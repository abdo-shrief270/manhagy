<?php

declare(strict_types=1);

namespace App\Modules\Curriculum\Http\Controllers;

use App\Modules\Curriculum\Contracts\CurriculumTree;
use App\Modules\Curriculum\Enums\NodeKind;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The published tree for anyone: «اختار صفك», a teacher picking where a course goes, the marketplace. */
final class PublicCurriculumController
{
    public function __construct(private readonly CurriculumTree $tree) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['parent' => ['nullable', 'uuid']]);

        return response()->json(['data' => $this->tree->children($data['parent'] ?? null)])
            ->header('Cache-Control', 'public, max-age=300');
    }

    public function show(string $node): JsonResponse
    {
        $found = $this->tree->node($node) ?? abort(404);

        return response()->json(['data' => [...$found, 'path' => $this->tree->path($node), 'children' => $this->tree->children($node)]])
            ->header('Cache-Control', 'public, max-age=300');
    }

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:80'],
            'kind' => ['nullable', Rule::enum(NodeKind::class)],
        ]);

        return response()->json(['data' => $this->tree->search($data['q'], $data['kind'] ?? null)]);
    }

    public function kinds(): JsonResponse
    {
        return response()->json(['data' => array_map(fn (NodeKind $k) => [
            'value' => $k->value, 'label' => $k->label(), 'children' => array_map(fn (NodeKind $c) => $c->value, $k->children()), 'teachable' => $k->teachable(),
        ], NodeKind::cases())]);
    }
}
