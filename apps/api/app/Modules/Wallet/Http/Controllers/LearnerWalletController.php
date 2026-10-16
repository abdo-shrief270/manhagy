<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Http\Controllers;

use App\Modules\Access\Contracts\Enrollments;
use App\Modules\Courses\Contracts\CourseCatalog;
use App\Modules\Learners\Contracts\LearnerDirectory;
use App\Modules\Notifications\Contracts\Notifications;
use App\Modules\Wallet\Contracts\LearnerWallets;
use App\Modules\Wallet\Events\CoursePurchased;
use App\Modules\Wallet\Models\WalletSettings;
use App\Modules\Wallet\Models\WalletTopup;
use App\Modules\Wallet\Models\WalletTransaction;
use App\Modules\Wallet\Support\Money;
use App\Support\Events\EventRecorder;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Media\WebpImage;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** The student's wallet on this platform: balance, history, a transfer to confirm, buying a course. */
final class LearnerWalletController
{
    public function __construct(
        private readonly LearnerDirectory $learners,
        private readonly LearnerWallets $wallets,
    ) {}

    public function show(): JsonResponse
    {
        $learner = $this->learner();
        $settings = WalletSettings::current();

        return response()->json(['data' => [
            'balance' => $this->wallets->balance($learner['learner_id']),
            'transactions' => WalletTransaction::query()->where('learner_id', $learner['learner_id'])->orderByDesc('seq')->limit(50)->get()
                ->map(fn (WalletTransaction $t) => $t->toSummary())->all(),
            'topups' => WalletTopup::query()->where('learner_id', $learner['learner_id'])->latest()->limit(10)->get()
                ->map(fn (WalletTopup $t) => $t->toSummary())->all(),
            'transfer' => $settings->transfers_enabled ? $settings->toSummary() : null,
        ]]);
    }

    public function requestTopup(Request $request, Notifications $notifications): JsonResponse
    {
        $learner = $this->learner();
        $settings = WalletSettings::current();
        if (! $settings->transfers_enabled) {
            throw new DomainRuleException('الشحن بالتحويل مش متاح على المنصة دي.', 'transfers_closed');
        }
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:'.$settings->min_topup, 'max:10000000'],
            'method' => ['required', Rule::in(WalletTopup::METHODS)],
            'reference' => ['nullable', 'string', 'max:64'],
            'proof' => ['required', 'image', 'max:5120'],
        ], [], ['amount' => 'المبلغ', 'proof' => 'صورة التحويل']);
        $data['amount'] = (int) $data['amount']; // a multipart form sends it as text
        if (WalletTopup::query()->where('learner_id', $learner['learner_id'])->where('status', 'pending')->count() >= 3) {
            throw new DomainRuleException('عندك 3 تحويلات لسه بتتراجع. استنى لما يتأكدوا.', 'topups_pending');
        }

        $topup = DB::transaction(function () use ($learner, $data, $notifications): WalletTopup {
            $id = (string) Str::uuid7();
            $path = "wallet/{$id}.webp";
            Storage::disk('local')->put($path, WebpImage::read((string) file_get_contents($data['proof']->getRealPath()))->webp(1600));
            $topup = WalletTopup::query()->create([
                'id' => $id, 'learner_id' => $learner['learner_id'], 'amount' => $data['amount'], 'method' => $data['method'],
                'reference' => $data['reference'] ?? null, 'proof_path' => $path,
            ]);
            $notifications->notify('wallet.topup_requested', 'تحويل شحن محفظة', "{$learner['name']} حوّل ".Money::format($data['amount']).' — راجعه وأكّده.', 'i-lucide-wallet', '/wallet/topups', 'wallet.confirm');

            return $topup;
        });

        return response()->json(['data' => $topup->toSummary()], 201);
    }

    /** Buying a course from the balance (a free course just opens). */
    public function buy(Request $request, string $course, CourseCatalog $courses, Enrollments $enrollments, EventRecorder $events, CurrentTenant $tenant): JsonResponse
    {
        // Where the student found the course: «market» = from the marketplace (the platform's commission).
        $via = $request->validate(['via' => ['nullable', Rule::in(['market'])]])['via'] ?? null;
        $learner = $this->learner();
        $found = $courses->course($course) ?? abort(404);
        if ($found['access_days'] === null && $enrollments->hasAccess($learner['learner_id'], $found['id'])) {
            throw new DomainRuleException('انت مشترك في الكورس ده فعلاً.', 'already_enrolled');
        }

        $result = DB::transaction(function () use ($learner, $found, $enrollments, $events, $tenant, $via): array {
            if ($found['price'] === 0) {
                return ['enrollment' => $enrollments->grant($learner['learner_id'], $found['id'], 'free')];
            }
            $tx = $this->wallets->post($learner['learner_id'], 'purchase', -$found['price'], $found['id'], "شراء «{$found['title']}»");
            $events->record(new CoursePurchased((string) $tenant->id(), $learner['learner_id'], $found['id'], $found['price'], $tx['id'], $via));

            return ['enrollment' => $enrollments->grant($learner['learner_id'], $found['id'], 'wallet', reference: $tx['id']), 'balance' => $tx['balance_after']];
        });

        return response()->json(['data' => $result], 201);
    }

    /** @return array<string, mixed> */
    private function learner(): array
    {
        return $this->learners->current() ?? abort(401);
    }
}
