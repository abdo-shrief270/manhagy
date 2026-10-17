<?php

declare(strict_types=1);

namespace App\Modules\Live\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $session_id
 * @property string $learner_id
 * @property string|null $device_id
 * @property string|null $ip
 * @property Carbon $joined_at
 * @property Carbon $last_beat_at
 * @property int $seconds
 * @property Carbon|null $ended_at
 * @property string|null $ended_reason
 */
#[Fillable(['tenant_id', 'session_id', 'learner_id', 'device_id', 'ip', 'joined_at', 'last_beat_at', 'seconds', 'ended_at', 'ended_reason'])]
final class LiveAttendance extends Model
{
    use BelongsToTenant, HasUuids;

    public $timestamps = false;

    /** No beat for this long = the student left (closed the tab, lost the network). */
    public const STALE_SECONDS = 90;

    protected function casts(): array
    {
        return ['joined_at' => 'datetime', 'last_beat_at' => 'datetime', 'ended_at' => 'datetime', 'seconds' => 'integer'];
    }

    public function isOpen(): bool
    {
        return $this->ended_at === null && $this->last_beat_at->gt(now()->subSeconds(self::STALE_SECONDS));
    }
}
