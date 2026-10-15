<?php

declare(strict_types=1);

namespace App\Modules\Access\Support;

use App\Modules\Access\Contracts\Enrollments;
use App\Modules\Access\Models\LessonProgress;
use App\Modules\Access\Models\PlaybackSession;
use App\Modules\Courses\Contracts\CourseCatalog;
use App\Modules\Media\Contracts\MediaLibrary;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Support\Facades\DB;

/**
 * Opening a lesson and the playback session: one open session per student (a newer one ends
 * the others), a heartbeat every minute renews the short-lived link, and a view counts only
 * after the student watched count_percent of the video (time measured by the server).
 */
final class Player
{
    /** Life of a playback / file link. */
    public const LINK_SECONDS = 300;

    public const BEAT_SECONDS = 60;

    /** No beat for this long = the session is over. */
    public const STALE_SECONDS = 150;

    /** The most one beat can add (a late beat doesn't count the gap). */
    private const MAX_CREDIT = 90;

    public function __construct(
        private readonly CourseCatalog $courses,
        private readonly Enrollments $enrollments,
        private readonly MediaLibrary $media,
    ) {}

    /**
     * @param  array<string, mixed>  $learner  LearnerDirectory::current()
     * @return array<string, mixed>
     */
    public function open(array $learner, string $lessonId, ?string $ip): array
    {
        $lesson = $this->courses->lesson($lessonId);
        if ($lesson === null || $lesson['course_status'] !== 'published') {
            abort(404);
        }
        $outline = $this->courses->outline($lesson['course_id']);
        $progress = LessonProgress::query()->where('learner_id', $learner['learner_id'])->whereIn('lesson_id', array_column($outline, 'id'))->get()->keyBy('lesson_id')->all();
        $reason = Rules::lockReason($lesson, $outline, $this->enrollments->hasAccess($learner['learner_id'], $lesson['course_id']), $progress);
        if ($reason !== null) {
            throw new DomainRuleException(Rules::MESSAGES[$reason], $reason, $reason === 'exam_soon' ? 409 : 403, ['publish_at' => $lesson['publish_at']]);
        }

        return DB::transaction(function () use ($learner, $lesson, $ip): array {
            $row = LessonProgress::query()->firstOrCreate(['learner_id' => $learner['learner_id'], 'lesson_id' => $lesson['id']]);
            $row->last_viewed_at = now();

            if ($lesson['kind'] === 'pdf') {
                $row->views++;
                $row->completed_at ??= now();
                $row->save();

                return ['kind' => 'pdf', 'file_url' => $this->media->fileUrl($lesson['media_id'], intdiv(self::LINK_SECONDS, 60)), 'views_left' => $row->viewsLeft($lesson['max_views'])];
            }

            $playback = $this->media->playback($lesson['media_id'], self::LINK_SECONDS);
            $row->save();
            PlaybackSession::query()->where('learner_id', $learner['learner_id'])->whereNull('ended_at')
                ->update(['ended_at' => now(), 'ended_reason' => 'replaced']);
            $session = PlaybackSession::query()->create([
                'learner_id' => $learner['learner_id'], 'lesson_id' => $lesson['id'], 'device_id' => $learner['device_id'] ?? null,
                'duration_seconds' => $this->media->find($lesson['media_id'])['duration_seconds'] ?? $lesson['duration_seconds'],
                'ip' => $ip, 'started_at' => now(), 'last_beat_at' => now(),
            ]);

            return [
                'kind' => 'video',
                'session_id' => $session->id,
                'playback' => $playback,
                'resume_at' => $row->last_position,
                'views_left' => $row->viewsLeft($lesson['max_views']),
                'beat_seconds' => self::BEAT_SECONDS,
                'watermark' => $this->watermark($learner, $session),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $learner
     * @return array<string, mixed>
     */
    public function beat(array $learner, string $sessionId, int $position, bool $playing): array
    {
        // Ending a session is saved, then refused outside the transaction (a throw inside would undo it).
        $result = DB::transaction(function () use ($learner, $sessionId, $position, $playing): array|PlaybackSession {
            $session = PlaybackSession::query()->where('learner_id', $learner['learner_id'])->lockForUpdate()->findOrFail($sessionId);
            if ($session->ended_at === null && $session->last_beat_at->lt(now()->subSeconds(self::STALE_SECONDS))) {
                $session->fill(['ended_at' => now(), 'ended_reason' => 'expired'])->save();
            }
            if ($session->ended_at !== null) {
                return $session;
            }
            $lesson = $this->courses->lesson($session->lesson_id) ?? abort(404);

            $credit = $playing ? min(self::MAX_CREDIT, (int) $session->last_beat_at->diffInSeconds(now(), true)) : 0;
            $session->fill(['watched_seconds' => $session->watched_seconds + $credit, 'position' => max(0, $position), 'last_beat_at' => now()]);

            $row = LessonProgress::query()->where('learner_id', $learner['learner_id'])->where('lesson_id', $lesson['id'])->lockForUpdate()->firstOrFail();
            $row->watched_seconds += $credit;
            $row->last_position = max(0, $position);
            $justCounted = false;
            if (! $session->counted && $session->duration_seconds) {
                $row->pending_seconds += $credit;
                $needed = (int) ceil($session->duration_seconds * $lesson['count_percent'] / 100);
                if ($row->pending_seconds >= $needed) {
                    $row->views++;
                    $row->pending_seconds = 0;
                    $row->completed_at ??= now();
                    $session->counted = $justCounted = true;
                }
            }
            $row->save();

            // A counted session may finish the video (pauses, rewinding), not replay it forever.
            if ($session->counted && $session->duration_seconds && $session->watched_seconds > 2 * $session->duration_seconds) {
                $session->fill(['ended_at' => now(), 'ended_reason' => 'finished'])->save();

                return $session;
            }
            $session->save();

            return [
                'playback' => $lesson['media_id'] !== null ? $this->media->playback($lesson['media_id'], self::LINK_SECONDS) : null,
                'counted' => $justCounted,
                'views_left' => $row->viewsLeft($lesson['max_views']),
                'watermark' => $this->watermark($learner, $session),
            ];
        });

        return $result instanceof PlaybackSession ? throw $this->ended($result) : $result;
    }

    /** @param  array<string, mixed>  $learner */
    public function stop(array $learner, string $sessionId): void
    {
        PlaybackSession::query()->where('learner_id', $learner['learner_id'])->whereNull('ended_at')->whereKey($sessionId)
            ->update(['ended_at' => now(), 'ended_reason' => 'stopped']);
    }

    private function ended(PlaybackSession $session): DomainRuleException
    {
        return new DomainRuleException(match ($session->ended_reason) {
            'replaced' => 'حسابك بيتفرّج على درس من مكان تاني، فوقفنا هنا.',
            'finished' => 'خلصت المشاهدة دي. افتح الدرس تاني لو عايز تشوفه.',
            default => 'المشاهدة دي خلصت. افتح الدرس تاني.',
        }, 'playback_ended', 409, ['reason' => $session->ended_reason]);
    }

    /**
     * What the player draws over the video, moving it around: the student's name and phone and
     * the time. Signed so the player can't be fed another text.
     *
     * @param  array<string, mixed>  $learner
     * @return array<string, string>
     */
    private function watermark(array $learner, PlaybackSession $session): array
    {
        $phone = (string) $learner['phone'];
        $text = $learner['name'].' · '.(str_starts_with($phone, '+20') ? '0'.substr($phone, 3) : $phone);
        $issued = now()->toIso8601String();

        return ['text' => $text, 'issued_at' => $issued, 'signature' => hash_hmac('sha256', "{$session->id}|{$text}|{$issued}", (string) config('app.key'))];
    }
}
