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
 * @property string $user_id
 * @property string $user_name
 * @property int $expected
 * @property int $counted
 * @property int $difference
 * @property string|null $note
 * @property string|null $received_by_name
 * @property Carbon $created_at
 */
#[Fillable(['tenant_id', 'user_id', 'user_name', 'expected', 'counted', 'difference', 'note', 'received_by_name', 'created_at'])]
final class Handover extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'center_handovers';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['expected' => 'integer', 'counted' => 'integer', 'difference' => 'integer', 'created_at' => 'datetime'];
    }
}
