<?php

use App\Modules\Center\CenterServiceProvider;
use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'center',
    name: 'السنتر',
    tier: ModuleTier::Optional,
    description: 'مجموعات ومواعيد، حضور بكارنيه QR حتى من غير نت، تحصيل الحصة أو الشهر على الباب، درج لكل مساعد، ونصيب كل مدرس.',
    permissions: [
        'center.door' => 'الباب: تسجيل الحضور والتحصيل',
        'center.manage' => 'المجموعات والمدرسين والمواعيد',
        'center.cash' => 'استلام الدرج ونصيب المدرسين',
    ],
    menu: [
        new MenuItem('/center/door', 'الباب (الحضور)', 'i-lucide-scan-qr-code', 'center.door', group: 'center'),
        new MenuItem('/center/groups', 'المجموعات', 'i-lucide-users', 'center.manage', group: 'center'),
        new MenuItem('/center/teachers', 'المدرسين ونصيبهم', 'i-lucide-presentation', 'center.cash', group: 'center'),
        new MenuItem('/center/cash', 'الدرج', 'i-lucide-banknote', 'center.door', group: 'center'),
    ],
    provider: CenterServiceProvider::class,
    sort: 60,
    trialFor: ['center'],
);
