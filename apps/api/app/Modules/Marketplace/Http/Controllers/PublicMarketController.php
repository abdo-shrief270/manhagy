<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Http\Controllers;

use App\Modules\Courses\Contracts\MarketCourses;
use App\Modules\Marketplace\Models\CourseReview;
use App\Modules\Marketplace\Models\MarketSetting;
use App\Modules\Marketplace\Support\Search;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** «سوق منهجي» before signing in: search, a course, a teacher, browsing the tree. */
final class PublicMarketController
{
    public function __construct(private readonly Search $search) {}

    public function home(): JsonResponse
    {
        $top = DB::table('market_courses')->where('reviews', '>=', 3)->orderByDesc('rating')->orderByDesc('reviews')->limit(8)->get();
        $new = DB::table('market_courses')->orderByRaw('published_at DESC NULLS LAST')->limit(8)->get();
        $popular = DB::table('market_courses')->orderByDesc('students')->limit(8)->get();

        return $this->cached([
            'kinds' => $this->search->kindCounts(),
            'courses' => (int) DB::table('market_courses')->count(),
            'teachers' => (int) DB::table('market_courses')->distinct()->count('tenant_id'),
            'top_rated' => $top->map(fn ($r) => Search::card($r))->all(),
            'newest' => $new->map(fn ($r) => Search::card($r))->all(),
            'popular' => $popular->map(fn ($r) => Search::card($r))->all(),
        ]);
    }

    public function courses(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'node' => ['nullable', 'uuid'],
            'kind' => ['nullable', Rule::in(array_keys(Search::KINDS))],
            'price' => ['nullable', Rule::in(['free', 'paid'])],
            'min' => ['nullable', 'integer', 'min:0'],
            'max' => ['nullable', 'integer', 'min:0'],
            'teacher' => ['nullable', 'string', 'max:30'],
            'sort' => ['nullable', Rule::in(Search::SORTS)],
            'page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);
        $result = $this->search->courses($filters);

        return response()->json($result)->header('Cache-Control', 'public, max-age=30');
    }

    public function course(string $course, MarketCourses $courses): JsonResponse
    {
        $row = $this->search->find($course) ?? abort(404);
        $reviews = CourseReview::withoutTenancy()->where('course_id', $row->course_id)->where('hidden', false);
        $distribution = (clone $reviews)->selectRaw('rating, count(*) as n')->groupBy('rating')->pluck('n', 'rating');

        return $this->cached([
            ...Search::card($row),
            'description' => $row->description,
            'syllabus' => $courses->syllabus($row->tenant_id, $row->course_id),
            'reviews_list' => (clone $reviews)->whereNotNull('comment')->latest('updated_at')->limit(20)->get()->map(fn (CourseReview $r) => $r->toPublic())->all(),
            'distribution' => array_map(fn (int $star) => (int) ($distribution[$star] ?? 0), [5 => 5, 4 => 4, 3 => 3, 2 => 2, 1 => 1]),
            'more_from_teacher' => $this->search->byTeacher($row->site_slug, $row->course_id, 6),
        ]);
    }

    public function teacher(string $slug): JsonResponse
    {
        $courses = $this->search->byTeacher(strtolower($slug));
        abort_if($courses === [], 404);
        $tenant = DB::table('market_courses')->where('site_slug', strtolower($slug))->value('tenant_id');
        $settings = MarketSetting::withoutTenancy()->where('tenant_id', $tenant)->first();
        $rated = array_filter($courses, fn (array $c) => $c['reviews'] > 0);
        $reviews = array_sum(array_column($rated, 'reviews'));

        return $this->cached([
            ...$courses[0]['teacher'],
            'about' => $settings?->about,
            'site_url' => (string) DB::table('market_courses')->where('tenant_id', $tenant)->value('site_url'),
            'courses_count' => count($courses),
            'students' => array_sum(array_column($courses, 'students')),
            'reviews' => $reviews,
            'rating' => $reviews === 0 ? null : round(array_sum(array_map(fn (array $c) => $c['rating'] * $c['reviews'], $rated)) / $reviews, 2),
            'courses' => $courses,
        ]);
    }

    public function nodes(Request $request): JsonResponse
    {
        $data = $request->validate(['parent' => ['nullable', 'uuid']]);

        return $this->cached($this->search->nodes($data['parent'] ?? null));
    }

    /** @param  array<mixed>  $data */
    private function cached(array $data): JsonResponse
    {
        return response()->json(['data' => $data])->header('Cache-Control', 'public, max-age=60');
    }
}
