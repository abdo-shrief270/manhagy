<?php

declare(strict_types=1);

namespace App\Modules\Live\Console;

use App\Modules\Courses\Contracts\LessonMedia;
use App\Modules\Live\Jobs\CollectRecording;
use App\Modules\Live\Models\LiveAttendance;
use App\Modules\Live\Models\LiveSession;
use App\Modules\Live\Support\LiveKit;
use App\Modules\Live\Support\Studio;
use App\Modules\Media\Contracts\MediaLibrary;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Every minute: attendances that stopped beating are closed; a live room left empty for 20 minutes is
 * ended (the teacher closed the tab); finished Egress recordings are taken in; encoded recordings
 * become their lesson's video. Asks LiveKit itself, so no webhook is needed.
 */
final class SyncLive extends Command
{
    private const ABANDONED_MINUTES = 20;

    protected $signature = 'live:sync';

    protected $description = 'Close stale attendances, end abandoned rooms, take in and attach recordings';

    public function handle(LiveKit $livekit, MediaLibrary $media, LessonMedia $lessons, CurrentTenant $tenant): int
    {
        LiveAttendance::withoutTenancy()->whereNull('ended_at')->where('last_beat_at', '<', now()->subSeconds(LiveAttendance::STALE_SECONDS))
            ->update(['ended_at' => now(), 'ended_reason' => 'stale']);

        if ($livekit->configured()) {
            $this->endAbandoned($livekit, $tenant);
            $this->takeRecordings($livekit);
        }

        LiveSession::withoutTenancy()->where('recording', 'processing')->whereNotNull('replay_media_id')->get()
            ->each(function (LiveSession $session) use ($media, $lessons, $tenant): void {
                $tenant->runAs($session->tenant_id, function () use ($session, $media, $lessons): void {
                    $asset = $media->find((string) $session->replay_media_id);
                    if ($asset === null || $asset['status'] === 'failed') {
                        $session->forceFill(['recording' => 'failed', 'recording_error' => 'التحويل فشل.'])->save();
                    } elseif ($asset['status'] === 'ready') {
                        $lessons->attachReplay($session->lesson_id, $asset['id'], $asset['duration_seconds']);
                        $session->forceFill(['recording' => 'ready'])->save();
                    }
                });
            });

        return self::SUCCESS;
    }

    private function endAbandoned(LiveKit $livekit, CurrentTenant $tenant): void
    {
        foreach (LiveSession::withoutTenancy()->where('status', 'live')->get() as $session) {
            try {
                $empty = $livekit->participants($session->room()) === [];
            } catch (RuntimeException) {
                continue; // the server can't be asked: decide nothing
            }
            $key = "live:empty:{$session->id}";
            if (! $empty) {
                Cache::forget($key);

                continue;
            }
            $since = Cache::get($key);
            if ($since === null) {
                Cache::put($key, now()->toIso8601String(), now()->addHours(2));
            } elseif (now()->parse($since)->lt(now()->subMinutes(self::ABANDONED_MINUTES))) {
                Cache::forget($key);
                $tenant->runAs($session->tenant_id, fn () => app(Studio::class)->end($session));
            }
        }
    }

    private function takeRecordings(LiveKit $livekit): void
    {
        foreach (LiveSession::withoutTenancy()->where('recording', 'collecting')->whereNotNull('egress_id')->get() as $session) {
            try {
                $egress = $livekit->recording((string) $session->egress_id);
            } catch (RuntimeException) {
                continue;
            }
            $status = $egress['status'] ?? '';
            if ($status === 'EGRESS_COMPLETE' && ! empty($egress['file'])) {
                // Egress wrote it under its own path: the API sees the same folder elsewhere, or fetches it.
                CollectRecording::dispatch($session->tenant_id, $session->id, basename((string) $egress['file']));
            } elseif (in_array($status, ['EGRESS_FAILED', 'EGRESS_ABORTED', 'EGRESS_LIMIT_REACHED'], true) || ($egress === null && $session->ended_at?->lt(now()->subHour()))) {
                $session->forceFill(['recording' => 'failed', 'recording_error' => mb_substr($egress['error'] ?? 'التسجيل ما اكتملش.', 0, 300)])->save();
            }
        }
    }
}
