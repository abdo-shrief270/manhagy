<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $lesson_id
 * @property string $learner_id
 * @property string $body
 * @property int|null $position
 * @property string $status open | answered | hidden
 * @property string|null $answer
 * @property bool $public
 * @property string|null $answered_by_name
 * @property Carbon|null $answered_at
 * @property Carbon $created_at
 */
#[Fillable(['tenant_id', 'lesson_id', 'learner_id', 'body', 'position', 'status', 'answer', 'public', 'answered_by_name', 'answered_at'])]
final class LessonQuestion extends Model
{
    use BelongsToTenant, HasUuids;

    protected $attributes = ['status' => 'open', 'public' => false];

    protected function casts(): array
    {
        return ['position' => 'integer', 'public' => 'boolean', 'answered_at' => 'datetime'];
    }

    /** @return array<string, mixed> */
    public function toSummary(): array
    {
        return [
            'id' => $this->id, 'lesson_id' => $this->lesson_id, 'body' => $this->body, 'position' => $this->position, 'status' => $this->status,
            'answer' => $this->answer, 'public' => $this->public, 'answered_by_name' => $this->answered_by_name,
            'answered_at' => $this->answered_at?->toIso8601String(), 'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
