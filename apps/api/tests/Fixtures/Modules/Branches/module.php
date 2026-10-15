<?php

use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

// Test-only optional module (an extra bought on top of a plan in the billing tests).
return new ModuleManifest(
    key: 'branches',
    name: 'فروع',
    tier: ModuleTier::Optional,
    permissions: ['branches.extra' => 'فروع إضافية'],
    sort: 910,
);
