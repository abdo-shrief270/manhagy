<?php

use App\Modules\Access\AccessServiceProvider;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'access',
    name: 'الاشتراكات والمشاهدة',
    tier: ModuleTier::Core,
    description: 'مين مشترك في إيه، وعدد المشاهدات، وجلسة مشاهدة واحدة لكل طالب.',
    permissions: [
        'access.enroll' => 'اشتراك طالب في كورس بإيدك أو إلغاؤه ومشاهدات زيادة',
    ],
    provider: AccessServiceProvider::class,
    sort: 25,
);
