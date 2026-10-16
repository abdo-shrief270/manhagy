<?php

declare(strict_types=1);

namespace App\Modules\Access\Support;

use App\Modules\Access\Contracts\Enrollments;
use App\Modules\Access\Models\Enrollment;
use App\Modules\Courses\Contracts\CourseCatalog;
use App\Support\Exceptions\DomainRuleException;
use InvalidArgumentException;

final class EnrollmentBook implements Enrollments
{
    public function __construct(private readonly CourseCatalog $courses) {}

    public function grant(string $learnerId, string $courseId, string $source, ?int $days = null, ?string $byName = null, ?string $reference = null): array
    {
        if (! in_array($source, Enrollment::SOURCES, true)) {
            throw new InvalidArgumentException("Unknown enrollment source [{$source}].");
        }
        $course = $this->courses->course($courseId, evenUnpublished: true);
        if ($course === null || $course['status'] === 'archived') {
            throw new DomainRuleException('الكورس ده مقفول.', 'course_closed');
        }
        $days ??= $course['access_days'];

        $current = Enrollment::query()->where('learner_id', $learnerId)->where('course_id', $courseId)->active()
            ->orderByRaw('ends_at is null desc')->orderByDesc('ends_at')->lockForUpdate()->first();
        if ($current !== null) {
            if ($current->ends_at !== null) {
                $current->update(['ends_at' => $days === null ? null : $current->ends_at->copy()->addDays($days)]);
            }

            return $current->toSummary();
        }

        return Enrollment::query()->create([
            'learner_id' => $learnerId, 'course_id' => $courseId, 'source' => $source, 'reference' => $reference,
            'starts_at' => now(), 'ends_at' => $days === null ? null : now()->addDays($days), 'granted_by_name' => $byName,
        ])->toSummary();
    }

    public function hasAccess(string $learnerId, string $courseId): bool
    {
        return Enrollment::query()->where('learner_id', $learnerId)->where('course_id', $courseId)->active()->exists();
    }

    public function activeCourseIds(string $learnerId): array
    {
        return Enrollment::query()->where('learner_id', $learnerId)->active()->distinct()->pluck('course_id')->all();
    }

    public function wasEnrolled(string $learnerId, string $courseId): bool
    {
        return Enrollment::query()->where('learner_id', $learnerId)->where('course_id', $courseId)->whereNull('revoked_at')->exists();
    }

    public function studentCounts(array $courseIds): array
    {
        if ($courseIds === []) {
            return [];
        }

        return Enrollment::withoutTenancy()->whereIn('course_id', $courseIds)->whereNull('revoked_at')
            ->selectRaw('course_id, count(distinct learner_id) as students')->groupBy('course_id')
            ->pluck('students', 'course_id')->map(fn ($n) => (int) $n)->all();
    }
}
