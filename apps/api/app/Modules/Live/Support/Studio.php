<?php

declare(strict_types=1);

namespace App\Modules\Live\Support;

use App\Modules\Courses\Contracts\CourseCatalog;
use App\Modules\Learners\Contracts\LearnerDirectory;
use App\Modules\Live\Jobs\CollectRecording;
use App\Modules\Live\Models\LiveAttendance;
use App\Modules\Live\Models\LiveMessage;
use App\Modules\Live\Models\LivePoll;
use App\Modules\Live\Models\LiveSession;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** The teacher's side: the session of a live lesson, going off air, the chat, polls, who attended. */
final class Studio
{
    public function __construct(
        private readonly CourseCatalog $courses,
        private readonly LiveServer $server,
        private readonly LearnerDirectory $learners,
        private readonly Auditor $auditor,
    ) {}

    public static function newKey(): string
    {
        return Str::random(24);
    }

    /** Students watching now (beating). */
    public static function viewers(LiveSession $session): int
    {
        return LiveAttendance::query()->where('session_id', $session->id)->whereNull('ended_at')
            ->where('last_beat_at', '>', now()->subSeconds(LiveAttendance::STALE_SECONDS))->count();
    }

    /** @return array{0: array<string, mixed>, 1: LiveSession} */
    public function session(string $lessonId): array
    {
        $lesson = $this->courses->lesson($lessonId);
        if ($lesson === null || $lesson['kind'] !== 'live') {
            abort(404);
        }

        return [$lesson, LiveSession::query()->firstOrCreate(['lesson_id' => $lessonId], ['stream_key' => self::newKey()])];
    }

    /**
     * @param  array<string, mixed>  $lesson
     * @return array<string, mixed>
     */
    public function state(array $lesson, LiveSession $session, bool $withKeys): array
    {
        return [
            'lesson_id' => $lesson['id'],
            'title' => $lesson['title'],
            'course_id' => $lesson['course_id'],
            'starts_at' => $lesson['publish_at'],
            'session_id' => $session->id,
            'status' => $session->status,
            'chat_mode' => $session->chat_mode,
            'started_at' => $session->started_at?->toIso8601String(),
            'ended_at' => $session->ended_at?->toIso8601String(),
            'viewers' => self::viewers($session),
            'peak_viewers' => $session->peak_viewers,
            'attended' => LiveAttendance::query()->where('session_id', $session->id)->distinct()->count('learner_id'),
            'recording' => $session->recording,
            'recording_error' => $session->recording_error,
            'server_ready' => $this->server->configured(),
            'publishing' => $withKeys && $this->server->configured() && $session->status !== 'ended' ? $this->server->publishing($session) : null,
        ];
    }

    public function end(LiveSession $session): void
    {
        if ($session->status === 'ended') {
            return;
        }
        DB::transaction(function () use ($session): void {
            $session->forceFill(['status' => 'ended', 'ended_at' => now()])->save();
            LiveAttendance::query()->where('session_id', $session->id)->whereNull('ended_at')
                ->update(['ended_at' => now(), 'ended_reason' => 'session_ended']);
            LivePoll::query()->where('session_id', $session->id)->whereNull('closed_at')->update(['closed_at' => now()]);
            $this->auditor->record('live.ended', 'انتهى بث حصة لايف', $session);
        });
        if ($session->started_at !== null && config('services.live.playback_url') !== '') {
            $session->forceFill(['recording' => 'collecting'])->save();
            // A minute for the live server to close the last recording segment.
            CollectRecording::dispatch($session->tenant_id, $session->id)->delay(now()->addMinute());
        }
        $this->server->kick($session);
    }

    public function rotateKey(LiveSession $session): void
    {
        if ($session->status === 'live') {
            throw new DomainRuleException('وقّف البث الأول قبل ما تغيّر المفتاح.', 'live_on_air');
        }
        $session->forceFill(['stream_key' => self::newKey()])->save();
        $this->auditor->record('live.key_rotated', 'مفتاح بث حصة لايف اتغيّر', $session);
    }

    /** @return array<string, mixed> everything since `$after`, hidden ones too */
    public function feed(LiveSession $session, int $after): array
    {
        $poll = LivePoll::query()->where('session_id', $session->id)->latest()->first();

        return [
            'messages' => LiveMessage::query()->where('session_id', $session->id)->where('seq', '>', $after)->orderBy('seq')->limit(500)->get()
                ->map(fn (LiveMessage $m) => $m->toSummary())->all(),
            'poll' => $poll?->toSummary(true),
            'viewers' => self::viewers($session),
            'status' => $session->status,
        ];
    }

    /** @return list<array<string, mixed>> who attended and for how long, longest first */
    public function attendance(LiveSession $session): array
    {
        $rows = LiveAttendance::query()->where('session_id', $session->id)
            ->selectRaw('learner_id, sum(seconds) as seconds, min(joined_at) as first_joined, count(*) as joins')
            ->groupBy('learner_id')->orderByDesc('seconds')->get();

        return $rows->map(function ($r): array {
            $learner = $this->learners->find($r->learner_id);

            return [
                'learner_id' => $r->learner_id, 'name' => $learner['name'] ?? '—', 'phone' => $learner['phone'] ?? null,
                'minutes' => intdiv((int) $r->seconds, 60), 'joined_at' => now()->parse($r->first_joined)->toIso8601String(), 'joins' => (int) $r->joins,
            ];
        })->all();
    }
}
