<?php

declare(strict_types=1);

namespace App\Modules\Center\Models;

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
 * @property string $learner_id
 * @property string $group_id
 * @property string|null $session_id
 * @property string $kind session | month
 * @property string|null $period
 * @property int $amount
 * @property string|null $reverses_id
 * @property string|null $user_id
 * @property string|null $by_name
 * @property string|null $handover_id
 * @property Carbon $created_at
 */
#[Fillable(['id', 'tenant_id', 'learner_id', 'group_id', 'session_id', 'kind', 'period', 'amount', 'reverses_id', 'user_id', 'by_name', 'handover_id', 'created_at'])]
final class Payment extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'center_payments';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['seq' => 'integer', 'amount' => 'integer', 'created_at' => 'datetime'];
    }
}
