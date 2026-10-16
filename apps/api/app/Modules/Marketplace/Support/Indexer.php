<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Support;

use App\Modules\Access\Contracts\Enrollments;
use App\Modules\Billing\Contracts\SubscriptionStanding;
use App\Modules\Courses\Contracts\MarketCourses;
use App\Modules\Curriculum\Contracts\CurriculumTree;
use App\Modules\Marketplace\Models\CourseReview;
use App\Modules\Marketplace\Models\MarketSetting;
use App\Modules\Sites\Contracts\SiteDirectory;
use App\Support\Text\SearchText;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Keeps `market_courses` (the market's search table) in step with what each listed account has
 * published: rebuilt per account when its courses change (`market:sync`), when its settings are
 * saved, and every night for all (`market:reindex`: students, standing, unpaid commissions).
 */
final class Indexer
{
    private const MARK = 'market:sync:mark';

    public function __construct(
        private readonly MarketCourses $courses,
        private readonly CurriculumTree $tree,
        private readonly SiteDirectory $sites,
        private readonly Enrollments $enrollments,
        private readonly SubscriptionStanding $standing,
        private readonly Commissions $commissions,
    ) {}

    /** Why the account isn't in the market now (null = it is). */
    public function blocker(string $tenantId, ?MarketSetting $settings): ?string
    {
        return match (true) {
            $settings === null || ! $settings->listed => 'not_listed',
            ! $this->standing->listed($tenantId) => 'subscription',
            $this->commissions->overdue($tenantId) => 'unpaid',
            default => null,
        };
    }

    /** @return int how many courses the account has in the market now */
    public function syncTenant(string $tenantId): int
    {
        $settings = MarketSetting::withoutTenancy()->where('tenant_id', $tenantId)->first();
        if ($this->blocker($tenantId, $settings) !== null) {
            DB::table('market_courses')->where('tenant_id', $tenantId)->delete();

            return 0;
        }
        assert($settings instanceof MarketSetting);
        $site = $this->sites->forTenant($tenantId);
        $courses = array_values(array_filter($this->courses->forTenant($tenantId), fn (array $c) => ! in_array($c['id'], $settings->hidden_courses, true) && $c['lessons_count'] > 0));
        $ids = array_column($courses, 'id');
        $students = $this->enrollments->studentCounts($ids);
        $ratings = $this->ratings($ids);

        $rows = array_map(function (array $c) use ($site, $settings, $students, $ratings, $tenantId): array {
            $path = $this->tree->path($c['grade_node_id']);
            $label = implode(' › ', array_column($path, 'name'));

            return [
                'course_id' => $c['id'], 'tenant_id' => $tenantId,
                'site_slug' => $site['slug'], 'site_url' => $site['url'], 'teacher' => $site['title'], 'headline' => $settings->headline, 'color' => $site['color'],
                'title' => $c['title'], 'description' => $c['description'],
                'search_name' => SearchText::normalize("{$c['title']} {$label} {$site['title']}"),
                'price' => $c['price'], 'access_days' => $c['access_days'],
                'node_id' => $c['grade_node_id'], 'node_ids' => '{'.implode(',', array_column($path, 'id')).'}', 'node_label' => mb_substr($label, 0, 500),
                'root_kind' => $path[0]['kind'] ?? 'stage',
                'lessons_count' => $c['lessons_count'], 'free_previews' => $c['free_previews'],
                'students' => $students[$c['id']] ?? 0,
                'rating' => $ratings[$c['id']]['rating'] ?? null, 'reviews' => $ratings[$c['id']]['reviews'] ?? 0,
                'published_at' => $c['published_at'], 'indexed_at' => now(),
            ];
        }, $courses);

        DB::transaction(function () use ($tenantId, $rows): void {
            DB::table('market_courses')->where('tenant_id', $tenantId)->delete();
            if ($rows !== []) {
                DB::table('market_courses')->insert($rows);
            }
        });

        return count($rows);
    }

    /** After a review: the course's rating only. */
    public function refreshRating(string $courseId): void
    {
        $r = $this->ratings([$courseId])[$courseId] ?? ['rating' => null, 'reviews' => 0];
        DB::table('market_courses')->where('course_id', $courseId)->update($r);
    }

    /** @return int accounts rebuilt: those whose courses changed since the last run */
    public function syncChanged(): int
    {
        $since = now()->parse((string) Cache::get(self::MARK, now()->subDay()->toIso8601String()))->subSeconds(5);
        $started = now();
        $listed = MarketSetting::withoutTenancy()->where('listed', true)->pluck('tenant_id')->all();
        $tenants = array_values(array_intersect($this->courses->changedSince($since), $listed));
        foreach ($tenants as $tenant) {
            $this->syncTenant($tenant);
        }
        Cache::forever(self::MARK, $started->toIso8601String());

        return count($tenants);
    }

    /** @return int accounts rebuilt: every listed one, and the rows of any that left */
    public function rebuild(?string $tenantId = null): int
    {
        $tenants = $tenantId !== null ? [$tenantId] : array_values(array_unique([
            ...MarketSetting::withoutTenancy()->where('listed', true)->pluck('tenant_id')->all(),
            ...DB::table('market_courses')->distinct()->pluck('tenant_id')->all(),
        ]));
        foreach ($tenants as $tenant) {
            $this->syncTenant($tenant);
        }

        return count($tenants);
    }

    /**
     * @param  list<string>  $courseIds
     * @return array<string, array{rating: float, reviews: int}>
     */
    private function ratings(array $courseIds): array
    {
        if ($courseIds === []) {
            return [];
        }

        return CourseReview::withoutTenancy()->whereIn('course_id', $courseIds)->where('hidden', false)
            ->selectRaw('course_id, avg(rating) as rating, count(*) as reviews')->groupBy('course_id')->get()
            ->mapWithKeys(fn ($r) => [$r->course_id => ['rating' => round((float) $r->rating, 2), 'reviews' => (int) $r->reviews]])->all();
    }
}
