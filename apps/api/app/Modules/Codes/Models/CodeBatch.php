<?php

declare(strict_types=1);

namespace App\Modules\Codes\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $number
 * @property string|null $label
 * @property string $kind course | balance
 * @property string|null $course_id
 * @property int|null $days
 * @property int $value
 * @property int $unit_price
 * @property int $count
 * @property Carbon|null $expires_at
 * @property string|null $distributor_id
 * @property string $status active | cancelled
 * @property string|null $cancelled_reason
 * @property string|null $created_by_name
 * @property Carbon $created_at
 */
#[Fillable(['tenant_id', 'number', 'label', 'kind', 'course_id', 'days', 'value', 'unit_price', 'count', 'expires_at', 'distributor_id', 'status', 'cancelled_reason', 'created_by_name'])]
final class CodeBatch extends Model
{
    use BelongsToTenant, HasUuids;

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return ['number' => 'integer', 'days' => 'integer', 'value' => 'integer', 'unit_price' => 'integer', 'count' => 'integer', 'expires_at' => 'datetime'];
    }

    public function code(): string
    {
        return sprintf('CB-%05d', $this->number);
    }
}
