<?php

declare(strict_types=1);

namespace App\Modules\Courses;

use App\Modules\Courses\Contracts\CourseCatalog;
use App\Modules\Courses\Contracts\MarketCourses;
use App\Modules\Courses\Support\Catalog;
use App\Modules\Courses\Support\MarketFeed;
use App\Support\Modules\ModuleServiceProvider;

final class CoursesServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CourseCatalog::class, Catalog::class);
        $this->app->bind(MarketCourses::class, MarketFeed::class);
    }
}
