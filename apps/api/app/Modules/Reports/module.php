<?php

use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'reports',
    name: 'لوحة المدرس والتقارير',
    tier: ModuleTier::Core,
    description: 'إيرادات النهارده والشهر، والطلاب، والمشاهدة، والامتحانات، وتنبيهات المشاركة.',
    permissions: [
        'reports.view' => 'عرض لوحة المدرس',
        'reports.money' => 'عرض الإيرادات',
    ],
    sort: 50,
);
