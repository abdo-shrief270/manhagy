<?php

declare(strict_types=1);

namespace App\Modules\Wallet;

use App\Modules\Wallet\Contracts\LearnerWallets;
use App\Modules\Wallet\Support\Ledger;
use App\Support\Modules\ModuleServiceProvider;

final class WalletServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LearnerWallets::class, Ledger::class);
    }
}
