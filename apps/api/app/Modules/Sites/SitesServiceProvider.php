<?php

declare(strict_types=1);

namespace App\Modules\Sites;

use App\Modules\Sites\Contracts\SiteDirectory;
use App\Modules\Sites\Support\Directory;
use App\Support\Modules\ModuleServiceProvider;

final class SitesServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SiteDirectory::class, Directory::class);
    }
}
