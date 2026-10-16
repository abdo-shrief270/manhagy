<?php

declare(strict_types=1);

namespace App\Modules\Media\Support\Video;

/**
 * The permission inside every HLS address we serve: video, scope (`s` = playlists, segments and
 * poster; `k` = the decryption key), expiry and the viewer it was issued for, signed with the app key.
 */
final class HlsToken
{
    public static function make(string $video, string $scope, int $expires, ?string $viewer): string
    {
        $payload = rtrim(strtr(base64_encode("{$video}|{$scope}|{$expires}|".($viewer ?? '')), '+/', '-_'), '=');

        return $payload.'.'.self::sign($payload);
    }

    /** @return array{expires: int, viewer: string|null}|null null = forged, for another video / scope, or expired */
    public static function read(?string $token, string $video, string $scope): ?array
    {
        if ($token === null || ! str_contains($token, '.')) {
            return null;
        }
        [$payload, $signature] = explode('.', $token, 2);
        if (! hash_equals(self::sign($payload), $signature)) {
            return null;
        }
        $parts = explode('|', (string) base64_decode(strtr($payload, '-_', '+/'), true));
        if (count($parts) !== 4 || $parts[0] !== $video || $parts[1] !== $scope || (int) $parts[2] < time()) {
            return null;
        }

        return ['expires' => (int) $parts[2], 'viewer' => $parts[3] === '' ? null : $parts[3]];
    }

    private static function sign(string $payload): string
    {
        return substr(hash_hmac('sha256', $payload, 'hls|'.config('app.key')), 0, 32);
    }
}
