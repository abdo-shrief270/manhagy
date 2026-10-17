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
 * @property string $stream_key
 * @property string $status idle | live | ended
 * @property string $chat_mode open | questions | off
 * @property Carbon|null $started_at
 * @property Carbon|null $last_published_at
 * @property Carbon|null $ended_at
 * @property int $peak_viewers
 * @property string $recording none | collecting | processing | ready | failed
 * @property string|null $replay_media_id
 * @property string|null $recording_error
 */
#[Fillable(['tenant_id', 'lesson_id', 'stream_key', 'status', 'chat_mode', 'started_at', 'last_published_at', 'ended_at', 'peak_viewers', 'recording', 'replay_media_id', 'recording_error'])]
final class LiveSession extends Model
{
    use BelongsToTenant, HasUuids;

    public const CHAT_MODES = ['open', 'questions', 'off'];

    protected $attributes = ['status' => 'idle', 'chat_mode' => 'open', 'peak_viewers' => 0, 'recording' => 'none'];

    protected $hidden = ['stream_key'];

    protected function casts(): array
    {
        return ['stream_key' => 'encrypted', 'started_at' => 'datetime', 'last_published_at' => 'datetime', 'ended_at' => 'datetime', 'peak_viewers' => 'integer'];
    }

    /** Where it is published and read on the live server. */
    public function path(): string
    {
        return "live/{$this->id}";
    }
}
