<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Append-only.
 *
 * @property string $id
 * @property int $seq
 * @property string $learner_id
 * @property string $type code | transfer | purchase | adjustment
 * @property int $amount
 * @property int $balance_after
 * @property string|null $reference
 * @property string|null $note
 * @property string|null $by_name
 * @property Carbon $created_at
 */
#[Fillable(['tenant_id', 'learner_id', 'type', 'amount', 'balance_after', 'reference', 'note', 'by_name', 'created_at'])]
final class WalletTransaction extends Model
{
    use BelongsToTenant, HasUuids;

    public const TYPES = ['code', 'transfer', 'purchase', 'adjustment'];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['seq' => 'integer', 'amount' => 'integer', 'balance_after' => 'integer', 'created_at' => 'datetime'];
    }

    /** @return array<string, mixed> */
    public function toSummary(): array
    {
        return [
            'id' => $this->id, 'type' => $this->type, 'amount' => $this->amount, 'balance_after' => $this->balance_after,
            'note' => $this->note, 'by_name' => $this->by_name, 'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
