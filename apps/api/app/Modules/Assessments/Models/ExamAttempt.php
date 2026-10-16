<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $exam_id
 * @property string $learner_id
 * @property int $number
 * @property int $seed
 * @property Carbon $started_at
 * @property Carbon $ends_at
 * @property Carbon|null $submitted_at
 * @property string $status in_progress | submitted
 * @property string|null $submitted_by learner | timer
 * @property int|null $score
 * @property int $max_score
 * @property int|null $percent
 * @property int $leaves
 * @property int $pending_grading manual answers still to grade
 * @property Carbon|null $graded_at
 * @property-read Exam $exam
 */
#[Fillable(['tenant_id', 'exam_id', 'learner_id', 'number', 'seed', 'started_at', 'ends_at', 'submitted_at', 'status', 'submitted_by', 'score', 'max_score', 'percent', 'leaves', 'pending_grading', 'graded_at'])]
final class ExamAttempt extends Model
{
    use BelongsToTenant, HasUuids;

    protected $attributes = ['status' => 'in_progress', 'leaves' => 0, 'pending_grading' => 0];

    protected function casts(): array
    {
        return [
            'number' => 'integer', 'seed' => 'integer', 'started_at' => 'datetime', 'ends_at' => 'datetime', 'submitted_at' => 'datetime',
            'score' => 'integer', 'max_score' => 'integer', 'percent' => 'integer', 'leaves' => 'integer', 'pending_grading' => 'integer', 'graded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Exam, $this> */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /** @return HasMany<AttemptAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class, 'attempt_id');
    }
}
