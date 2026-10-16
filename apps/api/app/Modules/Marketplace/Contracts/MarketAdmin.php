<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Contracts;

/** For the platform admins (apps/admin «سوق منهجي»): moderating reviews. */
interface MarketAdmin
{
    /** @return array{data: list<array<string, mixed>>, total: int} newest first */
    public function reviews(?bool $hidden, ?int $maxRating, int $page): array;

    /** @return array<string, mixed>|null */
    public function setReviewHidden(string $reviewId, bool $hidden): ?array;
}
