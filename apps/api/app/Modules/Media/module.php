<?php

use App\Modules\Media\MediaServiceProvider;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'media',
    name: 'الفيديوهات والملفات',
    tier: ModuleTier::Core,
    description: 'رفع الفيديوهات لمزوّد الفيديو والملازم PDF.',
    permissions: [
        'media.upload' => 'رفع الفيديوهات والملفات',
    ],
    provider: MediaServiceProvider::class,
    sort: 15,
);
