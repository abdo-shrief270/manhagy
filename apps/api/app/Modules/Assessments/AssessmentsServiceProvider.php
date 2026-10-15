<?php

declare(strict_types=1);

namespace App\Modules\Assessments;

use App\Modules\Assessments\Console\CloseExpiredAttempts;
use App\Support\Modules\ModuleServiceProvider;

final class AssessmentsServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();
        if ($this->app->runningInConsole()) {
            $this->commands([CloseExpiredAttempts::class]);
        }
    }
}
