<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Support;

use App\Modules\Marketplace\Contracts\MarketAdmin;
use App\Modules\Marketplace\Models\CourseReview;
use App\Modules\Marketplace\Models\MarketCommission;
use Illuminate\Support\Facades\DB;

final class Admin implements MarketAdmin
{
    public function __construct(private readonly Commissions $commissions, private readonly Indexer $indexer) {}

    public function owing(): array
    {
        $days = (int) config('services.market.pay_within_days', 30);

        return MarketCommission::withoutTenancy()->due()
            ->selectRaw('tenant_id, sum(commission) as due, count(*) as due_count, min(created_at) as oldest')
            ->groupBy('tenant_id')->orderBy('oldest')->get()
            ->map(fn ($r) => [
                'tenant_id' => $r->tenant_id, 'due' => (int) $r->due, 'due_count' => (int) $r->due_count,
                'oldest_due_at' => now()->parse($r->oldest)->toIso8601String(),
                'overdue' => now()->parse($r->oldest)->lt(now()->subDays($days)),
            ])->all();
    }

    public function commissions(string $tenantId): array
    {
        return [
            'summary' => $this->commissions->summary($tenantId),
            'rows' => MarketCommission::withoutTenancy()->where('tenant_id', $tenantId)->orderByDesc('seq')->limit(200)->get()
                ->map(fn (MarketCommission $c) => $c->toSummary())->all(),
        ];
    }

    public function settle(string $tenantId, string $reference): array
    {
        $result = $this->commissions->settle($tenantId, $reference);
        $this->indexer->syncTenant($tenantId); // back in the market if it was out for being unpaid

        return $result;
    }

    public function void(string $commissionId, string $reason): array
    {
        $row = $this->commissions->void($commissionId, $reason);
        $this->indexer->syncTenant($row->tenant_id);

        return [...$row->toSummary(), 'tenant_id' => $row->tenant_id];
    }

    public function reviews(?bool $hidden, ?int $maxRating, int $page): array
    {
        $query = CourseReview::withoutTenancy()->when($hidden !== null, fn ($q) => $q->where('hidden', $hidden))
            ->when($maxRating !== null, fn ($q) => $q->where('rating', '<=', $maxRating));
        $total = (clone $query)->count();
        $rows = $query->latest('updated_at')->offset(($page - 1) * 50)->limit(50)->get();
        $titles = DB::table('market_courses')->whereIn('course_id', $rows->pluck('course_id'))->get(['course_id', 'title', 'teacher'])->keyBy('course_id');

        return ['total' => $total, 'data' => $rows->map(fn (CourseReview $r) => [
            ...$r->toPublic(), 'hidden' => $r->hidden, 'tenant_id' => $r->tenant_id, 'course_id' => $r->course_id,
            'course' => $titles[$r->course_id]->title ?? null, 'teacher' => $titles[$r->course_id]->teacher ?? null,
        ])->all()];
    }

    public function setReviewHidden(string $reviewId, bool $hidden): ?array
    {
        $review = CourseReview::withoutTenancy()->find($reviewId);
        if ($review === null) {
            return null;
        }
        $review->update(['hidden' => $hidden]);
        $this->indexer->refreshRating($review->course_id);

        return [...$review->toPublic(), 'hidden' => $review->hidden, 'course_id' => $review->course_id];
    }
}
