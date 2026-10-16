<?php

declare(strict_types=1);

namespace App\Modules\Access\Support;

use App\Modules\Access\Contracts\Enrollments;
use App\Modules\Access\Contracts\LessonAccess;
use App\Modules\Access\Models\LessonProgress;
use App\Modules\Courses\Contracts\CourseCatalog;

final class LessonGate implements LessonAccess
{
    public function __construct(private readonly CourseCatalog $courses, private readonly Enrollments $enrollments) {}

    public function lockReason(string $learnerId, string $lessonId): ?string
    {
        $lesson = $this->courses->lesson($lessonId);
        if ($lesson === null || $lesson['course_status'] !== 'published') {
            abort(404);
        }
        $outline = $this->courses->outline($lesson['course_id']);
        $progress = LessonProgress::query()->where('learner_id', $learnerId)->whereIn('lesson_id', array_column($outline, 'id'))->get()->keyBy('lesson_id')->all();

        return Rules::lockReason($lesson, $outline, $this->enrollments->hasAccess($learnerId, $lesson['course_id']), $progress);
    }

    public function complete(string $learnerId, string $lessonId): void
    {
        $row = LessonProgress::query()->firstOrCreate(['learner_id' => $learnerId, 'lesson_id' => $lessonId]);
        $row->update(['completed_at' => $row->completed_at ?? now(), 'last_viewed_at' => now()]);
    }

    public function completed(string $learnerId, array $lessonIds): array
    {
        return $lessonIds === [] ? [] : LessonProgress::query()->where('learner_id', $learnerId)->whereIn('lesson_id', $lessonIds)
            ->whereNotNull('completed_at')->pluck('lesson_id')->all();
    }
}
