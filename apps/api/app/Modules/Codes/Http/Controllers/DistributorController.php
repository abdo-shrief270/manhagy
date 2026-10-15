<?php

declare(strict_types=1);

namespace App\Modules\Codes\Http\Controllers;

use App\Modules\Codes\Models\CodeBatch;
use App\Modules\Codes\Models\Distributor;
use App\Modules\Codes\Models\DistributorPayment;
use App\Modules\Codes\Support\Stats;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Libraries / centers / assistants selling the cards, and what they collected for the teacher. */
final class DistributorController
{
    public function __construct(private readonly Auditor $auditor) {}

    public function index(): JsonResponse
    {
        $rows = Distributor::query()->orderByDesc('active')->orderBy('name')->get();
        $stats = Stats::distributors($rows->pluck('id')->all());

        return response()->json(['data' => $rows->map(fn (Distributor $d) => [...$this->summary($d), 'stats' => $stats[$d->id]])->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $distributor = Distributor::query()->create($this->validated($request, true));

        return response()->json(['data' => $this->summary($distributor)], 201);
    }

    public function update(Request $request, string $distributor): JsonResponse
    {
        $found = Distributor::query()->findOrFail($distributor);
        $found->update($this->validated($request, false));

        return response()->json(['data' => $this->summary($found)]);
    }

    /** The statement: their batches and the money collected. */
    public function show(string $distributor): JsonResponse
    {
        $found = Distributor::query()->findOrFail($distributor);
        $batches = CodeBatch::query()->where('distributor_id', $found->id)->orderByDesc('number')->get();
        $batchStats = Stats::batches($batches->pluck('id')->all());

        return response()->json(['data' => [
            ...$this->summary($found),
            'stats' => Stats::distributors([$found->id])[$found->id],
            'batches' => $batches->map(fn (CodeBatch $b) => [
                'id' => $b->id, 'number' => $b->code(), 'kind' => $b->kind, 'count' => $b->count, 'unit_price' => $b->unit_price,
                'status' => $b->status, 'created_at' => $b->created_at->toIso8601String(), 'stats' => $batchStats[$b->id],
            ])->all(),
            'payments' => DistributorPayment::query()->where('distributor_id', $found->id)->orderByDesc('seq')->get()
                ->map(fn (DistributorPayment $p) => $p->toSummary())->all(),
        ]]);
    }

    public function pay(Request $request, string $distributor): JsonResponse
    {
        $data = $request->validate(['amount' => ['required', 'integer', 'min:1', 'max:100000000'], 'note' => ['nullable', 'string', 'max:200']], [], ['amount' => 'المبلغ']);
        $found = Distributor::query()->findOrFail($distributor);
        $payment = DB::transaction(function () use ($found, $data, $request): DistributorPayment {
            $payment = DistributorPayment::query()->create([
                'distributor_id' => $found->id, 'amount' => $data['amount'], 'note' => $data['note'] ?? null, 'by_name' => $request->user()?->name, 'created_at' => now(),
            ]);
            $this->auditor->record('codes.distributor_paid', 'تحصيل '.($data['amount'] / 100)." ج من {$found->name}", $found);

            return $payment;
        });

        return response()->json(['data' => $payment->toSummary()], 201);
    }

    public function reverse(Request $request, string $distributor, string $payment): JsonResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:2', 'max:200']], [], ['note' => 'السبب']);
        $found = Distributor::query()->findOrFail($distributor);
        $reversal = DB::transaction(function () use ($found, $payment, $data, $request): DistributorPayment {
            $original = DistributorPayment::query()->where('distributor_id', $found->id)->lockForUpdate()->findOrFail($payment);
            if ($original->reverses_id !== null || DistributorPayment::query()->where('reverses_id', $original->id)->exists()) {
                throw new DomainRuleException('التحصيل ده اتلغى قبل كده.', 'payment_reversed');
            }
            $reversal = DistributorPayment::query()->create([
                'distributor_id' => $found->id, 'amount' => -$original->amount, 'reverses_id' => $original->id,
                'note' => $data['note'], 'by_name' => $request->user()?->name, 'created_at' => now(),
            ]);
            $this->auditor->record('codes.distributor_payment_reversed', 'إلغاء تحصيل '.($original->amount / 100)." ج من {$found->name}: {$data['note']}", $found);

            return $reversal;
        });

        return response()->json(['data' => $reversal->toSummary()], 201);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'min:2', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'regex:/^0[0-9]{9,10}$/'],
            'kind' => ['sometimes', Rule::in(Distributor::KINDS)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
            'active' => ['sometimes', 'boolean'],
        ], [], ['name' => 'الاسم', 'phone' => 'الموبايل']);
    }

    /** @return array<string, mixed> */
    private function summary(Distributor $d): array
    {
        return ['id' => $d->id, 'name' => $d->name, 'phone' => $d->phone, 'kind' => $d->kind, 'notes' => $d->notes, 'active' => $d->active];
    }
}
