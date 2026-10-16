<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Events;

use App\Support\Events\DomainEvent;

/** A student bought a course from their wallet. `via` = where they came from (market | null). */
final class CoursePurchased extends DomainEvent
{
    public const NAME = 'wallet.course_purchased';

    public function __construct(
        public readonly string $tenantId,
        public readonly string $learnerId,
        public readonly string $courseId,
        public readonly int $amount,
        public readonly string $transactionId,
        public readonly ?string $via = null,
    ) {}

    public function tenantId(): string
    {
        return $this->tenantId;
    }
}
