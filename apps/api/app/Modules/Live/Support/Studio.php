<?php

declare(strict_types=1);

namespace App\Modules\Live\Support;

use App\Modules\Courses\Contracts\CourseCatalog;
use App\Modules\Learners\Contracts\LearnerDirectory;
use App\Modules\Live\Jobs\StartRecording;
use App\Modules\Live\Models\LiveAttendance;
use App\Modules\Live\Models\LiveHand;
use App\Modules\Live\Models\LiveMessage;
use App\Modules\Live\Models\LivePoll;
use App\Modules\Live\Models\LiveSession;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The teacher's side: going live (the LiveKit room, the recording, a token to publish), giving
 * students the floor, the chat, polls, who attended, ending.
 */
final class Studio
{
    public function __construct(
        private readonly CourseCatalog $courses,
        private readonly LiveKit $livekit,
        private readonly LearnerDirectory $learners,
        private readonly Auditor $auditor,
    ) {}

    /** Students watching now (beating). */
    public static function viewers(LiveSession $session): int
    {
        return LiveAttendance::query()->where('session_id', $session->id)->whereNull('ended_at')
            ->where('last_beat_at', '>', now()->subSeconds(LiveAttendance::STALE_SECONDS))->count();
    }

    public static function identity(string $userId): string
    {
        return "t:{$userId}";
    }

    /** @return array{0: array<string, mixed>, 1: LiveSession} */
    public function session(string $lessonId): array
    {
        $lesson = $this->courses->lesson($lessonId);
        if ($lesson === null || $lesson['kind'] !== 'live') {
            abort(404);
        }

        return [$lesson, LiveSession::query()->firstOrCreate(['lesson_id' => $lessonId])];
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
            'server_ready' => $this->livekit->configured(),
            'obs_available' => (bool) config('services.live.ingress'),
            'obs' => $session->ingress_url === null ? null : ['server' => $session->ingress_url, 'key' => $session->ingress_key],
            'hands' => LiveHand::query()->where('session_id', $session->id)->where('status', '!=', 'done')->orderBy('raised_at')->get()
                ->map(fn (LiveHand $h) => $h->toSummary())->all(),
        ];
    }

    /**
     * Into the room: the teacher (live.host) publishes; others in the studio (live.moderate) watch hidden.
     * The first time the host comes in, the session goes live and the recording starts.
     *
     * @return array{url: string, token: string, identity: string}
     */
    public function join(LiveSession $session, string $userId, string $userName, bool $host): array
    {
        if (! $this->livekit->configured()) {
            throw new DomainRuleException('سيرفر البث مش متظبط.', 'live_unavailable', 503);
        }
        if ($session->status === 'ended') {
            throw new DomainRuleException('الحصة دي خلصت.', 'live_ended');
        }
        if ($host) {
            $this->livekit->createRoom($session->room()); // the same room again if it's there
        }
        if ($host && $session->status === 'idle') {
            $session->forceFill(['status' => 'live', 'started_at' => now()])->save();
            $this->auditor->record('live.started', 'بدأ بث حصة لايف', $session);
            if (config('services.live.recording')) {
                // Egress can take seconds to answer (or not be there at all): never hold the teacher's join for it.
                StartRecording::dispatch($session->tenant_id, $session->id)->afterCommit();
            }
        }

        return [
            'url' => $this->livekit->url(),
            'token' => $this->livekit->token($session->room(), self::identity($userId), $userName, $host
                ? ['canPublish' => true, 'canPublishSources' => LiveKit::SOURCES_TEACHER]
                : ['hidden' => true], metadata: (string) json_encode(['role' => 'teacher'])),
            'identity' => self::identity($userId),
        ];
    }

    /** Gives a student the floor (microphone, and the camera if `$camera`), or takes it back. */
    public function floor(LiveSession $session, string $learnerId, bool $allow, bool $camera): array
    {
        if ($session->status !== 'live') {
            throw new DomainRuleException('الحصة مش لايف.', 'live_not_on_air');
        }
        $hand = LiveHand::query()->where('session_id', $session->id)->where('learner_id', $learnerId)->first();
        if ($hand === null) {
            $learner = $this->learners->find($learnerId) ?? abort(404);
            $hand = new LiveHand(['session_id' => $session->id, 'learner_id' => $learnerId, 'name' => $learner['name'], 'raised_at' => now()]);
        }
        $this->livekit->setPublish($session->room(), Room::identity($learnerId), $allow ? Room::studentSources($camera) : []);
        $hand->fill(['status' => $allow ? 'speaking' : 'done', 'camera' => $allow && $camera, 'decided_at' => now()])->save();
        $this->livekit->signal($session->room(), ['type' => 'hands']);

        return $hand->toSummary();
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
            LiveHand::query()->where('session_id', $session->id)->where('status', '!=', 'done')->update(['status' => 'done', 'decided_at' => now()]);
            $this->auditor->record('live.ended', 'انتهى بث حصة لايف', $session);
        });
        try {
            $this->livekit->signal($session->room(), ['type' => 'ended']);
            if ($session->egress_id !== null) {
                $this->livekit->stopRecording($session->egress_id);
                $session->forceFill(['recording' => 'collecting'])->save();
            }
            if ($session->ingress_id !== null) {
                $this->livekit->deleteIngress($session->ingress_id);
            }
            $this->livekit->deleteRoom($session->room()); // everyone out
        } catch (RuntimeException $e) {
            // The server will close an empty room itself; live:sync picks the recording up.
            report($e);
        }
    }

    /** OBS: an RTMP input (LiveKit Ingress) that joins the room as the teacher. */
    public function obs(LiveSession $session, string $userId, string $userName): void
    {
        if (! config('services.live.ingress')) {
            throw new DomainRuleException('البث من OBS مش متاح على السيرفر ده.', 'obs_unavailable');
        }
        if ($session->status === 'ended') {
            throw new DomainRuleException('الحصة دي خلصت.', 'live_ended');
        }
        if ($session->ingress_id !== null) {
            $this->livekit->deleteIngress($session->ingress_id);
        }
        $ingress = $this->livekit->createIngress($session->room(), self::identity($userId).':obs', $userName);
        $session->forceFill(['ingress_id' => $ingress['id'], 'ingress_url' => $ingress['url'], 'ingress_key' => $ingress['key']])->save();
        $this->auditor->record('live.obs_key', 'مفتاح OBS لحصة لايف', $session);
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
            'hands' => LiveHand::query()->where('session_id', $session->id)->where('status', '!=', 'done')->orderBy('raised_at')->get()
                ->map(fn (LiveHand $h) => $h->toSummary())->all(),
        ];
    }

    /** Teacher messages / polls / moderation: everyone looks again now. */
    public function nudge(LiveSession $session, string $type = 'feed'): void
    {
        if ($session->status === 'live') {
            $this->livekit->signal($session->room(), ['type' => $type]);
        }
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

    /** Egress records the room into one MP4 (from StartRecording, once the lesson is live). */
    public function record(LiveSession $session): void
    {
        if ($session->status !== 'live' || $session->egress_id !== null) {
            return;
        }
        try {
            $egress = $this->livekit->startRecording($session->room(), config('services.live.egress_path')."/{$session->room()}.mp4");
            $session->forceFill(['egress_id' => $egress, 'recording' => 'recording', 'recording_error' => null])->save();
        } catch (RuntimeException $e) {
            // The lesson goes on without a recording; the studio says so.
            report($e);
            $session->forceFill(['recording' => 'failed', 'recording_error' => 'سيرفر التسجيل ما ردّش، فالحصة دي مش بتتسجل. البث نفسه شغال عادي.'])->save();
        }
    }
}
