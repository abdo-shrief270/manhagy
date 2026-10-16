<?php

declare(strict_types=1);

namespace App\Modules\Media\Support\Hosts;

use App\Modules\Media\Contracts\VideoHost;
use Illuminate\Support\Str;

/**
 * Development and tests without ffmpeg: every video is ready at once with
 * `services.video.fake_duration` seconds; playback links are signed with the app key.
 */
final class FakeVideoHost implements VideoHost
{
    public function createUpload(string $title): array
    {
        $id = (string) Str::uuid();

        return ['video_id' => $id, 'method' => 'put', 'upload_url' => "https://upload.video.test/{$id}", 'headers' => ['X-Fake' => 'true'], 'chunk_bytes' => null, 'expires_at' => now()->addHour()->toIso8601String()];
    }

    public function finishUpload(string $videoId): void {}

    public function status(string $videoId): array
    {
        return ['state' => 'ready', 'duration_seconds' => (int) config('services.video.fake_duration', 600)];
    }

    public function playback(string $videoId, int $ttlSeconds, ?string $viewer = null): array
    {
        $expires = now()->addSeconds($ttlSeconds)->getTimestamp();
        $token = hash_hmac('sha256', "{$videoId}|{$expires}", (string) config('app.key'));

        return ['url' => "https://cdn.video.test/{$videoId}/playlist.m3u8?token={$token}&expires={$expires}", 'expires_at' => now()->setTimestamp($expires)->toIso8601String()];
    }

    public function delete(string $videoId): void {}

    public function name(): string
    {
        return 'fake';
    }
}
