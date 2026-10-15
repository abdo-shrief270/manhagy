<?php

declare(strict_types=1);

namespace App\Modules\Codes\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $batch_id
 * @property int $serial
 * @property string $hash
 * @property string $status unused | used | cancelled
 * @property string|null $used_by
 * @property Carbon|null $used_at
 * @property string|null $result_ref
 * @property-read CodeBatch $batch
 */
#[Fillable(['tenant_id', 'batch_id', 'serial', 'hash', 'status', 'used_by', 'used_at', 'result_ref'])]
final class Code extends Model
{
    use BelongsToTenant, HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['serial' => 'integer', 'used_at' => 'datetime'];
    }

    /** @return BelongsTo<CodeBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(CodeBatch::class, 'batch_id');
    }
}
