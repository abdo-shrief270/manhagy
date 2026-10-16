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
 * @property Carbon $starts_at
 * @property int $minutes
 * @property string $status scheduled | cancelled
 * @property string|null $topic
 */
#[Fillable(['tenant_id', 'group_id', 'starts_at', 'minutes', 'status', 'topic'])]
final class Session extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'center_sessions';

    protected $attributes = ['status' => 'scheduled', 'minutes' => 90];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'minutes' => 'integer'];
    }
}
