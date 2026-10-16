<?php

declare(strict_types=1);

namespace App\Modules\Media\Support\Video;

use Illuminate\Support\Facades\Storage;

/**
 * A self-hosted video's folder on the private disk: videos/{id}/ = source(.part) while uploading,
 * state.json, enc.key (AES-128), {height}p/index.m3u8 + %04d.ts, poster.jpg.
 */
final class VideoFiles
{
    public const DISK = 'local';

    public static function dir(string $id): string
    {
        return "videos/{$id}";
    }

    public static function path(string $id, string $file = ''): string
    {
        return Storage::disk(self::DISK)->path(self::dir($id).($file === '' ? '' : "/{$file}"));
    }

    /** @return array<string, mixed> */
    public static function state(string $id): array
    {
        $raw = Storage::disk(self::DISK)->get(self::dir($id).'/state.json');

        return $raw === null ? ['state' => 'failed', 'error' => 'missing'] : (array) json_decode($raw, true);
    }

    /** @param  array<string, mixed>  $state */
    public static function writeState(string $id, array $state): void
    {
        Storage::disk(self::DISK)->put(self::dir($id).'/state.json', (string) json_encode([...$state, 'at' => now()->toIso8601String()]));
    }

    public static function valid(string $id): bool
    {
        return preg_match('/^[0-9a-f-]{36}$/', $id) === 1;
    }
}
