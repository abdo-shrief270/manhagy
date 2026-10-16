<?php

use App\Modules\Marketplace\MarketplaceServiceProvider;
use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'marketplace',
    name: 'سوق منهجي',
    tier: ModuleTier::Core,
    description: 'كورساتك في السوق المركزي: الطلاب يدوّروا ويقارنوا ويقيّموا، ويشتركوا من منصتك.',
    permissions: [
        'marketplace.manage' => 'الظهور في السوق والتقييمات',
    ],
    menu: [
        new MenuItem('/marketplace', 'سوق منهجي', 'i-lucide-store', 'marketplace.manage', group: 'sales'),
    ],
    provider: MarketplaceServiceProvider::class,
    sort: 60,
);
