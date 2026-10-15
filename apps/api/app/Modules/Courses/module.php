<?php

use App\Modules\Courses\CoursesServiceProvider;
use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'courses',
    name: 'الكورسات',
    tier: ModuleTier::Core,
    description: 'الكورسات وفصولها ودروسها (فيديو، ملزمة، امتحان) ومواعيد فتحها.',
    permissions: [
        'courses.view' => 'عرض الكورسات',
        'courses.manage' => 'عمل الكورسات وتعديلها ونشرها',
    ],
    menu: [
        new MenuItem('/courses', 'الكورسات', 'i-lucide-book-open-text', 'courses.view', group: 'teaching'),
    ],
    provider: CoursesServiceProvider::class,
    sort: 20,
);
