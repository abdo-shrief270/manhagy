<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Http\Controllers;

use App\Modules\Learners\Contracts\LearnerDirectory;
use App\Modules\Wallet\Contracts\LearnerWallets;
use App\Modules\Wallet\Models\WalletSettings;
use App\Modules\Wallet\Models\WalletTopup;
use App\Modules\Wallet\Models\WalletTransaction;
use App\Modules\Wallet\Support\Money;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The account's side: transfers to confirm, a student's statement, adjustments, the transfer details. */
final class WalletController
{
    public function __construct(
        private readonly LearnerWallets $wallets,
        private readonly LearnerDirectory $learners,
        private readonly Auditor $auditor,
        private readonly CurrentTenant $tenant,
    ) {}

    public function topups(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])]]);
        $rows = WalletTopup::query()->where('status', $data['status'] ?? 'pending')
            ->when(($data['status'] ?? 'pending') === 'pending', fn ($q) => $q->oldest(), fn ($q) => $q->latest('decided_at'))->limit(200)->get();
        $people = [];
        foreach ($rows->pluck('learner_id')->unique() as $id) {
            $people[$id] = $this->learners->find($id);
        }

        return response()->json(['data' => $rows->map(fn (WalletTopup $t) => [
            ...$t->toSummary(), 'learner_name' => $people[$t->learner_id]['name'] ?? null, 'learner_phone' => $people[$t->learner_id]['phone'] ?? null,
        ])->all(), 'meta' => ['pending' => WalletTopup::query()->where('status', 'pending')->count()]]);
    }

    public function proof(string $topup): StreamedResponse
    {
        $found = WalletTopup::query()->findOrFail($topup);
        abort_if($found->proof_path === null, 404);

        return Storage::disk('local')->response($found->proof_path, null, ['Content-Type' => 'image/webp', 'Cache-Control' => 'private, no-store']);
    }

    /** Confirms the transfer; the amount that actually arrived may differ from the one claimed. */
    public function approve(Request $request, string $topup): JsonResponse
    {
        $data = $request->validate(['amount' => ['nullable', 'integer', 'min:1', 'max:10000000']]);
        $found = DB::transaction(function () use ($topup, $data, $request): WalletTopup {
            $found = $this->pending($topup);
            $amount = $data['amount'] ?? $found->amount;
            $by = $request->user()?->name;
            $this->wallets->post($found->learner_id, 'transfer', $amount, $found->id, 'شحن بتحويل', $by);
            $found->update(['status' => 'approved', 'approved_amount' => $amount, 'decided_by_name' => $by, 'decided_at' => now()]);
            $name = $this->learners->find($found->learner_id)['name'] ?? '';
            $this->auditor->record('wallet.topup_approved', "تأكيد تحويل {$name} بـ ".Money::format($amount), $found);

            return $found;
        });

        return response()->json(['data' => $found->toSummary()]);
    }

    public function reject(Request $request, string $topup): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:2', 'max:200']], [], ['reason' => 'السبب']);
        $found = DB::transaction(function () use ($topup, $data, $request): WalletTopup {
            $found = $this->pending($topup);
            $found->update(['status' => 'rejected', 'reason' => $data['reason'], 'decided_by_name' => $request->user()?->name, 'decided_at' => now()]);
            $this->auditor->record('wallet.topup_rejected', 'رفض تحويل شحن بـ '.Money::format($found->amount).": {$data['reason']}", $found);

            return $found;
        });

        return response()->json(['data' => $found->toSummary()]);
    }

    public function statement(Request $request): JsonResponse
    {
        $data = $request->validate(['learner_id' => ['required', 'uuid']]);
        $learner = $this->learners->find($data['learner_id']) ?? abort(404);

        return response()->json(['data' => [
            'learner_id' => $learner['learner_id'],
            'name' => $learner['name'],
            'balance' => $this->wallets->balance($learner['learner_id']),
            'transactions' => WalletTransaction::query()->where('learner_id', $learner['learner_id'])->orderByDesc('seq')->limit(500)->get()
                ->map(fn (WalletTransaction $t) => $t->toSummary())->all(),
        ]]);
    }

    public function adjust(Request $request): JsonResponse
    {
        $data = $request->validate([
            'learner_id' => ['required', 'uuid'],
            'amount' => ['required', 'integer', 'not_in:0', 'min:-10000000', 'max:10000000'],
            'note' => ['required', 'string', 'min:2', 'max:200'],
        ], [], ['note' => 'السبب']);
        $learner = $this->learners->find($data['learner_id']) ?? abort(404);

        $tx = DB::transaction(function () use ($learner, $data, $request): array {
            $tx = $this->wallets->post($learner['learner_id'], 'adjustment', $data['amount'], null, $data['note'], $request->user()?->name);
            $this->auditor->record('wallet.adjusted', "تعديل رصيد {$learner['name']} بـ ".($data['amount'] > 0 ? '+' : '−').Money::format(abs($data['amount'])).": {$data['note']}", null, ['transaction_id' => $tx['id']], $this->tenant->idOrFail());

            return $tx;
        });

        return response()->json(['data' => $tx], 201);
    }

    public function settings(): JsonResponse
    {
        return response()->json(['data' => WalletSettings::current()->toSummary()]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'transfers_enabled' => ['sometimes', 'boolean'],
            'instapay' => ['sometimes', 'nullable', 'string', 'max:60'],
            'wallet_number' => ['sometimes', 'nullable', 'regex:/^01[0125][0-9]{8}$/'],
            'instructions' => ['sometimes', 'nullable', 'string', 'max:500'],
            'min_topup' => ['sometimes', 'integer', 'min:100', 'max:1000000'],
        ], [], ['wallet_number' => 'رقم المحفظة']);
        $settings = WalletSettings::current();
        $settings->fill($data);
        if ($settings->transfers_enabled && $settings->instapay === null && $settings->wallet_number === null) {
            throw new DomainRuleException('اكتب حساب InstaPay أو رقم محفظة عشان الطلاب يحوّلوا عليه.', 'transfer_details_missing');
        }
        $settings->save();

        return response()->json(['data' => $settings->toSummary()]);
    }

    private function pending(string $id): WalletTopup
    {
        $found = WalletTopup::query()->lockForUpdate()->findOrFail($id);
        if ($found->status !== 'pending') {
            throw new DomainRuleException('التحويل ده اتراجع قبل كده.', 'topup_decided');
        }

        return $found;
    }
}
