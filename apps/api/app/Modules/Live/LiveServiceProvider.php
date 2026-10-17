<?php

declare(strict_types=1);

namespace App\Modules\Live;

use App\Modules\Live\Console\SyncLive;
use App\Support\Modules\ModuleServiceProvider;

final class LiveServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();
        if ($this->app->runningInConsole()) {
            $this->commands([SyncLive::class]);
        }
    }
}
