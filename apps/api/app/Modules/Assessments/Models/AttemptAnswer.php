<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $attempt_id
 * @property string $question_id
 * @property mixed $answer
 * @property bool|null $correct
 * @property int $points_awarded
 * @property Carbon $answered_at
 * @property string|null $photo_path
 * @property string|null $comment
 * @property string|null $graded_by_name
 * @property Carbon|null $graded_at
 */
#[Fillable(['tenant_id', 'attempt_id', 'question_id', 'answer', 'correct', 'points_awarded', 'answered_at', 'photo_path', 'comment', 'graded_by_name', 'graded_at'])]
final class AttemptAnswer extends Model
{
    use BelongsToTenant, HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['answer' => 'json', 'correct' => 'boolean', 'points_awarded' => 'integer', 'answered_at' => 'datetime', 'graded_at' => 'datetime'];
    }
}
