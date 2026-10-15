<?php

use App\Support\Modules\Feature;
use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;
use Tests\Fixtures\Modules\Demo\DemoServiceProvider;

// Test-only optional module (loaded in the testing environment): stands in for a real optional
// module so trials, plans, permissions, routes and feature switches stay covered.
return new ModuleManifest(
    key: 'demo',
    name: 'تجربة',
    tier: ModuleTier::Optional,
    description: 'قسم للتيستات بس.',
    permissions: [
        'demo.view' => 'عرض التجربة',
        'demo.manage' => 'إدارة التجربة',
    ],
    menu: [new MenuItem('/demo', 'تجربة', 'i-lucide-flask-conical', 'demo.view', group: 'settings')],
    provider: DemoServiceProvider::class,
    shopTypes: ['teacher', 'center'],
    trialFor: ['teacher'],
    features: [
        new Feature('demo.public', 'التجربة ظاهرة', 'سويتش للتيستات.', true),
        new Feature('demo.strict', 'التجربة بقيود', 'سويتش للتيستات، مقفول افتراضياً.', false),
        new Feature('demo.third', 'سويتش تالت', 'للتيستات.', true),
        new Feature('demo.fourth', 'سويتش رابع', 'للتيستات.', true),
    ],
    sort: 900,
);
