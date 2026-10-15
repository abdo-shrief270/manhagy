<?php

use App\Modules\Curriculum\CurriculumServiceProvider;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'curriculum',
    name: 'شجرة المناهج',
    tier: ModuleTier::Core,
    description: 'المراحل والصفوف والمواد والجامعات والكليات والمقررات، مشتركة لكل المنصة.',
    permissions: [
        'curriculum.suggest' => 'اقتراح مادة أو مقرر مش موجود في الشجرة',
    ],
    provider: CurriculumServiceProvider::class,
    sort: 5,
);
