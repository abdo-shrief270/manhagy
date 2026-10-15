<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $lesson_id
 * @property int $duration_minutes
 * @property Carbon|null $opens_at
 * @property Carbon|null $closes_at
 * @property int $attempts_allowed
 * @property bool $shuffle_questions
 * @property bool $shuffle_options
 * @property int $pass_percent
 * @property string $show_answers after_submit | after_close | never
 */
#[Fillable(['tenant_id', 'lesson_id', 'duration_minutes', 'opens_at', 'closes_at', 'attempts_allowed', 'shuffle_questions', 'shuffle_options', 'pass_percent', 'show_answers'])]
final class Exam extends Model
{
    use BelongsToTenant, HasUuids;

    public const SHOW_ANSWERS = ['after_submit', 'after_close', 'never'];

    protected $attributes = ['attempts_allowed' => 1, 'shuffle_questions' => true, 'shuffle_options' => true, 'pass_percent' => 50, 'show_answers' => 'after_close'];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer', 'opens_at' => 'datetime', 'closes_at' => 'datetime', 'attempts_allowed' => 'integer',
            'shuffle_questions' => 'boolean', 'shuffle_options' => 'boolean', 'pass_percent' => 'integer',
        ];
    }

    /** @return HasMany<ExamQuestion, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ExamQuestion::class)->orderBy('sort');
    }

    public function answersVisible(bool $submitted): bool
    {
        return $submitted && match ($this->show_answers) {
            'after_submit' => true,
            'after_close' => $this->closes_at !== null && $this->closes_at->isPast(),
            default => false,
        };
    }
}
