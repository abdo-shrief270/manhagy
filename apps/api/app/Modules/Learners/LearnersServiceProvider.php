<?php

declare(strict_types=1);

namespace App\Modules\Learners;

use App\Modules\Learners\Contracts\LearnerDirectory;
use App\Modules\Learners\Http\Middleware\AuthenticateLearner;
use App\Modules\Learners\Support\Directory;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Routing\Router;

final class LearnersServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LearnerDirectory::class, Directory::class);
    }

    public function boot(): void
    {
        parent::boot();
        $this->app->make(Router::class)->aliasMiddleware('learner', AuthenticateLearner::class);
    }
}
