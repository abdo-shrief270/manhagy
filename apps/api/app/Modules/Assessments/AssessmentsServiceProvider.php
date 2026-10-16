<?php

declare(strict_types=1);

namespace App\Modules\Assessments;

use App\Modules\Assessments\Console\CloseExpiredAttempts;
use App\Modules\Assessments\Contracts\ExamResults;
use App\Modules\Assessments\Support\Results;
use App\Support\Modules\ModuleServiceProvider;

final class AssessmentsServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ExamResults::class, Results::class);
    }

    public function boot(): void
    {
        parent::boot();
        if ($this->app->runningInConsole()) {
            $this->commands([CloseExpiredAttempts::class]);
        }
    }
}
