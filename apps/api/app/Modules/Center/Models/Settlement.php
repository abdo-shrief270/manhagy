<?php

declare(strict_types=1);

namespace App\Modules\Center\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Append-only.
 *
 * @property string $id
 * @property string $teacher_id
 * @property Carbon $period_from
 * @property Carbon $period_to
 * @property int $due
 * @property int $paid
 * @property string|null $note
 * @property string|null $by_name
 * @property Carbon $created_at
 */
#[Fillable(['tenant_id', 'teacher_id', 'period_from', 'period_to', 'due', 'paid', 'note', 'by_name', 'created_at'])]
final class Settlement extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'center_settlements';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['seq' => 'integer', 'period_from' => 'date', 'period_to' => 'date', 'due' => 'integer', 'paid' => 'integer', 'created_at' => 'datetime'];
    }
}
