<?php

use App\Modules\Live\LiveServiceProvider;
use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'live',
    name: 'الحصص اللايف',
    tier: ModuleTier::Core,
    description: 'بث مباشر لطلاب الكورس من المتصفح أو OBS، بشات وأسئلة واستفتاءات وحضور، والتسجيل بيبقى درس في الكورس.',
    permissions: [
        'live.host' => 'تشغيل البث اللايف',
        'live.moderate' => 'إدارة الشات والأسئلة في اللايف',
    ],
    menu: [
        new MenuItem('/live', 'الحصص اللايف', 'i-lucide-radio', 'live.moderate', group: 'teaching'),
    ],
    provider: LiveServiceProvider::class,
    sort: 25,
);
