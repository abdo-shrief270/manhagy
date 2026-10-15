<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Support;

use App\Modules\Wallet\Contracts\LearnerWallets;
use App\Modules\Wallet\Models\LearnerWallet;
use App\Modules\Wallet\Models\WalletTransaction;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** The wallet row and its append-only transactions move together, under the wallet's row lock. */
final class Ledger implements LearnerWallets
{
    public function balance(string $learnerId): int
    {
        return (int) (LearnerWallet::query()->where('learner_id', $learnerId)->value('balance') ?? 0);
    }

    public function post(string $learnerId, string $type, int $amount, ?string $reference = null, ?string $note = null, ?string $byName = null): array
    {
        if (! in_array($type, WalletTransaction::TYPES, true) || $amount === 0) {
            throw new InvalidArgumentException("Bad wallet posting [{$type}, {$amount}].");
        }

        return DB::transaction(function () use ($learnerId, $type, $amount, $reference, $note, $byName): array {
            LearnerWallet::query()->firstOrCreate(['learner_id' => $learnerId]);
            $wallet = LearnerWallet::query()->where('learner_id', $learnerId)->lockForUpdate()->firstOrFail();
            if ($wallet->balance + $amount < 0) {
                throw new DomainRuleException('رصيدك مش كفاية.', 'wallet_insufficient', 422, ['balance' => $wallet->balance, 'needed' => -$amount]);
            }
            $wallet->update(['balance' => $wallet->balance + $amount]);

            return WalletTransaction::query()->create([
                'learner_id' => $learnerId, 'type' => $type, 'amount' => $amount, 'balance_after' => $wallet->balance,
                'reference' => $reference, 'note' => $note, 'by_name' => $byName, 'created_at' => now(),
            ])->toSummary();
        });
    }
}
