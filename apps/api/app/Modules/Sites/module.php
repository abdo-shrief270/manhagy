<?php

use App\Modules\Sites\SitesServiceProvider;
use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'sites',
    name: 'منصتك',
    tier: ModuleTier::Core,
    description: 'لينك المنصة باسمك وشكلها وقواعد دخول الطلاب.',
    permissions: [
        'site.manage' => 'تعديل المنصة (اللينك والشكل وعدد الأجهزة)',
    ],
    menu: [
        new MenuItem('/settings/site', 'منصتك', 'i-lucide-globe', 'site.manage', group: 'settings'),
    ],
    provider: SitesServiceProvider::class,
    sort: 6,
);
