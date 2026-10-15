<?php

declare(strict_types=1);

namespace App\Modules\Courses;

use App\Modules\Courses\Contracts\CourseCatalog;
use App\Modules\Courses\Support\Catalog;
use App\Support\Modules\ModuleServiceProvider;

final class CoursesServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CourseCatalog::class, Catalog::class);
    }
}
