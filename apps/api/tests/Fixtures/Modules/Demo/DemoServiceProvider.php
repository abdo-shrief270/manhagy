<?php

namespace Tests\Fixtures\Modules\Demo;

use App\Modules\ModuleManager\Events\ModuleEnabled;
use App\Support\Modules\ModuleServiceProvider;

final class DemoServiceProvider extends ModuleServiceProvider
{
    protected array $listen = [
        ModuleEnabled::class => [SeedDemoNotes::class],
    ];
}
