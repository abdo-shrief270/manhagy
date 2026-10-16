<?php

declare(strict_types=1);

namespace App\Modules\Media\Jobs;

use App\Modules\Media\Support\Video\Transcoder;
use App\Modules\Media\Support\Video\VideoFiles;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/** Encodes an uploaded video on the `video` queue (one at a time: ffmpeg takes the CPU). */
final class TranscodeVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 4 * 3600;

    public function __construct(public readonly string $videoId)
    {
        // Its own connection in production (hours-long retry_after); sync / database elsewhere.
        if (config('queue.default') === 'redis') {
            $this->onConnection('video');
        }
        $this->onQueue('video');
    }

    public function handle(Transcoder $transcoder): void
    {
        VideoFiles::writeState($this->videoId, ['state' => 'processing', 'started' => true]);
        $transcoder->run($this->videoId);
    }

    public function failed(?Throwable $e): void
    {
        VideoFiles::writeState($this->videoId, ['state' => 'failed', 'error' => mb_substr((string) $e?->getMessage(), 0, 300)]);
    }
}
