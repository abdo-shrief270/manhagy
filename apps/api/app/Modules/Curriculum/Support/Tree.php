<?php

declare(strict_types=1);

namespace App\Modules\Curriculum\Support;

use App\Modules\Curriculum\Contracts\CurriculumAdmin;
use App\Modules\Curriculum\Contracts\CurriculumTree;
use App\Modules\Curriculum\Enums\NodeKind;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Text\SearchText;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * `curriculum_nodes` through the query builder: the tree belongs to the platform, not to an
 * account. The same name (normalised) can't appear twice under the same parent.
 */
final class Tree implements CurriculumAdmin, CurriculumTree
{
    private const TABLE = 'curriculum_nodes';

    public function children(?string $parentId): array
    {
        return $this->rows($this->query()->where('n.status', 'published')
            ->when($parentId === null, fn ($q) => $q->whereNull('n.parent_id'), fn ($q) => $q->where('n.parent_id', $parentId)));
    }

    public function node(string $id, bool $evenUnpublished = false): ?array
    {
        if (! Str::isUuid($id)) {
            return null;
        }
        $rows = $this->rows($this->query()->where('n.id', $id)->when(! $evenUnpublished, fn ($q) => $q->where('n.status', 'published')));

        return $rows[0] ?? null;
    }

    public function path(string $id): array
    {
        if (! Str::isUuid($id)) {
            return [];
        }
        $ids = collect(DB::select(<<<'SQL'
            WITH RECURSIVE up AS (
                SELECT id, parent_id, 0 AS depth FROM curriculum_nodes WHERE id = ?
                UNION ALL
                SELECT n.id, n.parent_id, up.depth + 1 FROM curriculum_nodes n JOIN up ON n.id = up.parent_id
            )
            SELECT id, depth FROM up ORDER BY depth DESC
            SQL, [$id]))->pluck('id')->all();

        $nodes = collect($this->rows($this->query()->whereIn('n.id', $ids)))->keyBy('id');

        return array_values(array_filter(array_map(fn (string $nodeId) => $nodes[$nodeId] ?? null, $ids)));
    }

    public function label(string $id): string
    {
        return Str::isUuid($id) ? ($this->labels([$id])[$id] ?? '') : '';
    }

    public function search(string $query, ?string $kind = null, int $limit = 20): array
    {
        $tokens = SearchText::tokens($query);
        if ($tokens === []) {
            return [];
        }
        // A word may name the node or one of its parents («فيزياء التالت»): find nodes matching any
        // word, then keep those whose whole path has every word.
        $q = $this->query()->where('n.status', 'published')->when($kind !== null, fn ($q) => $q->where('n.kind', $kind))
            ->where(function ($q) use ($tokens): void {
                foreach ($tokens as $token) {
                    $q->orWhere('n.search_name', 'like', SearchText::like($token));
                }
            });

        $rows = $this->rows($q->limit(400));
        $labels = $this->labels(array_column($rows, 'id'));
        $found = [];
        foreach ($rows as $node) {
            $label = $labels[$node['id']] ?? $node['name'];
            $haystack = SearchText::normalize($label);
            if (collect($tokens)->every(fn (string $t) => str_contains($haystack, $t))) {
                $found[] = [...$node, 'label' => $label];
                if (count($found) >= $limit) {
                    break;
                }
            }
        }

        return $found;
    }

    public function suggest(string $parentId, string $kind, string $name, ?string $code, string $tenantId, string $byName): array
    {
        $parent = $this->node($parentId, true) ?? throw new DomainRuleException('المكان اللي اخترته مش موجود في الشجرة.', 'curriculum_parent_missing', 404);
        $this->checkKind($parent['kind'], $kind);

        $existing = DB::table(self::TABLE)->where('parent_id', $parentId)->where('search_name', SearchText::normalize($name))->value('id');
        if ($existing !== null) {
            return $this->node($existing, true);
        }

        $id = (string) Str::uuid7();
        DB::table(self::TABLE)->insert([
            'id' => $id, 'parent_id' => $parentId, 'kind' => $kind, 'name' => trim($name), 'search_name' => SearchText::normalize($name),
            'code' => $code, 'status' => 'review', 'sort' => $this->nextSort($parentId),
            'suggested_by_tenant' => $tenantId, 'suggested_by_name' => $byName, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $this->node($id, true);
    }

    public function browse(array $filters): array
    {
        $q = $this->query();
        $status = $filters['status'] ?? null;
        $search = trim((string) ($filters['q'] ?? ''));

        if ($search !== '') {
            foreach (SearchText::tokens($search) as $token) {
                $q->where('n.search_name', 'like', SearchText::like($token));
            }
        } elseif ($status !== null && ! array_key_exists('parent', $filters)) {
            // Every node in that status anywhere (the admins' «للمراجعة» list).
        } else {
            $parent = $filters['parent'] ?? null;
            $q->when($parent === null, fn ($q) => $q->whereNull('n.parent_id'), fn ($q) => $q->where('n.parent_id', $parent));
        }
        $q->when($status !== null, fn ($q) => $q->where('n.status', $status));

        $rows = $this->rows($q->limit(500), adminView: true);
        $labels = $this->labels(array_column($rows, 'id'));

        return array_map(fn (array $node) => [
            ...$node,
            'label' => $labels[$node['id']] ?? $node['name'],
            'suggested_by' => $node['suggested_by_name'],
        ], $rows);
    }

    public function save(?string $id, array $data): array
    {
        $current = $id === null ? null : ($this->node($id, true) ?? throw new DomainRuleException('مش موجود.', 'curriculum_missing', 404));
        $parentId = array_key_exists('parent_id', $data) ? $data['parent_id'] : ($current['parent_id'] ?? null);
        $kind = $data['kind'] ?? $current['kind'] ?? throw new DomainRuleException('اختار نوع العنصر.', 'curriculum_kind_required');
        $name = trim((string) ($data['name'] ?? $current['name'] ?? ''));

        if ($parentId === null) {
            if (! in_array(NodeKind::from($kind), NodeKind::roots(), true)) {
                throw new DomainRuleException('أول مستوى في الشجرة مرحلة أو جامعة بس.', 'curriculum_kind_not_allowed');
            }
        } else {
            $parent = $this->node($parentId, true) ?? throw new DomainRuleException('المكان اللي اخترته مش موجود في الشجرة.', 'curriculum_parent_missing', 404);
            if ($id !== null && in_array($id, array_column($this->path($parentId), 'id'), true)) {
                throw new DomainRuleException('مينفعش تحط عنصر جوه نفسه.', 'curriculum_cycle');
            }
            $this->checkKind($parent['kind'], $kind);
        }

        $search = SearchText::normalize($name);
        $clash = DB::table(self::TABLE)
            ->when($parentId === null, fn ($q) => $q->whereNull('parent_id'), fn ($q) => $q->where('parent_id', $parentId))
            ->where('search_name', $search)->when($id !== null, fn ($q) => $q->where('id', '!=', $id))->exists();
        if ($clash) {
            throw new DomainRuleException('فيه عنصر بنفس الاسم في نفس المكان.', 'curriculum_exists');
        }

        $values = [
            'parent_id' => $parentId, 'kind' => $kind, 'name' => $name, 'search_name' => $search,
            'code' => array_key_exists('code', $data) ? $data['code'] : ($current['code'] ?? null),
            'status' => $data['status'] ?? $current['status'] ?? 'published',
            'sort' => $data['sort'] ?? $current['sort'] ?? $this->nextSort($parentId),
            'updated_at' => now(),
        ];
        if ($id === null) {
            $id = (string) Str::uuid7();
            DB::table(self::TABLE)->insert(['id' => $id, ...$values, 'created_at' => now()]);
        } else {
            DB::table(self::TABLE)->where('id', $id)->update($values);
        }

        return $this->node($id, true);
    }

    public function delete(string $id): void
    {
        if (DB::table(self::TABLE)->where('parent_id', $id)->exists()) {
            throw new DomainRuleException('فيه عناصر تحته، امسحها أو انقلها الأول.', 'curriculum_has_children');
        }
        DB::table(self::TABLE)->where('id', $id)->delete();
    }

    public function toReview(): int
    {
        return DB::table(self::TABLE)->where('status', 'review')->count();
    }

    /**
     * Each node's path as «a › b › c», in one query.
     *
     * @param  list<string>  $ids
     * @return array<string, string>
     */
    private function labels(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return collect(DB::select(<<<'SQL'
            WITH RECURSIVE up AS (
                SELECT id AS origin, id, parent_id, name, 0 AS depth FROM curriculum_nodes WHERE id = ANY(?::uuid[])
                UNION ALL
                SELECT up.origin, n.id, n.parent_id, n.name, up.depth + 1 FROM curriculum_nodes n JOIN up ON n.id = up.parent_id
            )
            SELECT origin, string_agg(name, ' › ' ORDER BY depth DESC) AS label FROM up GROUP BY origin
            SQL, ['{'.implode(',', $ids).'}']))->pluck('label', 'origin')->all();
    }

    private function checkKind(string $parentKind, string $kind): void
    {
        $child = NodeKind::tryFrom($kind);
        if ($child === null || ! NodeKind::from($parentKind)->allows($child)) {
            $allowed = implode(' أو ', array_map(fn (NodeKind $k) => $k->label(), NodeKind::from($parentKind)->children()));
            throw new DomainRuleException($allowed === '' ? 'مينفعش يتحط حاجة تحت المستوى ده.' : 'تحت «'.NodeKind::from($parentKind)->label()."» يتحط {$allowed} بس.", 'curriculum_kind_not_allowed');
        }
    }

    private function nextSort(?string $parentId): int
    {
        return (int) DB::table(self::TABLE)->when($parentId === null, fn ($q) => $q->whereNull('parent_id'), fn ($q) => $q->where('parent_id', $parentId))->max('sort') + 1;
    }

    private function query(): Builder
    {
        return DB::table(self::TABLE.' as n')
            ->select('n.*')
            ->selectRaw('EXISTS (SELECT 1 FROM curriculum_nodes c WHERE c.parent_id = n.id) AS has_children')
            ->orderBy('n.sort')->orderBy('n.name');
    }

    /** @return list<array<string, mixed>> */
    private function rows(Builder $query, bool $adminView = false): array
    {
        return $query->get()->map(function (object $row) use ($adminView): array {
            $kind = NodeKind::from($row->kind);
            $node = [
                'id' => $row->id,
                'parent_id' => $row->parent_id,
                'kind' => $kind->value,
                'kind_label' => $kind->label(),
                'name' => $row->name,
                'code' => $row->code,
                'status' => $row->status,
                'sort' => (int) $row->sort,
                'has_children' => (bool) $row->has_children,
                'teachable' => $kind->teachable(),
            ];

            return $adminView ? [...$node, 'suggested_by_name' => $row->suggested_by_name, 'suggested_by_tenant' => $row->suggested_by_tenant] : $node;
        })->all();
    }
}
