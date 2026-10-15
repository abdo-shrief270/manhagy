<?php

use App\Modules\Wallet\WalletServiceProvider;
use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'wallet',
    name: 'محفظة الطالب',
    tier: ModuleTier::Core,
    description: 'رصيد الطالب على منصتك: يشحنه بكود أو بتحويل بإثبات، ويشتري بيه الكورسات.',
    permissions: [
        'wallet.view' => 'عرض محافظ الطلاب',
        'wallet.confirm' => 'تأكيد أو رفض تحويلات الشحن',
        'wallet.adjust' => 'تعديل رصيد طالب بالإيد',
        'wallet.settings' => 'أرقام التحويل وطريقة الشحن',
    ],
    menu: [
        new MenuItem('/wallet/topups', 'تحويلات الشحن', 'i-lucide-wallet', 'wallet.confirm', group: 'sales'),
    ],
    provider: WalletServiceProvider::class,
    sort: 30,
);
