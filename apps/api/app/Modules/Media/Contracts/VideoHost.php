<?php

declare(strict_types=1);

namespace App\Modules\Media\Contracts;

/**
 * Where videos live. `local` = our own server (chunked upload to the API, ffmpeg → encrypted HLS,
 * served by the API); `fake` = development and tests. We only ever hand out short-lived, signed
 * permissions to watch.
 */
interface VideoHost
{
    /**
     * A new video and how the browser uploads it: `put` = the whole file in one PUT to `upload_url`,
     * `chunks` = consecutive PUTs of `chunk_bytes` with `?offset=` (resumable: GET says how much arrived).
     *
     * @return array{video_id: string, method: string, upload_url: string, headers: array<string, string>, chunk_bytes: int|null, expires_at: string}
     */
    public function createUpload(string $title): array;

    /** The browser says the upload is complete: processing starts. */
    public function finishUpload(string $videoId): void;

    /** @return array{state: string, duration_seconds: int|null, error?: string|null} state = uploading | processing | ready | failed */
    public function status(string $videoId): array;

    /**
     * A signed HLS address valid for a few minutes. `$viewer` (a playback session) is checked with
     * PlaybackGuard on every key request, so a copied link stops working when that session ends.
     *
     * @return array{url: string, expires_at: string, poster?: string|null}
     */
    public function playback(string $videoId, int $ttlSeconds, ?string $viewer = null): array;

    public function delete(string $videoId): void;

    public function name(): string;
}
