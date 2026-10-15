<?php

use App\Modules\Assessments\AssessmentsServiceProvider;
use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'assessments',
    name: 'الامتحانات',
    tier: ModuleTier::Core,
    description: 'بنك الأسئلة والامتحانات بوقت على السيرفر وتصحيح تلقائي ونتايج.',
    permissions: [
        'assessments.view' => 'عرض نتايج الامتحانات',
        'assessments.manage' => 'بنك الأسئلة وإعداد الامتحانات',
        'assessments.grade' => 'تصحيح الأسئلة المقالية',
    ],
    menu: [
        new MenuItem('/assessments/questions', 'بنك الأسئلة', 'i-lucide-list-checks', 'assessments.manage', group: 'teaching'),
    ],
    provider: AssessmentsServiceProvider::class,
    sort: 40,
);
