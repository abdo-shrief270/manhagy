<?php

declare(strict_types=1);

namespace App\Modules\Center\Http\Controllers;

use App\Modules\Center\Models\Settlement;
use App\Modules\Center\Models\Teacher;
use App\Modules\Center\Support\Shares;
use App\Support\Audit\Auditor;
use App\Support\Time\ShopDay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** The center's teachers, their share, statement and settlements. */
final class TeacherController
{
    public function index(Request $request): JsonResponse
    {
        [$from, $to] = $this->period($request);
        $rows = Teacher::query()->orderByDesc('active')->orderBy('name')->get();

        return response()->json(['data' => $rows->map(fn (Teacher $t) => [...$this->summary($t), 'statement' => Shares::statement($t, $from, $to)])->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $teacher = Teacher::query()->create($this->validated($request, true));

        return response()->json(['data' => $this->summary($teacher)], 201);
    }

    public function update(Request $request, string $teacher): JsonResponse
    {
        $found = Teacher::query()->findOrFail($teacher);
        $found->update($this->validated($request, false));

        return response()->json(['data' => $this->summary($found)]);
    }

    public function show(Request $request, string $teacher): JsonResponse
    {
        [$from, $to] = $this->period($request);
        $found = Teacher::query()->findOrFail($teacher);

        return response()->json(['data' => [
            ...$this->summary($found),
            'statement' => Shares::statement($found, $from, $to),
            'settlements' => Settlement::query()->where('teacher_id', $found->id)->orderByDesc('seq')->limit(50)->get()->map(fn (Settlement $s) => [
                'id' => $s->id, 'from' => $s->period_from->toDateString(), 'to' => $s->period_to->toDateString(), 'due' => $s->due,
                'paid' => $s->paid, 'note' => $s->note, 'by_name' => $s->by_name, 'created_at' => $s->created_at->toIso8601String(),
            ])->all(),
        ]]);
    }

    /** Paying the teacher their share for a period (recorded; the money leaves outside the drawers). */
    public function settle(Request $request, string $teacher, Auditor $auditor): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from'],
            'paid' => ['required', 'integer', 'min:1'], 'note' => ['nullable', 'string', 'max:200'],
        ]);
        $found = Teacher::query()->findOrFail($teacher);
        $statement = Shares::statement($found, Carbon::parse($data['from']), Carbon::parse($data['to']));
        $settlement = DB::transaction(function () use ($found, $data, $statement, $request, $auditor): Settlement {
            $s = Settlement::query()->create([
                'teacher_id' => $found->id, 'period_from' => $data['from'], 'period_to' => $data['to'], 'due' => $statement['due'],
                'paid' => (int) $data['paid'], 'note' => $data['note'] ?? null, 'by_name' => $request->user()?->name, 'created_at' => now(),
            ]);
            $auditor->record('center.teacher_settled', "تسوية نصيب {$found->name}: ".((int) $data['paid'] / 100).' ج', $s);

            return $s;
        });

        return response()->json(['data' => ['id' => $settlement->id, 'statement' => Shares::statement($found, Carbon::parse($data['from']), Carbon::parse($data['to']))]], 201);
    }

    /** @return array{0: Carbon, 1: Carbon} this Cairo month by default */
    private function period(Request $request): array
    {
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $now = Carbon::now(ShopDay::TZ);

        return [
            isset($data['from']) ? Carbon::parse($data['from'], ShopDay::TZ) : $now->copy()->startOfMonth(),
            isset($data['to']) ? Carbon::parse($data['to'], ShopDay::TZ) : $now->copy()->endOfMonth(),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $creating): array
    {
        $data = $request->validate([
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'min:2', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'regex:/^0[0-9]{9,10}$/'],
            'subject' => ['sometimes', 'nullable', 'string', 'max:80'],
            'share_kind' => ['sometimes', Rule::in(['percent', 'per_student'])],
            'share_value' => ['sometimes', 'integer', 'min:0', 'max:10000000'],
            'active' => ['sometimes', 'boolean'],
        ], [], ['name' => 'الاسم', 'share_value' => 'النصيب']);
        if (($data['share_kind'] ?? null) === 'percent' && ($data['share_value'] ?? 0) > 100) {
            abort(response()->json(['message' => 'النسبة من 0 لـ 100.', 'errors' => ['share_value' => ['النسبة من 0 لـ 100.']]], 422));
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function summary(Teacher $t): array
    {
        return ['id' => $t->id, 'name' => $t->name, 'phone' => $t->phone, 'subject' => $t->subject, 'share_kind' => $t->share_kind, 'share_value' => $t->share_value, 'active' => $t->active];
    }
}
