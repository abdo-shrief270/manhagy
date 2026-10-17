<?php

declare(strict_types=1);

namespace App\Modules\Live\Console;

use App\Modules\Courses\Contracts\LessonMedia;
use App\Modules\Live\Models\LiveAttendance;
use App\Modules\Live\Models\LiveSession;
use App\Modules\Live\Support\LiveServer;
use App\Modules\Live\Support\Studio;
use App\Modules\Media\Contracts\MediaLibrary;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Console\Command;

/**
 * Every minute: attendances that stopped beating are closed; a session whose broadcast has been gone
 * from the live server for 20 minutes is ended (the teacher closed the tab); recordings that finished
 * encoding become their lesson's video.
 */
final class SyncLive extends Command
{
    protected $signature = 'live:sync';

    protected $description = 'Close stale attendances, end abandoned broadcasts, attach ready recordings';

    public function handle(LiveServer $server, MediaLibrary $media, LessonMedia $lessons, CurrentTenant $tenant): int
    {
        LiveAttendance::withoutTenancy()->whereNull('ended_at')->where('last_beat_at', '<', now()->subSeconds(LiveAttendance::STALE_SECONDS))
            ->update(['ended_at' => now(), 'ended_reason' => 'stale']);

        $onAir = $server->configured() ? $server->onAir() : null;
        if ($onAir !== null) {
            LiveSession::withoutTenancy()->where('status', 'live')->where('last_published_at', '<', now()->subMinutes(20))->get()
                ->reject(fn (LiveSession $s) => in_array($s->path(), $onAir, true))
                ->each(fn (LiveSession $s) => $tenant->runAs($s->tenant_id, fn () => app(Studio::class)->end($s)));
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
}
