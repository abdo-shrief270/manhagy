<?php

declare(strict_types=1);

namespace App\Modules\Media\Support\Hosts;

use App\Modules\Media\Contracts\VideoHost;
use App\Modules\Media\Jobs\TranscodeVideo;
use App\Modules\Media\Support\Video\HlsToken;
use App\Modules\Media\Support\Video\VideoFiles;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Videos on our own server. The browser sends the original in chunks to the API, `TranscodeVideo`
 * (ffmpeg) makes encrypted HLS, and HlsController serves it: playlists and segments with a signed
 * stream token, the key only with a short key token whose viewer PlaybackGuard still allows.
 */
final class LocalVideoHost implements VideoHost
{
    public function createUpload(string $title): array
    {
        $id = (string) Str::uuid7();
        Storage::disk(VideoFiles::DISK)->put(VideoFiles::dir($id).'/source.part', '');
        VideoFiles::writeState($id, ['state' => 'uploading', 'title' => $title]);

        return [
            'video_id' => $id,
            'method' => 'chunks',
            'upload_url' => url("/api/v1/media/uploads/{$id}"),
            'headers' => [],
            'chunk_bytes' => (int) config('services.video.chunk_mb', 8) * 1024 * 1024,
            'expires_at' => now()->addDay()->toIso8601String(),
        ];
    }

    /** How much of the original has arrived. */
    public function received(string $id): int
    {
        $part = VideoFiles::path($id, 'source.part');

        return is_file($part) ? (int) filesize($part) : 0;
    }

    /**
     * One chunk, appended only at the end of what arrived (a resend of an earlier chunk is refused).
     *
     * @param  resource  $body
     */
    public function append(string $id, int $offset, $body): int
    {
        if (VideoFiles::state($id)['state'] !== 'uploading') {
            throw new DomainRuleException('الفيديو ده اترفع خلاص.', 'upload_finished', 409);
        }
        $received = $this->received($id);
        if ($offset !== $received) {
            throw new DomainRuleException('الجزء ده مش في مكانه.', 'upload_offset', 409, ['received' => $received]);
        }
        $out = fopen(VideoFiles::path($id, 'source.part'), 'ab');
        $written = stream_copy_to_stream($body, $out, (int) config('services.video.chunk_mb', 8) * 1024 * 1024 + 1);
        fclose($out);
        clearstatcache();
        if ($this->received($id) > (int) config('services.video.max_mb', 4096) * 1024 * 1024) {
            $this->delete($id);
            throw new DomainRuleException('الفيديو أكبر من المسموح.', 'video_too_big', 422);
        }

        return $received + (int) $written;
    }

    public function finishUpload(string $videoId): void
    {
        if (VideoFiles::state($videoId)['state'] !== 'uploading') {
            return;
        }
        if ($this->received($videoId) === 0) {
            throw new DomainRuleException('الفيديو ما وصلش.', 'upload_empty', 422);
        }
        File::move(VideoFiles::path($videoId, 'source.part'), VideoFiles::path($videoId, 'source'));
        VideoFiles::writeState($videoId, ['state' => 'processing']);
        TranscodeVideo::dispatch($videoId);
    }

    public function status(string $videoId): array
    {
        $state = VideoFiles::state($videoId);

        return ['state' => (string) $state['state'], 'duration_seconds' => $state['duration_seconds'] ?? null, 'error' => $state['error'] ?? null];
    }

    public function playback(string $videoId, int $ttlSeconds, ?string $viewer = null): array
    {
        $expires = now()->addSeconds($ttlSeconds)->getTimestamp();
        $base = rtrim((string) config('services.video.public_base', config('app.url')), '/')."/api/v1/public/hls/{$videoId}";

        return [
            'url' => "{$base}/master.m3u8?t=".HlsToken::make($videoId, 's', $expires, $viewer),
            'poster' => "{$base}/poster.jpg?t=".HlsToken::make($videoId, 's', $expires, null),
            'expires_at' => now()->setTimestamp($expires)->toIso8601String(),
        ];
    }

    public function delete(string $videoId): void
    {
        if (VideoFiles::valid($videoId)) {
            Storage::disk(VideoFiles::DISK)->deleteDirectory(VideoFiles::dir($videoId));
        }
    }

    public function name(): string
    {
        return 'local';
    }
}
