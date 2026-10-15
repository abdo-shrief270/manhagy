<?php

declare(strict_types=1);

namespace App\Modules\Media\Contracts;

/**
 * The video provider. Videos never pass through our server: the teacher's browser uploads to the
 * provider, students watch from the provider; we only hand out short-lived, signed permissions.
 */
interface VideoHost
{
    /**
     * A new video at the provider and where the browser uploads it.
     *
     * @return array{video_id: string, upload_url: string, headers: array<string, string>, expires_at: string}
     */
    public function createUpload(string $title): array;

    /** @return array{state: string, duration_seconds: int|null} state = processing | ready | failed */
    public function status(string $videoId): array;

    /**
     * A signed HLS address valid for a few minutes.
     *
     * @return array{url: string, expires_at: string}
     */
    public function playback(string $videoId, int $ttlSeconds): array;

    public function delete(string $videoId): void;

    public function name(): string;
}
