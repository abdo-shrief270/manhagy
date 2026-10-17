<?php

declare(strict_types=1);

namespace App\Modules\Live\Support;

/** A student's permission to read a broadcast: their attendance, the session, an expiry; signed with the app key. */
final class LiveToken
{
    public static function make(string $attendanceId, string $sessionId, int $expires): string
    {
        $payload = rtrim(strtr(base64_encode("{$attendanceId}|{$sessionId}|{$expires}"), '+/', '-_'), '=');

        return $payload.'.'.self::sign($payload);
    }

    /** @return array{attendance: string, session: string}|null */
    public static function read(?string $token): ?array
    {
        if ($token === null || ! str_contains($token, '.')) {
            return null;
        }
        [$payload, $signature] = explode('.', $token, 2);
        if (! hash_equals(self::sign($payload), $signature)) {
            return null;
        }
        $parts = explode('|', (string) base64_decode(strtr($payload, '-_', '+/'), true));
        if (count($parts) !== 3 || (int) $parts[2] < time()) {
            return null;
        }

        return ['attendance' => $parts[0], 'session' => $parts[1]];
    }

    private static function sign(string $payload): string
    {
        return substr(hash_hmac('sha256', $payload, 'live|'.config('app.key')), 0, 32);
    }
}
