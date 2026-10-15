<?php

declare(strict_types=1);

namespace App\Modules\Curriculum\Http\Controllers;

use App\Modules\Curriculum\Contracts\CurriculumTree;
use App\Modules\Curriculum\Enums\NodeKind;
use App\Support\Audit\Auditor;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** A teacher adds a faculty, course, unit… that isn't in the tree yet; the platform admins review it. */
final class SuggestionController
{
    public function __invoke(Request $request, CurriculumTree $tree, CurrentTenant $tenant, Auditor $audit): JsonResponse
    {
        $data = $request->validate([
            'parent_id' => ['required', 'uuid'],
            'kind' => ['required', Rule::enum(NodeKind::class)],
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'code' => ['nullable', 'string', 'max:40'],
        ], [], ['parent_id' => 'المكان', 'kind' => 'النوع', 'name' => 'الاسم', 'code' => 'كود المقرر']);

        $node = $tree->suggest($data['parent_id'], $data['kind'], $data['name'], $data['code'] ?? null, $tenant->idOrFail(), (string) $request->user()?->name);
        $audit->record('curriculum.suggested', "اقتراح «{$node['name']}» تحت «{$tree->label((string) $node['parent_id'])}»");

        return response()->json(['data' => [...$node, 'label' => $tree->label($node['id'])]], 201);
    }
}
