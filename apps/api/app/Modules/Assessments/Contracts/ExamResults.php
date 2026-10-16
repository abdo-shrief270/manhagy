<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Contracts;

/** For other modules (Certificates): how a student did in the exams of a course. */
interface ExamResults
{
    /**
     * The best fully graded percent per exam lesson (lessons with no graded attempt are left out).
     *
     * @param  list<string>  $lessonIds
     * @return array<string, int>
     */
    public function bestPercents(string $learnerId, array $lessonIds): array;
}
