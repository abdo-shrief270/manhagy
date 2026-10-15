<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $learner_id
 * @property int $balance piasters
 */
#[Fillable(['tenant_id', 'learner_id', 'balance'])]
final class LearnerWallet extends Model
{
    use BelongsToTenant, HasUuids;

    protected $attributes = ['balance' => 0];

    protected function casts(): array
    {
        return ['balance' => 'integer'];
    }
}
