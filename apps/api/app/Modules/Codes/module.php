<?php

use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'codes',
    name: 'الأكواد',
    tier: ModuleTier::Core,
    description: 'دفعات أكواد لكورس أو رصيد، كروت للطباعة، والموزّعين وحساباتهم.',
    permissions: [
        'codes.view' => 'عرض الأكواد واستعلام عن كود',
        'codes.manage' => 'عمل دفعات أكواد وإلغاؤها والموزّعين',
    ],
    menu: [
        new MenuItem('/codes', 'الأكواد', 'i-lucide-ticket', 'codes.view', group: 'sales'),
        new MenuItem('/codes/distributors', 'الموزّعين', 'i-lucide-store', 'codes.manage', group: 'sales'),
    ],
    sort: 35,
);
