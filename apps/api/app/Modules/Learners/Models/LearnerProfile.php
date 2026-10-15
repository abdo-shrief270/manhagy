<?php

declare(strict_types=1);

namespace App\Modules\Learners\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The student as one teacher sees them.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $learner_id
 * @property string|null $grade_node_id
 * @property string|null $parent_phone
 * @property string|null $notes
 * @property Carbon|null $consent_at
 * @property Carbon|null $blocked_at
 * @property string|null $blocked_reason
 * @property Carbon|null $last_seen_at
 * @property Carbon $created_at
 * @property-read Learner $learner
 */
#[Fillable(['tenant_id', 'learner_id', 'grade_node_id', 'parent_phone', 'notes', 'consent_at', 'blocked_at', 'blocked_reason', 'last_seen_at'])]
final class LearnerProfile extends Model
{
    use BelongsToTenant, HasUuids;

    protected function casts(): array
    {
        return ['consent_at' => 'datetime', 'blocked_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }

    /** @return BelongsTo<Learner, $this> */
    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    /** @return HasMany<LearnerDevice, $this> */
    public function devices(): HasMany
    {
        return $this->hasMany(LearnerDevice::class, 'learner_id', 'learner_id');
    }
}
