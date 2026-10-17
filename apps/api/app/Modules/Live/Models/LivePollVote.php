<?php

declare(strict_types=1);

namespace App\Modules\Live\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $poll_id
 * @property string $learner_id
 * @property int $option
 */
#[Fillable(['tenant_id', 'poll_id', 'learner_id', 'option', 'created_at'])]
final class LivePollVote extends Model
{
    use BelongsToTenant, HasUuids;

    public const UPDATED_AT = null;
}
