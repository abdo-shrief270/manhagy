<?php

declare(strict_types=1);

namespace App\Modules\Sites\Support;

use App\Modules\Identity\Contracts\ShopDirectory;
use App\Modules\Sites\Contracts\SiteDirectory;
use App\Modules\Sites\Models\Site;
use App\Support\Tenancy\CurrentTenant;

final class Directory implements SiteDirectory
{
    public const COLORS = ['indigo', 'rose', 'teal', 'amber', 'sky', 'violet', 'emerald', 'slate'];

    public function __construct(
        private readonly CurrentTenant $tenant,
        private readonly ShopDirectory $accounts,
    ) {}

    public function bySlug(string $slug): ?array
    {
        return Site::withoutTenancy()->where('slug', Slugs::normalize($slug))->first()?->toSummary();
    }

    public function forTenant(string $tenantId): array
    {
        return $this->model($tenantId)->toSummary();
    }

    public function model(string $tenantId): Site
    {
        $site = Site::withoutTenancy()->where('tenant_id', $tenantId)->first();
        if ($site !== null) {
            return $site;
        }
        $account = $this->accounts->find($tenantId);

        return $this->tenant->runAs($tenantId, fn () => Site::query()->firstOrCreate(['tenant_id' => $tenantId], [
            'slug' => 't-'.strtolower($account?->code ?? substr($tenantId, -8)),
            'title' => $account?->name ?? 'منصتي',
            'color' => 'indigo',
            'device_limit' => 2,
            'registration_open' => true,
            'require_parent_phone' => false,
        ]));
    }
}
