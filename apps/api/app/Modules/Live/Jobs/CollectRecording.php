<?php

declare(strict_types=1);

namespace App\Modules\Live\Jobs;

use App\Modules\Courses\Contracts\CourseCatalog;
use App\Modules\Live\Models\LiveSession;
use App\Modules\Media\Contracts\MediaLibrary;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * After a live lesson: the MP4 the LiveKit Egress wrote goes into the video library, encoded like any
 * upload. Read from the folder Egress shares with the API, or — LiveKit on a server of its own —
 * downloaded from it (`services.live.recordings_url`). `live:sync` puts it on the lesson once it's ready.
 */
final class CollectRecording implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public readonly string $tenantId, public readonly string $sessionId, public readonly string $file)
    {
        if (config('queue.default') === 'redis') {
            $this->onConnection('video');
        }
        $this->onQueue('video');
    }

    public function handle(MediaLibrary $media, CourseCatalog $courses, CurrentTenant $tenant): void
    {
        $tenant->runAs($this->tenantId, function () use ($media, $courses): void {
            $session = LiveSession::query()->findOrFail($this->sessionId);
            if ($session->recording !== 'collecting') {
                return;
            }
            $file = $this->fetch();
            if ($file === null || ! is_file($file) || filesize($file) === 0) {
                $session->forceFill(['recording' => 'failed', 'recording_error' => 'ملف التسجيل مش موجود.'])->save();

                return;
            }
            $title = 'تسجيل: '.($courses->lesson($session->lesson_id)['title'] ?? 'حصة لايف');
            $asset = $media->importVideo($title, $file, 'البث اللايف');
            $session->forceFill(['recording' => 'processing', 'replay_media_id' => $asset['id'], 'recording_error' => null])->save();
        });
    }

    /** The recording as a local file: in the shared folder, or downloaded next to the other uploads. */
    private function fetch(): ?string
    {
        $name = basename($this->file);
        $url = config('services.live.recordings_url');
        if ($url === null || $url === '') {
            return config('services.live.recordings_path').'/'.$name;
        }
        $local = storage_path('app/private/live-'.Str::random(12).'.mp4');
        @mkdir(dirname($local), 0775, true);
        $res = Http::timeout($this->timeout - 60)->withToken((string) config('services.live.secret'))->sink($local)->get(rtrim((string) $url, '/').'/'.rawurlencode($name));
        if (! $res->successful()) {
            @unlink($local);

            return null;
        }

        return $local;
    }

    public function failed(?Throwable $e): void
    {
        app(CurrentTenant::class)->runAs($this->tenantId, fn () => LiveSession::query()->whereKey($this->sessionId)
            ->update(['recording' => 'failed', 'recording_error' => mb_substr((string) $e?->getMessage(), 0, 300)]));
    }
}
