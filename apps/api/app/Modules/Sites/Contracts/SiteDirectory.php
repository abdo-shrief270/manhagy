<?php

declare(strict_types=1);

namespace App\Modules\Sites\Contracts;

/**
 * Each account's student platform ({slug}.manhagy.online). Sites are arrays:
 * {tenant_id, slug, title, tagline, color, device_limit, registration_open, require_parent_phone, url}.
 */
interface SiteDirectory
{
    /** @return array<string, mixed>|null the site at this address (any case), null when unknown */
    public function bySlug(string $slug): ?array;

    /** @return array<string, mixed> the account's site, made with a default address the first time */
    public function forTenant(string $tenantId): array;
}
