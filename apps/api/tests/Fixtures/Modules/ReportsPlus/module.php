<?php

use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

// Test-only optional module on trial for teachers at sign-up, with one route.
return new ModuleManifest(
    key: 'reports_plus',
    name: 'تقارير إضافية',
    tier: ModuleTier::Optional,
    permissions: ['reports_plus.view' => 'عرض التقارير الإضافية'],
    trialFor: ['teacher'],
    sort: 903,
);
