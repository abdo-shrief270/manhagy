<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Console;

use App\Modules\Marketplace\Support\Indexer;
use Illuminate\Console\Command;

final class SyncMarket extends Command
{
    protected $signature = 'market:sync {--all : rebuild every listed account} {--tenant= : one account}';

    protected $description = 'Bring the market\'s search table up to date with what the accounts published';

    public function handle(Indexer $indexer): int
    {
        $count = $this->option('all') || $this->option('tenant')
            ? $indexer->rebuild($this->option('tenant') ?: null)
            : $indexer->syncChanged();
        $this->info("Accounts rebuilt: {$count}");

        return self::SUCCESS;
    }
}
