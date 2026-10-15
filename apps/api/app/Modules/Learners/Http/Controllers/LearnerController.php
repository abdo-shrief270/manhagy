<?php

declare(strict_types=1);

namespace App\Modules\Learners\Http\Controllers;

use App\Modules\Curriculum\Contracts\CurriculumTree;
use App\Modules\Learners\Models\Learner;
use App\Modules\Learners\Models\LearnerDevice;
use App\Modules\Learners\Models\LearnerProfile;
use App\Support\Audit\Auditor;
use App\Support\Text\SearchText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** The account's students: list, one student, block / unblock, freeing a device. */
final class LearnerController
{
    public function __construct(
        private readonly CurriculumTree $curriculum,
        private readonly Auditor $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'grade' => ['nullable', 'uuid'],
            'blocked' => ['nullable', 'boolean'],
        ]);

        $query = LearnerProfile::query()->with('learner')->withCount('devices')
            ->when($filters['grade'] ?? null, fn ($q, $grade) => $q->where('grade_node_id', $grade))
            ->when(array_key_exists('blocked', $filters) && $filters['blocked'] !== null, fn ($q) => $filters['blocked'] ? $q->whereNotNull('blocked_at') : $q->whereNull('blocked_at'));
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $digits = preg_replace('/\D/', '', $search);
            $query->whereHas('learner', function ($q) use ($search, $digits): void {
                $q->where(function ($q) use ($search, $digits): void {
                    $q->whereRaw('lower(name) like ?', [SearchText::like(mb_strtolower($search))]);
                    if (strlen((string) $digits) >= 4) {
                        $q->orWhere('phone', 'like', '%'.ltrim((string) $digits, '0').'%');
                    }
                });
            });
        }

        $page = $query->orderByDesc('last_seen_at')->orderByDesc('created_at')->paginate(50);

        return response()->json([
            'data' => collect($page->items())->map(fn (LearnerProfile $p) => $this->row($p))->all(),
            'meta' => ['total' => $page->total(), 'page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function show(string $profile): JsonResponse
    {
        $found = LearnerProfile::query()->with('learner')->withCount('devices')->findOrFail($profile);

        return response()->json(['data' => [
            ...$this->row($found),
            'notes' => $found->notes,
            'blocked_reason' => $found->blocked_reason,
            'devices' => LearnerDevice::query()->where('learner_id', $found->learner_id)->orderBy('created_at')->get()
                ->map(fn (LearnerDevice $d) => ['id' => $d->id, 'name' => $d->name, 'added_at' => $d->created_at->toIso8601String(), 'last_seen_at' => $d->last_seen_at?->toIso8601String()]),
        ]]);
    }

    public function update(Request $request, string $profile): JsonResponse
    {
        $found = LearnerProfile::query()->findOrFail($profile);
        $data = $request->validate(['notes' => ['sometimes', 'nullable', 'string', 'max:2000'], 'grade_id' => ['sometimes', 'nullable', 'uuid']]);
        $found->update([
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $found->notes,
            'grade_node_id' => array_key_exists('grade_id', $data) ? $data['grade_id'] : $found->grade_node_id,
        ]);

        return $this->show($profile);
    }

    public function block(Request $request, string $profile): JsonResponse
    {
        $found = LearnerProfile::query()->with('learner')->findOrFail($profile);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:200']], [], ['reason' => 'السبب']);
        DB::transaction(function () use ($found, $data): void {
            $found->update(['blocked_at' => now(), 'blocked_reason' => $data['reason']]);
            $this->audit->record('learners.blocked', "أوقف الطالب {$found->learner->name}: {$data['reason']}", $found);
        });

        return $this->show($profile);
    }

    public function unblock(string $profile): JsonResponse
    {
        $found = LearnerProfile::query()->with('learner')->findOrFail($profile);
        DB::transaction(function () use ($found): void {
            $found->update(['blocked_at' => null, 'blocked_reason' => null]);
            $this->audit->record('learners.unblocked', "رجّع الطالب {$found->learner->name}", $found);
        });

        return $this->show($profile);
    }

    /** Frees a device slot: the device's session ends and the student may sign in from another. */
    public function removeDevice(string $profile, string $device): JsonResponse
    {
        $found = LearnerProfile::query()->with('learner')->findOrFail($profile);
        $row = LearnerDevice::query()->where('learner_id', $found->learner_id)->findOrFail($device);
        DB::transaction(function () use ($found, $row): void {
            Learner::query()->findOrFail($found->learner_id)->tokens()->where('name', "device:{$row->id}")->delete();
            $row->delete();
            $this->audit->record('learners.device_removed', "شال جهاز «{$row->name}» من حساب الطالب {$found->learner->name}", $found);
        });

        return $this->show($profile);
    }

    /** @return array<string, mixed> */
    private function row(LearnerProfile $p): array
    {
        return [
            'id' => $p->id,
            'learner_id' => $p->learner_id,
            'name' => $p->learner->name,
            'phone' => $p->learner->phone,
            'parent_phone' => $p->parent_phone,
            'grade_id' => $p->grade_node_id,
            'grade' => $p->grade_node_id ? $this->curriculum->label($p->grade_node_id) : null,
            'devices' => (int) $p->devices_count,
            'blocked' => $p->blocked_at !== null,
            'joined_at' => $p->created_at->toIso8601String(),
            'last_seen_at' => $p->last_seen_at?->toIso8601String(),
        ];
    }
}
