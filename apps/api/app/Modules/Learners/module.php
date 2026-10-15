<?php

use App\Modules\Learners\LearnersServiceProvider;
use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'learners',
    name: 'الطلاب',
    tier: ModuleTier::Core,
    description: 'الطلاب اللي سجّلوا على منصتك وأجهزتهم.',
    permissions: [
        'learners.view' => 'عرض الطلاب',
        'learners.manage' => 'إيقاف طالب وتعديل بياناته',
        'learners.devices' => 'فك أجهزة الطلاب',
    ],
    menu: [
        new MenuItem('/learners', 'الطلاب', 'i-lucide-users-round', 'learners.view', group: 'learners'),
    ],
    provider: LearnersServiceProvider::class,
    sort: 10,
);
