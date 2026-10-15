<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $learner_id
 * @property int $amount
 * @property string $method instapay | wallet | other
 * @property string|null $reference
 * @property string|null $proof_path
 * @property string $status pending | approved | rejected
 * @property int|null $approved_amount
 * @property string|null $reason
 * @property string|null $decided_by_name
 * @property Carbon|null $decided_at
 * @property Carbon $created_at
 */
#[Fillable(['id', 'tenant_id', 'learner_id', 'amount', 'method', 'reference', 'proof_path', 'status', 'approved_amount', 'reason', 'decided_by_name', 'decided_at'])]
final class WalletTopup extends Model
{
    use BelongsToTenant, HasUuids;

    public const METHODS = ['instapay', 'wallet', 'other'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'approved_amount' => 'integer', 'decided_at' => 'datetime'];
    }

    /** @return array<string, mixed> */
    public function toSummary(): array
    {
        return [
            'id' => $this->id, 'learner_id' => $this->learner_id, 'amount' => $this->amount, 'method' => $this->method,
            'reference' => $this->reference, 'has_proof' => $this->proof_path !== null, 'status' => $this->status,
            'approved_amount' => $this->approved_amount, 'reason' => $this->reason, 'decided_by_name' => $this->decided_by_name,
            'decided_at' => $this->decided_at?->toIso8601String(), 'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
