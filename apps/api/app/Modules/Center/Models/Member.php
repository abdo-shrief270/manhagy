<?php

declare(strict_types=1);

namespace App\Modules\Center\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $group_id
 * @property string $learner_id
 * @property string $plan session | monthly
 * @property Carbon $joined_at
 * @property Carbon|null $left_at
 */
#[Fillable(['tenant_id', 'group_id', 'learner_id', 'plan', 'joined_at', 'left_at'])]
final class Member extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'center_members';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['joined_at' => 'datetime', 'left_at' => 'datetime'];
    }
}
