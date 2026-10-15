<?php

declare(strict_types=1);

namespace App\Modules\Sites\Support;

/** A site's address: lowercase latin letters, digits and dashes, 3–30, not a platform name. */
final class Slugs
{
    public const RESERVED = [
        'www', 'app', 'admin', 'api', 'mail', 'email', 'smtp', 'ftp', 'learn', 'market', 'souq', 'souk', 'store', 'shop',
        'static', 'cdn', 'media', 'assets', 'help', 'support', 'blog', 'docs', 'site', 'status', 'manhagy', 'manhaji', 'test', 'demo',
    ];

    public const PATTERN = '/^[a-z0-9](?:[a-z0-9-]{1,28})[a-z0-9]$/';

    public static function normalize(string $slug): string
    {
        return strtolower(trim($slug));
    }

    public static function valid(string $slug): bool
    {
        return preg_match(self::PATTERN, $slug) === 1 && ! str_contains($slug, '--') && ! in_array($slug, self::RESERVED, true);
    }
}
