<?php

declare(strict_types=1);

namespace App\Modules\Learners\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A device a student signed in from on one teacher's platform (at most the site's device limit).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $learner_id
 * @property string $device_hash
 * @property string $name
 * @property Carbon|null $last_seen_at
 * @property Carbon $created_at
 */
#[Fillable(['tenant_id', 'learner_id', 'device_hash', 'name', 'last_seen_at'])]
final class LearnerDevice extends Model
{
    use BelongsToTenant, HasUuids;

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }
}
