<?php

declare(strict_types=1);

namespace App\Modules\Access\Contracts;

/** For other modules (Assessments, Certificates): may the student open this lesson now, marking it done, what they finished. */
interface LessonAccess
{
    /** What to tell the student for each lock reason. */
    public const MESSAGES = [
        'not_yet' => 'الدرس ده لسه ما اتفتحش.',
        'not_enrolled' => 'اشترك في الكورس الأول.',
        'previous_required' => 'خلّص الدرس اللي قبله الأول.',
        'views_used_up' => 'خلصت مرات المشاهدة للدرس ده. كلّم المدرس لو محتاج تاني.',
    ];

    /**
     * Null = open; otherwise not_yet | not_enrolled | previous_required | views_used_up.
     * Aborts 404 for a lesson that isn't in a published course.
     */
    public function lockReason(string $learnerId, string $lessonId): ?string;

    public function complete(string $learnerId, string $lessonId): void;

    /**
     * @param  list<string>  $lessonIds
     * @return list<string> those the student completed
     */
    public function completed(string $learnerId, array $lessonIds): array;
}
