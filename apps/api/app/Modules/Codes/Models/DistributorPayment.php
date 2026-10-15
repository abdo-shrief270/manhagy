<?php

declare(strict_types=1);

namespace App\Modules\Codes\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Append-only: a mistake is a reversal row.
 *
 * @property string $id
 * @property int $seq
 * @property string $distributor_id
 * @property int $amount
 * @property string|null $reverses_id
 * @property string|null $note
 * @property string|null $by_name
 * @property Carbon $created_at
 */
#[Fillable(['tenant_id', 'distributor_id', 'amount', 'reverses_id', 'note', 'by_name', 'created_at'])]
final class DistributorPayment extends Model
{
    use BelongsToTenant, HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['seq' => 'integer', 'amount' => 'integer', 'created_at' => 'datetime'];
    }

    /** @return array<string, mixed> */
    public function toSummary(): array
    {
        return ['id' => $this->id, 'amount' => $this->amount, 'reverses_id' => $this->reverses_id, 'note' => $this->note, 'by_name' => $this->by_name, 'created_at' => $this->created_at->toIso8601String()];
    }
}
