<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Listeners;

use App\Modules\Marketplace\Support\Commissions;
use App\Modules\Wallet\Events\CoursePurchased;
use App\Support\Events\DomainEvent;
use App\Support\Events\ModuleListener;

/** A course bought by a student who came from the market: the platform's commission. */
final class RecordMarketSale extends ModuleListener
{
    public function __construct(private readonly Commissions $commissions) {}

    protected function module(): string
    {
        return 'marketplace';
    }

    protected function react(DomainEvent $event): void
    {
        assert($event instanceof CoursePurchased);
        $this->commissions->record($event->tenantId, $event->learnerId, $event->courseId, $event->amount, $event->transactionId, $event->via);
    }
}
