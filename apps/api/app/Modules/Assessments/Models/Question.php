<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $course_id
 * @property string|null $chapter_id
 * @property string $kind mcq | true_false | fill
 * @property string $body
 * @property list<array{id: string, text: string}>|null $options
 * @property mixed $answer
 * @property string|null $explanation
 * @property int $points
 * @property string $difficulty easy | medium | hard
 * @property bool $active
 */
#[Fillable(['tenant_id', 'course_id', 'chapter_id', 'kind', 'body', 'options', 'answer', 'explanation', 'points', 'difficulty', 'active'])]
final class Question extends Model
{
    use BelongsToTenant, HasUuids;

    public const KINDS = ['mcq', 'true_false', 'fill'];

    public const DIFFICULTIES = ['easy', 'medium', 'hard'];

    protected $attributes = ['points' => 1, 'difficulty' => 'medium', 'active' => true];

    protected function casts(): array
    {
        return ['options' => 'array', 'answer' => 'json', 'points' => 'integer', 'active' => 'boolean'];
    }

    /** @return array<string, mixed> the teacher's view, answer included */
    public function toSummary(): array
    {
        return [
            'id' => $this->id, 'course_id' => $this->course_id, 'chapter_id' => $this->chapter_id, 'kind' => $this->kind,
            'body' => $this->body, 'options' => $this->options, 'answer' => $this->answer, 'explanation' => $this->explanation,
            'points' => $this->points, 'difficulty' => $this->difficulty, 'active' => $this->active,
        ];
    }
}
