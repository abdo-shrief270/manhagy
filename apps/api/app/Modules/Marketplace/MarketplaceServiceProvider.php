<?php

declare(strict_types=1);

namespace App\Modules\Marketplace;

use App\Modules\Marketplace\Console\SyncMarket;
use App\Modules\Marketplace\Contracts\MarketAdmin;
use App\Modules\Marketplace\Support\Admin;
use App\Support\Modules\ModuleServiceProvider;

final class MarketplaceServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MarketAdmin::class, Admin::class);
    }

    public function boot(): void
    {
        parent::boot();
        if ($this->app->runningInConsole()) {
            $this->commands([SyncMarket::class]);
        }
    }
}
