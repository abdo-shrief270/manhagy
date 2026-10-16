<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Support;

use App\Modules\Assessments\Contracts\ExamResults;
use App\Modules\Assessments\Models\Exam;
use App\Modules\Assessments\Models\ExamAttempt;

final class Results implements ExamResults
{
    public function bestPercents(string $learnerId, array $lessonIds): array
    {
        if ($lessonIds === []) {
            return [];
        }
        $exams = Exam::query()->whereIn('lesson_id', $lessonIds)->pluck('lesson_id', 'id');

        return ExamAttempt::query()->where('learner_id', $learnerId)->whereIn('exam_id', $exams->keys())
            ->where('status', 'submitted')->where('pending_grading', 0)->whereNotNull('percent')
            ->selectRaw('exam_id, max(percent) as best')->groupBy('exam_id')->get()
            ->mapWithKeys(fn ($r) => [$exams[$r->exam_id] => (int) $r->best])->all();
    }
}
