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
 * @property string $tenant_id
 * @property string $lesson_id
 * @property string $status idle | live | ended
 * @property string $chat_mode open | questions | off
 * @property Carbon|null $started_at
 * @property Carbon|null $ended_at
 * @property int $peak_viewers
 * @property string|null $egress_id
 * @property string $recording none | recording | collecting | processing | ready | failed
 * @property string|null $replay_media_id
 * @property string|null $recording_error
 * @property string|null $ingress_id
 * @property string|null $ingress_url
 * @property string|null $ingress_key
 */
#[Fillable(['tenant_id', 'lesson_id', 'status', 'chat_mode', 'started_at', 'ended_at', 'peak_viewers', 'egress_id', 'recording', 'replay_media_id', 'recording_error', 'ingress_id', 'ingress_url', 'ingress_key'])]
final class LiveSession extends Model
{
    use BelongsToTenant, HasUuids;

    public const CHAT_MODES = ['open', 'questions', 'off'];

    protected $attributes = ['status' => 'idle', 'chat_mode' => 'open', 'peak_viewers' => 0, 'recording' => 'none'];

    protected $hidden = ['ingress_key'];

    protected function casts(): array
    {
        return ['ingress_key' => 'encrypted', 'started_at' => 'datetime', 'ended_at' => 'datetime', 'peak_viewers' => 'integer'];
    }

    /** Its room on the LiveKit server. */
    public function room(): string
    {
        return "live-{$this->id}";
    }
}
