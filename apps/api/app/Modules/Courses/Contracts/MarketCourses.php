<?php

declare(strict_types=1);

namespace App\Modules\Courses\Contracts;

use Carbon\CarbonInterface;

/**
 * For the marketplace, across accounts: what each account has published, and what changed.
 * Courses are CourseCatalog's arrays (+ published_at); a syllabus is the titles only.
 */
interface MarketCourses
{
    /** @return list<array<string, mixed>> the account's published courses */
    public function forTenant(string $tenantId): array;

    /** @return list<string> accounts whose courses, chapters or lessons changed after $since */
    public function changedSince(CarbonInterface $since): array;

    /** @return list<array{title: string, lessons: list<array{title: string, kind: string, duration_seconds: int|null, free_preview: bool}>}> */
    public function syllabus(string $tenantId, string $courseId): array;
}
