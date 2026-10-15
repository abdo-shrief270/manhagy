<?php

use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

// Test-only optional module for centers only, on trial for them at sign-up.
return new ModuleManifest(
    key: 'center_tools',
    name: 'أدوات السنتر',
    tier: ModuleTier::Optional,
    permissions: ['center_tools.manage' => 'إدارة أدوات السنتر'],
    shopTypes: ['center'],
    trialFor: ['center'],
    sort: 902,
);
