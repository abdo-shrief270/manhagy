<?php

declare(strict_types=1);

namespace App\Modules\Access\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $learner_id
 * @property string $lesson_id
 * @property string|null $device_id
 * @property int|null $duration_seconds
 * @property int $watched_seconds
 * @property int $position
 * @property bool $counted
 * @property string|null $ip
 * @property Carbon $started_at
 * @property Carbon $last_beat_at
 * @property Carbon|null $ended_at
 * @property string|null $ended_reason stopped | replaced | expired
 */
#[Fillable(['tenant_id', 'learner_id', 'lesson_id', 'device_id', 'duration_seconds', 'watched_seconds', 'position', 'counted', 'ip', 'started_at', 'last_beat_at', 'ended_at', 'ended_reason'])]
final class PlaybackSession extends Model
{
    use BelongsToTenant, HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer', 'watched_seconds' => 'integer', 'position' => 'integer', 'counted' => 'boolean',
            'started_at' => 'datetime', 'last_beat_at' => 'datetime', 'ended_at' => 'datetime',
        ];
    }
}
