<?php

declare(strict_types=1);

namespace App\Modules\Access\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $learner_id
 * @property string $course_id
 * @property string $source manual | code | wallet | free
 * @property string|null $reference
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $revoked_at
 * @property string|null $revoked_reason
 * @property string|null $granted_by_name
 * @property Carbon $created_at
 */
#[Fillable(['tenant_id', 'learner_id', 'course_id', 'source', 'reference', 'starts_at', 'ends_at', 'revoked_at', 'revoked_reason', 'granted_by_name'])]
final class Enrollment extends Model
{
    use BelongsToTenant, HasUuids;

    public const SOURCES = ['manual', 'code', 'wallet', 'free'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    /** @param  Builder<Enrollment>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at')->where('starts_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->starts_at->lte(now()) && ($this->ends_at === null || $this->ends_at->gt(now()));
    }

    /** @return array<string, mixed> */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'learner_id' => $this->learner_id,
            'course_id' => $this->course_id,
            'source' => $this->source,
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'active' => $this->isActive(),
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'revoked_reason' => $this->revoked_reason,
            'granted_by_name' => $this->granted_by_name,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
