<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $seq
 * @property string $tenant_id
 * @property string $course_id
 * @property string $course_title
 * @property string $learner_id
 * @property string $reference
 * @property int $amount
 * @property int $rate_percent
 * @property int $commission
 * @property Carbon|null $settled_at
 * @property string|null $settled_reference
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 * @property Carbon $created_at
 */
#[Fillable(['tenant_id', 'course_id', 'course_title', 'learner_id', 'reference', 'amount', 'rate_percent', 'commission', 'settled_at', 'settled_reference', 'voided_at', 'void_reason', 'created_at'])]
final class MarketCommission extends Model
{
    use BelongsToTenant, HasUuids;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['amount' => 'integer', 'rate_percent' => 'integer', 'commission' => 'integer', 'seq' => 'integer',
            'settled_at' => 'datetime', 'voided_at' => 'datetime', 'created_at' => 'datetime'];
    }

    /** @param  Builder<MarketCommission>  $query */
    public function scopeDue(Builder $query): void
    {
        $query->whereNull('settled_at')->whereNull('voided_at');
    }

    public function state(): string
    {
        return $this->voided_at !== null ? 'void' : ($this->settled_at !== null ? 'settled' : 'due');
    }

    /** @return array<string, mixed> */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'course_title' => $this->course_title,
            'amount' => $this->amount,
            'rate_percent' => $this->rate_percent,
            'commission' => $this->commission,
            'state' => $this->state(),
            'settled_at' => $this->settled_at?->toIso8601String(),
            'settled_reference' => $this->settled_reference,
            'voided_at' => $this->voided_at?->toIso8601String(),
            'void_reason' => $this->void_reason,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
