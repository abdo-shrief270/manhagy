<?php

use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

// Test-only optional module for every account kind, no automatic trial.
return new ModuleManifest(
    key: 'extra',
    name: 'إضافة',
    tier: ModuleTier::Optional,
    permissions: ['extra.manage' => 'إدارة الإضافة'],
    menu: [new MenuItem('/extra', 'إضافة', 'i-lucide-plus', 'extra.manage', group: 'settings')],
    sort: 901,
);
