<?php

declare(strict_types=1);

namespace App\Modules\Center\Support;

/**
 * The student's card QR: their id with a signature (account + student), so a card can't be made
 * from someone's id. Short enough for a small QR.
 */
final class Cards
{
    public static function token(string $tenantId, string $learnerId): string
    {
        return str_replace('-', '', $learnerId).'.'.self::sign($tenantId, $learnerId);
    }

    /** The student's id, or null for a card that isn't this account's. */
    public static function read(string $tenantId, string $token): ?string
    {
        $token = trim($token);
        if (preg_match('/^([0-9a-f]{32})\.([A-Za-z0-9_-]{16})$/', $token, $m) !== 1) {
            return null;
        }
        $id = vsprintf('%s-%s-%s-%s-%s', [substr($m[1], 0, 8), substr($m[1], 8, 4), substr($m[1], 12, 4), substr($m[1], 16, 4), substr($m[1], 20)]);

        return hash_equals(self::sign($tenantId, $id), $m[2]) ? $id : null;
    }

    private static function sign(string $tenantId, string $learnerId): string
    {
        return substr(rtrim(strtr(base64_encode(hash_hmac('sha256', "card|{$tenantId}|{$learnerId}", (string) config('app.key'), true)), '+/', '-_'), '='), 0, 16);
    }
}
