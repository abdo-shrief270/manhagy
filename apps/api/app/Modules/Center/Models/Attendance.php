<?php

declare(strict_types=1);

namespace App\Modules\Center\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id the scanning device's id
 * @property string $session_id
 * @property string $learner_id
 * @property bool $late
 * @property Carbon $scanned_at
 * @property string|null $by_name
 */
#[Fillable(['id', 'tenant_id', 'session_id', 'learner_id', 'late', 'scanned_at', 'by_name', 'created_at'])]
final class Attendance extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'center_attendances';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['late' => 'boolean', 'scanned_at' => 'datetime', 'created_at' => 'datetime'];
    }
}
