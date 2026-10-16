<?php

use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'engagement',
    name: 'أسئلة الطلاب',
    tier: ModuleTier::Core,
    description: 'الطالب يسأل تحت الدرس، والمدرس أو المساعد يرد.',
    permissions: [
        'engagement.answer' => 'الرد على أسئلة الطلاب',
    ],
    menu: [
        new MenuItem('/questions', 'أسئلة الطلاب', 'i-lucide-message-circle-question', 'engagement.answer', group: 'learners'),
    ],
    sort: 45,
);
