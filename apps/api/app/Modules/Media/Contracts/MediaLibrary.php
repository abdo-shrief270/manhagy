<?php

declare(strict_types=1);

namespace App\Modules\Media\Contracts;

/**
 * For other modules: the account's videos and files. An asset is an array:
 * {id, kind, title, status, duration_seconds, size_bytes}.
 */
interface MediaLibrary
{
    /** @return array<string, mixed>|null */
    public function find(string $id): ?array;

    /**
     * A student's way into a ready video: a signed address valid for $ttlSeconds.
     *
     * @return array{url: string, expires_at: string}
     */
    public function playback(string $id, int $ttlSeconds): array;

    /** A signed download link for a file (PDF) valid for $minutes. */
    public function fileUrl(string $id, int $minutes): string;
}
