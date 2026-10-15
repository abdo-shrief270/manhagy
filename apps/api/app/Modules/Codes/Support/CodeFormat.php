<?php

declare(strict_types=1);

namespace App\Modules\Codes\Support;

/**
 * XXXX-XXXX-XXXX from 31 characters that can't be mistaken for each other (no 0/O, 1/I/L), the
 * last one a Luhn mod 31 check character: a typo is caught before the database is asked.
 * Stored only as an HMAC (keyed by the app key and the account).
 */
final class CodeFormat
{
    public const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public const LENGTH = 12;

    public static function generate(): string
    {
        $body = '';
        for ($i = 0; $i < self::LENGTH - 1; $i++) {
            $body .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $body.self::checkChar($body);
    }

    /** Upper case, without spaces / dashes / dots. */
    public static function normalize(string $input): string
    {
        return strtoupper((string) preg_replace('/[\s\-_.]+/u', '', $input));
    }

    public static function valid(string $code): bool
    {
        if (strlen($code) !== self::LENGTH || strspn($code, self::ALPHABET) !== self::LENGTH) {
            return false;
        }

        return self::checkChar(substr($code, 0, -1)) === $code[self::LENGTH - 1];
    }

    public static function pretty(string $code): string
    {
        return implode('-', str_split($code, 4));
    }

    public static function hash(string $tenantId, string $code): string
    {
        return hash_hmac('sha256', "{$tenantId}|{$code}", (string) config('app.key'));
    }

    private static function checkChar(string $body): string
    {
        $n = strlen(self::ALPHABET);
        $factor = 2;
        $sum = 0;
        for ($i = strlen($body) - 1; $i >= 0; $i--) {
            $addend = $factor * strpos(self::ALPHABET, $body[$i]);
            $factor = $factor === 2 ? 1 : 2;
            $sum += intdiv($addend, $n) + $addend % $n;
        }

        return self::ALPHABET[($n - $sum % $n) % $n];
    }
}
