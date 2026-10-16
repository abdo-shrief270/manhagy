<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Http\Controllers;

use App\Modules\Courses\Contracts\CourseCatalog;
use App\Modules\Marketplace\Models\CourseReview;
use App\Modules\Marketplace\Models\MarketCommission;
use App\Modules\Marketplace\Models\MarketSetting;
use App\Modules\Marketplace\Support\Commissions;
use App\Modules\Marketplace\Support\Indexer;
use App\Support\Audit\Auditor;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** The account's side of the market: showing up there, which courses, the reviews, the commissions. */
final class MarketController
{
    public function __construct(
        private readonly Indexer $indexer,
        private readonly Commissions $commissions,
        private readonly CurrentTenant $tenant,
    ) {}

    public function show(CourseCatalog $courses): JsonResponse
    {
        return response()->json(['data' => $this->state($courses)]);
    }

    public function update(Request $request, CourseCatalog $courses, Auditor $auditor): JsonResponse
    {
        $published = array_column($courses->published(), 'id');
        $data = $request->validate([
            'listed' => ['required', 'boolean'],
            'headline' => ['nullable', 'string', 'max:120'],
            'about' => ['nullable', 'string', 'max:2000'],
            'hidden_courses' => ['array'],
            'hidden_courses.*' => ['uuid', Rule::in($published)],
        ], [], ['headline' => 'الجملة اللي تحت اسمك', 'about' => 'نبذة عنك']);

        $settings = MarketSetting::current();
        $was = $settings->listed;
        DB::transaction(function () use ($settings, $data, $was, $auditor): void {
            $settings->update([...$data, 'hidden_courses' => array_values(array_unique($data['hidden_courses'] ?? []))]);
            if ($was !== $settings->listed) {
                $auditor->record('marketplace.'.($settings->listed ? 'listed' : 'unlisted'), $settings->listed ? 'الكورسات بقت ظاهرة في سوق منهجي' : 'الكورسات اتشالت من سوق منهجي', $settings);
            }
        });
        $this->indexer->syncTenant($this->tenant->idOrFail());

        return response()->json(['data' => $this->state($courses)]);
    }

    public function reviews(Request $request): JsonResponse
    {
        $data = $request->validate(['course_id' => ['nullable', 'uuid']]);
        $reviews = CourseReview::query()->when($data['course_id'] ?? null, fn ($q, $id) => $q->where('course_id', $id))
            ->latest('updated_at')->paginate(30);

        return response()->json([
            'data' => collect($reviews->items())->map(fn (CourseReview $r) => [...$r->toPublic(), 'course_id' => $r->course_id, 'hidden' => $r->hidden])->all(),
            'meta' => ['total' => $reviews->total(), 'last_page' => $reviews->lastPage()],
        ]);
    }

    public function commissions(): JsonResponse
    {
        $rows = MarketCommission::query()->orderByDesc('seq')->paginate(50);

        return response()->json([
            'data' => collect($rows->items())->map(fn (MarketCommission $c) => $c->toSummary())->all(),
            'meta' => ['total' => $rows->total(), 'last_page' => $rows->lastPage(), 'summary' => $this->commissions->summary($this->tenant->idOrFail())],
        ]);
    }

    /** @return array<string, mixed> */
    private function state(CourseCatalog $courses): array
    {
        $tenantId = $this->tenant->idOrFail();
        $settings = MarketSetting::current();
        $listed = DB::table('market_courses')->where('tenant_id', $tenantId)->get()->keyBy('course_id');
        $market = (string) config('services.market.url');

        return [
            'listed' => $settings->listed,
            'headline' => $settings->headline,
            'about' => $settings->about,
            'hidden_courses' => $settings->hidden_courses,
            // null = in the market; not_listed / subscription / unpaid = why not
            'blocker' => $this->indexer->blocker($tenantId, $settings),
            'market_url' => $market === '' ? null : $market,
            'courses' => array_map(fn (array $c) => [
                'id' => $c['id'], 'title' => $c['title'], 'price' => $c['price'],
                'hidden' => in_array($c['id'], $settings->hidden_courses, true),
                'in_market' => isset($listed[$c['id']]),
                'students' => (int) ($listed[$c['id']]->students ?? 0),
                'rating' => isset($listed[$c['id']]) && $listed[$c['id']]->rating !== null ? (float) $listed[$c['id']]->rating : null,
                'reviews' => (int) ($listed[$c['id']]->reviews ?? 0),
                'url' => $market === '' || ! isset($listed[$c['id']]) ? null : "{$market}/courses/{$c['id']}",
            ], $courses->published()),
            'commission' => $this->commissions->summary($tenantId),
        ];
    }
}
