<?php

declare(strict_types=1);

namespace App\Modules\Center;

use App\Modules\Center\Console\ScheduleSessions;
use App\Support\Modules\ModuleServiceProvider;

final class CenterServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();
        if ($this->app->runningInConsole()) {
            $this->commands([ScheduleSessions::class]);
        }
    }
}
