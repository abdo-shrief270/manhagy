<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Support;

use App\Modules\Marketplace\Contracts\MarketAdmin;
use App\Modules\Marketplace\Models\CourseReview;
use Illuminate\Support\Facades\DB;

final class Admin implements MarketAdmin
{
    public function __construct(private readonly Indexer $indexer) {}

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
