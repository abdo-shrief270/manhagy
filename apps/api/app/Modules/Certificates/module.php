<?php

use App\Modules\Certificates\CertificatesServiceProvider;
use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'certificates',
    name: 'الشهادات',
    tier: ModuleTier::Core,
    description: 'شهادة إتمام للطالب اللي خلّص الكورس (ونجح في امتحاناته لو عايز)، بكود تحقق ولينك عام.',
    permissions: [
        'certificates.manage' => 'إعداد الشهادات وإلغاؤها',
    ],
    menu: [
        new MenuItem('/certificates', 'الشهادات', 'i-lucide-award', 'certificates.manage', group: 'learners'),
    ],
    provider: CertificatesServiceProvider::class,
    sort: 45,
);
