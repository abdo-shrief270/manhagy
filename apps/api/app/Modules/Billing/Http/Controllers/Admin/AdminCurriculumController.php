<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\Admin;

use App\Modules\Billing\Support\AdminLog;
use App\Modules\Curriculum\Contracts\CurriculumAdmin;
use App\Modules\Curriculum\Contracts\CurriculumTree;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** «شجرة المناهج»: the platform admins keep the tree and review what teachers suggest. */
final class AdminCurriculumController
{
    public function __construct(
        private readonly CurriculumAdmin $admin,
        private readonly CurriculumTree $tree,
        private readonly AdminLog $log,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'parent' => ['nullable', 'uuid'],
            'status' => ['nullable', Rule::in(CurriculumAdmin::STATUSES)],
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        return response()->json(['data' => $this->admin->browse($filters), 'meta' => ['review' => $this->admin->toReview()]]);
    }

    public function store(Request $request): JsonResponse
    {
        $node = $this->admin->save(null, $this->validated($request, true));
        $this->log->record('curriculum_added', null, $node['id'], ['node' => $this->tree->label($node['id'])]);

        return response()->json(['data' => $node], 201);
    }

    public function update(Request $request, string $node): JsonResponse
    {
        abort_if($this->tree->node($node, true) === null, 404);
        $data = $this->validated($request, false);
        $saved = $this->admin->save($node, $data);
        $this->log->record('curriculum_changed', null, $node, ['node' => $this->tree->label($node), ...array_intersect_key($data, array_flip(['status', 'name']))]);

        return response()->json(['data' => $saved]);
    }

    public function destroy(string $node): JsonResponse
    {
        abort_if($this->tree->node($node, true) === null, 404);
        $label = $this->tree->label($node);
        $this->admin->delete($node);
        $this->log->record('curriculum_deleted', null, $node, ['node' => $label]);

        return response()->json(['data' => ['deleted' => true]]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'parent_id' => ['sometimes', 'nullable', 'uuid'],
            'kind' => [$required, Rule::in(CurriculumAdmin::KINDS)],
            'name' => [$required, 'string', 'min:2', 'max:160'],
            'code' => ['sometimes', 'nullable', 'string', 'max:40'],
            'status' => ['sometimes', Rule::in(CurriculumAdmin::STATUSES)],
            'sort' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ]);
    }
}
