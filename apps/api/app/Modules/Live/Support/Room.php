<?php

declare(strict_types=1);

namespace App\Modules\Live\Support;

use App\Modules\Access\Contracts\Enrollments;
use App\Modules\Access\Contracts\LessonAccess;
use App\Modules\Courses\Contracts\CourseCatalog;
use App\Modules\Live\Models\LiveAttendance;
use App\Modules\Live\Models\LiveMessage;
use App\Modules\Live\Models\LivePoll;
use App\Modules\Live\Models\LivePollVote;
use App\Modules\Live\Models\LiveSession;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The student's side of a live lesson: the waiting room, joining (one device at a time, a token for
 * the stream bound to the attendance), beats that credit the minutes watched, the chat / questions
 * feed, polls.
 */
final class Room
{
    public const BEAT_SECONDS = 30;

    /** The most one beat can credit (a late beat doesn't count the gap). */
    private const MAX_CREDIT = 45;

    public function __construct(
        private readonly CourseCatalog $courses,
        private readonly LessonAccess $access,
        private readonly Enrollments $enrollments,
        private readonly LiveServer $server,
    ) {}

    /**
     * The live lesson for this student: its session, or a refusal (not enrolled, locked).
     *
     * @param  array<string, mixed>  $learner
     * @return array{0: array<string, mixed>, 1: LiveSession}
     */
    public function enter(array $learner, string $lessonId): array
    {
        $lesson = $this->courses->lesson($lessonId);
        if ($lesson === null || $lesson['kind'] !== 'live' || $lesson['course_status'] !== 'published') {
            abort(404);
        }
        $reason = $this->access->lockReason($learner['learner_id'], $lessonId);
        $session = LiveSession::query()->firstOrCreate(['lesson_id' => $lessonId], ['stream_key' => Studio::newKey()]);
        // Before its time the lesson is «not yet»; the room still opens (waiting room, or the teacher
        // started early), for whoever may watch it.
        if ($reason === 'not_yet' && ! $lesson['free_preview'] && ! $this->enrollments->hasAccess($learner['learner_id'], $lesson['course_id'])) {
            $reason = 'not_enrolled';
        }
        if ($reason !== null && $reason !== 'not_yet') {
            throw new DomainRuleException(LessonAccess::MESSAGES[$reason], $reason, 403);
        }

        return [$lesson, $session];
    }

    /**
     * @param  array<string, mixed>  $learner
     * @return array<string, mixed>
     */
    public function join(array $learner, string $lessonId, ?string $ip): array
    {
        [$lesson, $session] = $this->enter($learner, $lessonId);
        $state = $this->state($lesson, $session);
        if ($session->status !== 'live') {
            return $state;
        }
        if (! $this->server->configured()) {
            throw new DomainRuleException('البث مش متاح دلوقتي.', 'live_unavailable', 503);
        }

        $attendance = DB::transaction(function () use ($learner, $session, $ip): LiveAttendance {
            LiveAttendance::query()->where('learner_id', $learner['learner_id'])->whereNull('ended_at')
                ->update(['ended_at' => now(), 'ended_reason' => 'replaced']);

            return LiveAttendance::query()->create([
                'session_id' => $session->id, 'learner_id' => $learner['learner_id'], 'device_id' => $learner['device_id'] ?? null,
                'ip' => $ip, 'joined_at' => now(), 'last_beat_at' => now(),
            ]);
        });
        $viewers = Studio::viewers($session);
        if ($viewers > $session->peak_viewers) {
            $session->forceFill(['peak_viewers' => $viewers])->save();
        }

        return [
            ...$state,
            'attendance_id' => $attendance->id,
            'stream_url' => $this->server->hlsUrl($session, LiveToken::make($attendance->id, $session->id, now()->addHours(8)->getTimestamp())),
            'beat_seconds' => self::BEAT_SECONDS,
            'watermark' => ['text' => $learner['name'].' · '.self::localPhone((string) $learner['phone'])],
        ];
    }

    /**
     * @param  array<string, mixed>  $learner
     * @return array<string, mixed>
     */
    public function beat(array $learner, string $attendanceId, bool $playing): array
    {
        $attendance = LiveAttendance::query()->where('learner_id', $learner['learner_id'])->findOrFail($attendanceId);
        $session = LiveSession::query()->findOrFail($attendance->session_id);
        if ($attendance->ended_at === null && ! $attendance->isOpen()) {
            $attendance->forceFill(['ended_at' => now(), 'ended_reason' => 'stale'])->save();
        }
        if ($attendance->ended_at !== null || $session->status === 'ended') {
            $reason = $session->status === 'ended' ? 'session_ended' : (string) $attendance->ended_reason;
            throw new DomainRuleException(['replaced' => 'فتحت اللايف من جهاز تاني.', 'session_ended' => 'الحصة خلصت.'][$reason] ?? 'رجعت؟ ادخل الحصة تاني.', 'live_left', 409, ['reason' => $reason]);
        }
        $credit = $playing ? min(self::MAX_CREDIT, (int) $attendance->last_beat_at->diffInSeconds(now(), true)) : 0;
        $attendance->forceFill(['last_beat_at' => now(), 'seconds' => $attendance->seconds + $credit])->save();

        return ['seconds' => $attendance->seconds, 'viewers' => Studio::viewers($session)];
    }

    /** @param  array<string, mixed>  $learner */
    public function leave(array $learner, string $attendanceId): void
    {
        LiveAttendance::query()->where('learner_id', $learner['learner_id'])->whereKey($attendanceId)->whereNull('ended_at')
            ->update(['ended_at' => now(), 'ended_reason' => 'left']);
    }

    /**
     * What a student sees: the teacher's messages and the chat (if open) for everyone, their own
     * questions, pinned ones, and the open poll.
     *
     * @param  array<string, mixed>  $lesson
     * @return array<string, mixed>
     */
    public function feed(array $lesson, LiveSession $session, string $learnerId, int $after): array
    {
        // The part everyone shares, cached a moment: hundreds of students ask every few seconds.
        $shared = Cache::remember("live:feed:{$session->id}:{$after}", 2, fn () => LiveMessage::query()->where('session_id', $session->id)
            ->where('seq', '>', $after)->where('hidden', false)
            ->where(fn ($q) => $q->whereNull('learner_id')->orWhere('pinned', true)->orWhere('kind', 'chat'))
            ->orderBy('seq')->limit(200)->get()->map(fn (LiveMessage $m) => $m->toSummary())->all());
        $mine = LiveMessage::query()->where('session_id', $session->id)->where('learner_id', $learnerId)->where('seq', '>', $after)
            ->where('kind', 'question')->orderBy('seq')->get()->map(fn (LiveMessage $m) => $m->toSummary($learnerId))->all();
        $messages = collect([...$mine, ...$shared])->unique('id')->sortBy('seq')->values()->all();

        $poll = LivePoll::query()->where('session_id', $session->id)->latest()->first();
        $vote = $poll === null ? null : LivePollVote::query()->where('poll_id', $poll->id)->where('learner_id', $learnerId)->value('option');

        return [
            ...$this->state($lesson, $session),
            'messages' => $messages,
            'poll' => $poll === null || ($poll->closed_at !== null && $poll->closed_at->lt(now()->subMinutes(10)))
                ? null : $poll->toSummary($vote !== null || $poll->closed_at !== null, $vote === null ? null : (int) $vote),
        ];
    }

    /**
     * @param  array<string, mixed>  $learner
     * @return array<string, mixed>
     */
    public function post(array $learner, LiveSession $session, string $kind, string $body): array
    {
        if ($session->status !== 'live') {
            throw new DomainRuleException('الشات بيفتح وقت الحصة.', 'live_not_on_air');
        }
        if ($session->chat_mode === 'off' || ($kind === 'chat' && $session->chat_mode === 'questions')) {
            throw new DomainRuleException($session->chat_mode === 'off' ? 'المدرس قفل الشات.' : 'المدرس مستقبل أسئلة بس دلوقتي.', 'live_chat_closed');
        }

        return LiveMessage::query()->create([
            'session_id' => $session->id, 'learner_id' => $learner['learner_id'], 'author_name' => self::firstName((string) $learner['name']),
            'kind' => $kind, 'body' => $body, 'created_at' => now(),
        ])->refresh()->toSummary($learner['learner_id']);
    }

    /** @param  array<string, mixed>  $learner */
    public function vote(array $learner, string $pollId, int $option): array
    {
        $poll = LivePoll::query()->findOrFail($pollId);
        if ($poll->closed_at !== null || ! array_key_exists($option, $poll->options)) {
            throw new DomainRuleException('الاستفتاء ده اتقفل.', 'poll_closed');
        }
        LivePollVote::query()->firstOrCreate(['poll_id' => $poll->id, 'learner_id' => $learner['learner_id']], ['option' => $option, 'created_at' => now()]);
        $mine = (int) LivePollVote::query()->where('poll_id', $poll->id)->where('learner_id', $learner['learner_id'])->value('option');

        return $poll->toSummary(true, $mine);
    }

    /**
     * @param  array<string, mixed>  $lesson
     * @return array<string, mixed>
     */
    public function state(array $lesson, LiveSession $session): array
    {
        return [
            'lesson_id' => $lesson['id'],
            'title' => $lesson['title'],
            'starts_at' => $lesson['publish_at'],
            'status' => $session->status,
            'chat_mode' => $session->chat_mode,
            'started_at' => $session->started_at?->toIso8601String(),
            'ended_at' => $session->ended_at?->toIso8601String(),
            'replay_ready' => $session->recording === 'ready',
        ];
    }

    private static function localPhone(string $phone): string
    {
        return str_starts_with($phone, '+20') ? '0'.substr($phone, 3) : $phone;
    }

    private static function firstName(string $name): string
    {
        return mb_substr(explode(' ', trim($name))[0] ?: 'طالب', 0, 60);
    }
}
