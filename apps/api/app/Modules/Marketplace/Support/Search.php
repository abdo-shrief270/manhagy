<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Support;

use App\Modules\Curriculum\Contracts\CurriculumTree;
use App\Support\Text\SearchText;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Reading the market's search table (Postgres + pg_trgm; Arabic folded by SearchText). */
final class Search
{
    public const SORTS = ['relevance', 'rating', 'students', 'new', 'price_asc', 'price_desc'];

    /** What the student picks («مدارس» / «جامعة» / «مهارات») → the root of the tree. */
    public const KINDS = ['school' => 'stage', 'university' => 'university', 'skills' => 'field'];

    public const PER_PAGE = 24;

    public function __construct(private readonly CurriculumTree $tree) {}

    /**
     * @param  array<string, mixed>  $f  q, node, kind, price (free|paid), min, max, teacher, sort, page
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function courses(array $f): array
    {
        $tokens = SearchText::tokens((string) ($f['q'] ?? ''));
        $query = DB::table('market_courses');
        foreach ($tokens as $token) {
            $query->where('search_name', 'like', '%'.addcslashes($token, '%_\\').'%');
        }
        if (! empty($f['node'])) {
            $query->whereRaw('node_ids @> ARRAY[?]::uuid[]', [$f['node']]);
        }
        if (! empty($f['kind'])) {
            $query->where('root_kind', self::KINDS[$f['kind']]);
        }
        match ($f['price'] ?? null) {
            'free' => $query->where('price', 0),
            'paid' => $query->where('price', '>', 0),
            default => null,
        };
        if (isset($f['min'])) {
            $query->where('price', '>=', (int) $f['min']);
        }
        if (isset($f['max'])) {
            $query->where('price', '<=', (int) $f['max']);
        }
        if (! empty($f['teacher'])) {
            $query->where('site_slug', $f['teacher']);
        }
        $total = (clone $query)->count();
        $sort = $f['sort'] ?? 'relevance';
        $this->order($query, $sort, $tokens);
        $page = max(1, (int) ($f['page'] ?? 1));
        $rows = $query->offset(($page - 1) * self::PER_PAGE)->limit(self::PER_PAGE)->get();

        return [
            'data' => $rows->map(fn (object $r) => self::card($r))->all(),
            'meta' => ['total' => $total, 'page' => $page, 'per_page' => self::PER_PAGE, 'last_page' => max(1, (int) ceil($total / self::PER_PAGE))],
        ];
    }

    public function find(string $courseId): ?object
    {
        return DB::table('market_courses')->where('course_id', $courseId)->first();
    }

    /** @return list<array<string, mixed>> */
    public function byTeacher(string $slug, ?string $except = null, int $limit = 50): array
    {
        return DB::table('market_courses')->where('site_slug', $slug)->when($except, fn ($q, $id) => $q->where('course_id', '!=', $id))
            ->orderByDesc('students')->orderByDesc('published_at')->limit($limit)->get()->map(fn (object $r) => self::card($r))->all();
    }

    /** @return list<array<string, mixed>> the tree's children of a node, each with how many listed courses sit under it */
    public function nodes(?string $parentId): array
    {
        $children = $this->tree->children($parentId);
        $ids = array_column($children, 'id');
        $counts = $ids === [] ? [] : collect(DB::select(
            'SELECT n AS id, count(*) AS courses FROM market_courses, unnest(node_ids) AS n WHERE n = ANY(?::uuid[]) GROUP BY n',
            ['{'.implode(',', $ids).'}'],
        ))->pluck('courses', 'id')->map(fn ($n) => (int) $n)->all();

        return array_map(fn (array $node) => [
            'id' => $node['id'], 'name' => $node['name'], 'kind' => $node['kind'], 'kind_label' => $node['kind_label'],
            'has_children' => $node['has_children'], 'courses' => $counts[$node['id']] ?? 0,
        ], $children);
    }

    /** @return array<string, int> listed courses per kind */
    public function kindCounts(): array
    {
        $byRoot = DB::table('market_courses')->selectRaw('root_kind, count(*) as n')->groupBy('root_kind')->pluck('n', 'root_kind');

        return array_map(fn (string $root) => (int) ($byRoot[$root] ?? 0), self::KINDS);
    }

    /** @return array<string, mixed> */
    public static function card(object $r): array
    {
        return [
            'id' => $r->course_id,
            'title' => $r->title,
            'description' => $r->description === null ? null : mb_substr($r->description, 0, 240),
            'price' => (int) $r->price,
            'access_days' => $r->access_days === null ? null : (int) $r->access_days,
            'subject' => $r->node_label,
            'kind' => array_search($r->root_kind, self::KINDS, true) ?: 'school',
            'lessons_count' => (int) $r->lessons_count,
            'free_previews' => (int) $r->free_previews,
            'students' => (int) $r->students,
            'rating' => $r->rating === null ? null : (float) $r->rating,
            'reviews' => (int) $r->reviews,
            'published_at' => $r->published_at === null ? null : now()->parse($r->published_at)->toIso8601String(),
            'teacher' => ['name' => $r->teacher, 'slug' => $r->site_slug, 'headline' => $r->headline, 'color' => $r->color],
            // The student opens it on the teacher's own platform.
            'url' => "{$r->site_url}/courses/{$r->course_id}",
        ];
    }

    /** @param  list<string>  $tokens */
    private function order(Builder $query, string $sort, array $tokens): void
    {
        match ($sort) {
            'rating' => $query->orderByRaw('rating DESC NULLS LAST')->orderByDesc('reviews'),
            'students' => $query->orderByDesc('students'),
            'new' => $query->orderByRaw('published_at DESC NULLS LAST'),
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            // Relevance: the closest title first, then what students chose and rated.
            default => $tokens !== []
                ? $query->orderByRaw('similarity(search_name, ?) DESC', [implode(' ', $tokens)])->orderByDesc('students')
                : $query->orderByDesc('students')->orderByRaw('rating DESC NULLS LAST'),
        };
        $query->orderBy('course_id');
    }
}
