<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Contracts;

/** For other modules (Codes): a student's balance on the current platform, moved inside the caller's transaction. */
interface LearnerWallets
{
    public function balance(string $learnerId): int;

    /**
     * @param  string  $type  code | transfer | purchase | adjustment
     * @param  int  $amount  piasters, + adds, − takes (refused below zero: wallet_insufficient)
     * @return array<string, mixed> the transaction
     */
    public function post(string $learnerId, string $type, int $amount, ?string $reference = null, ?string $note = null, ?string $byName = null): array;
}
