<?php

declare(strict_types=1);

namespace App\Modules\Center\Http\Controllers;

use App\Modules\Center\Models\Handover;
use App\Modules\Center\Models\Payment;
use App\Modules\Learners\Contracts\LearnerDirectory;
use App\Support\Audit\Auditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Each collector's drawer: the cash taken and not handed over yet; handing it over records the difference. */
final class CashController
{
    /** My drawer. */
    public function mine(Request $request, LearnerDirectory $learners): JsonResponse
    {
        $userId = $request->user()?->getAuthIdentifier();
        $rows = Payment::query()->where('user_id', $userId)->whereNull('handover_id')->orderByDesc('seq')->limit(300)->get();
        $names = [];

        return response()->json(['data' => [
            'in_hand' => (int) $rows->sum('amount'),
            'payments' => $rows->map(function (Payment $p) use ($learners, &$names): array {
                $names[$p->learner_id] ??= $learners->find($p->learner_id)['name'] ?? null;

                return ['id' => $p->id, 'learner_name' => $names[$p->learner_id], 'kind' => $p->kind, 'period' => $p->period, 'amount' => $p->amount, 'created_at' => $p->created_at->toIso8601String()];
            })->all(),
            'handovers' => Handover::query()->where('user_id', $userId)->latest('created_at')->limit(10)->get()->map(fn (Handover $h) => $this->handoverRow($h))->all(),
        ]]);
    }

    /** Every drawer with cash in it, for whoever receives the money. */
    public function all(): JsonResponse
    {
        $rows = Payment::query()->whereNull('handover_id')->whereNotNull('user_id')
            ->selectRaw('user_id, max(by_name) as name, sum(amount) as in_hand, count(*) as payments')->groupBy('user_id')->get();

        return response()->json(['data' => [
            'drawers' => $rows->map(fn ($r) => ['user_id' => $r->user_id, 'name' => $r->name, 'in_hand' => (int) $r->in_hand, 'payments' => (int) $r->payments])->values()->all(),
            'handovers' => Handover::query()->latest('created_at')->limit(30)->get()->map(fn (Handover $h) => $this->handoverRow($h))->all(),
        ]]);
    }

    public function handover(Request $request, Auditor $auditor): JsonResponse
    {
        $data = $request->validate(['user_id' => ['required', 'uuid'], 'counted' => ['required', 'integer', 'min:0'], 'note' => ['nullable', 'string', 'max:200']], [], ['counted' => 'المبلغ اللي اتعدّ']);
        $handover = DB::transaction(function () use ($data, $request, $auditor): Handover {
            $payments = Payment::query()->where('user_id', $data['user_id'])->whereNull('handover_id')->lockForUpdate()->get();
            abort_if($payments->isEmpty(), 422, 'الدرج ده فاضي.');
            $expected = (int) $payments->sum('amount');
            $handover = Handover::query()->create([
                'user_id' => $data['user_id'], 'user_name' => (string) $payments->first()->by_name, 'expected' => $expected,
                'counted' => (int) $data['counted'], 'difference' => (int) $data['counted'] - $expected, 'note' => $data['note'] ?? null,
                'received_by_name' => $request->user()?->name, 'created_at' => now(),
            ]);
            Payment::query()->whereKey($payments->pluck('id'))->update(['handover_id' => $handover->id]);
            $auditor->record('center.handover', "استلام درج {$handover->user_name}: المتوقع ".($expected / 100).' ج والمعدود '.((int) $data['counted'] / 100).' ج', $handover);

            return $handover;
        });

        return response()->json(['data' => $this->handoverRow($handover)], 201);
    }

    /** @return array<string, mixed> */
    private function handoverRow(Handover $h): array
    {
        return ['id' => $h->id, 'user_name' => $h->user_name, 'expected' => $h->expected, 'counted' => $h->counted, 'difference' => $h->difference, 'note' => $h->note, 'received_by_name' => $h->received_by_name, 'created_at' => $h->created_at->toIso8601String()];
    }
}
