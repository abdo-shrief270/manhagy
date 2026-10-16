<?php

declare(strict_types=1);

namespace App\Modules\Access;

use App\Modules\Access\Contracts\Enrollments;
use App\Modules\Access\Contracts\LessonAccess;
use App\Modules\Access\Support\EnrollmentBook;
use App\Modules\Access\Support\LessonGate;
use App\Modules\Access\Support\SessionGuard;
use App\Modules\Media\Contracts\PlaybackGuard;
use App\Support\Modules\ModuleServiceProvider;

final class AccessServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Enrollments::class, EnrollmentBook::class);
        $this->app->bind(LessonAccess::class, LessonGate::class);
        $this->app->bind(PlaybackGuard::class, SessionGuard::class);
    }
}
