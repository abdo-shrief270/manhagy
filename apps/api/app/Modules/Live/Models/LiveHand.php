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
 * @property string $name
 * @property string $status raised | speaking | done
 * @property bool $camera
 * @property Carbon $raised_at
 * @property Carbon|null $decided_at
 */
#[Fillable(['tenant_id', 'session_id', 'learner_id', 'name', 'status', 'camera', 'raised_at', 'decided_at'])]
final class LiveHand extends Model
{
    use BelongsToTenant, HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['camera' => 'boolean', 'raised_at' => 'datetime', 'decided_at' => 'datetime'];
    }

    /** @return array<string, mixed> */
    public function toSummary(): array
    {
        return [
            'learner_id' => $this->learner_id, 'identity' => 's:'.$this->learner_id, 'name' => $this->name,
            'status' => $this->status, 'camera' => $this->camera, 'raised_at' => $this->raised_at->toIso8601String(),
        ];
    }
}
