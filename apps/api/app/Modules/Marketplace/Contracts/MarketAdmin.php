<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Contracts;

/** For the platform admins (apps/admin «سوق منهجي»): commissions to collect, reviews to moderate. */
interface MarketAdmin
{
    /** @return list<array{tenant_id: string, due: int, due_count: int, oldest_due_at: string|null, overdue: bool}> accounts that owe, most overdue first */
    public function owing(): array;

    /** @return array{summary: array<string, mixed>, rows: list<array<string, mixed>>} */
    public function commissions(string $tenantId): array;

    /** @return array{count: int, amount: int} */
    public function settle(string $tenantId, string $reference): array;

    /** @return array<string, mixed> */
    public function void(string $commissionId, string $reason): array;

    /** @return array{data: list<array<string, mixed>>, total: int} newest first */
    public function reviews(?bool $hidden, ?int $maxRating, int $page): array;

    /** @return array<string, mixed>|null */
    public function setReviewHidden(string $reviewId, bool $hidden): ?array;
}
