<?php

declare(strict_types=1);

namespace App\Modules\Curriculum;

use App\Modules\Curriculum\Contracts\CurriculumAdmin;
use App\Modules\Curriculum\Contracts\CurriculumTree;
use App\Modules\Curriculum\Support\Tree;
use App\Support\Modules\ModuleServiceProvider;

final class CurriculumServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Tree::class);
        $this->app->bind(CurriculumTree::class, Tree::class);
        $this->app->bind(CurriculumAdmin::class, Tree::class);
    }
}
