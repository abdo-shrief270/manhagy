<?php

declare(strict_types=1);

namespace App\Modules\Access\Contracts;

/** For other modules (Codes, Wallet…): opening a course for a student, inside the caller's transaction. */
interface Enrollments
{
    /**
     * Opens the course for the student (or extends their current enrollment) for $days, else the
     * course's own access_days, else until the course closes.
     *
     * @param  string  $source  manual | code | wallet | free
     * @return array<string, mixed> the enrollment
     */
    public function grant(string $learnerId, string $courseId, string $source, ?int $days = null, ?string $byName = null, ?string $reference = null): array;

    public function hasAccess(string $learnerId, string $courseId): bool;

    /** @return list<string> */
    public function activeCourseIds(string $learnerId): array;

    /** The student was ever given the course (expired counts, revoked doesn't). */
    public function wasEnrolled(string $learnerId, string $courseId): bool;

    /**
     * Students ever enrolled (not revoked) per course, across accounts (the marketplace).
     *
     * @param  list<string>  $courseIds
     * @return array<string, int>
     */
    public function studentCounts(array $courseIds): array;
}
