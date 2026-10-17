<?php

declare(strict_types=1);

namespace App\Modules\Live\Jobs;

use App\Modules\Courses\Contracts\CourseCatalog;
use App\Modules\Live\Models\LiveSession;
use App\Modules\Live\Support\LiveServer;
use App\Modules\Media\Contracts\MediaLibrary;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * After a live lesson: its recording from the live server (playback server, one MP4 from the first
 * recorded second to the last) into the video library, encoded like any upload. `live:sync` puts it
 * on the lesson once it's ready.
 */
final class CollectRecording implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 3 * 3600;

    /** @var list<int> */
    public array $backoff = [120, 600];

    public function __construct(public readonly string $tenantId, public readonly string $sessionId)
    {
        if (config('queue.default') === 'redis') {
            $this->onConnection('video');
        }
        $this->onQueue('video');
    }

    public function handle(LiveServer $server, MediaLibrary $media, CourseCatalog $courses, CurrentTenant $tenant): void
    {
        $tenant->runAs($this->tenantId, function () use ($server, $media, $courses): void {
            $session = LiveSession::query()->findOrFail($this->sessionId);
            if ($session->recording !== 'collecting') {
                return;
            }
            $spans = $server->recordings($session);
            if ($spans === []) {
                $session->forceFill(['recording' => 'failed', 'recording_error' => 'ما اتسجّلش حاجة.'])->save();

                return;
            }
            usort($spans, fn (array $a, array $b) => strcmp($a['start'], $b['start']));
            $first = $spans[0]['start'];
            $last = end($spans);
            $seconds = now()->parse($last['start'])->diffInSeconds(now()->parse($first), true) + $last['duration'];

            $file = tempnam(sys_get_temp_dir(), 'live').'.mp4';
            $server->download($session, $first, $seconds, $file);
            $title = 'تسجيل: '.($courses->lesson($session->lesson_id)['title'] ?? 'حصة لايف');
            $asset = $media->importVideo($title, $file, 'البث اللايف');
            @unlink($file);
            $session->forceFill(['recording' => 'processing', 'replay_media_id' => $asset['id'], 'recording_error' => null])->save();
        });
    }

    public function failed(?Throwable $e): void
    {
        app(CurrentTenant::class)->runAs($this->tenantId, fn () => LiveSession::query()->whereKey($this->sessionId)
            ->update(['recording' => 'failed', 'recording_error' => mb_substr((string) $e?->getMessage(), 0, 300)]));
    }
}
