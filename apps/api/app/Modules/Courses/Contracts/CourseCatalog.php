<?php

declare(strict_types=1);

namespace App\Modules\Courses\Contracts;

/**
 * For other modules (Access, Codes…): the current account's courses as arrays.
 * A course: {id, title, description, price, access_days, status, grade_node_id, grade, lessons_count, free_previews}.
 * A lesson: {id, course_id, chapter_id, chapter_title, kind, title, media_id, duration_seconds, free_preview,
 * publish_at, requires_previous, max_views, count_percent, position, previous_id}.
 */
interface CourseCatalog
{
    /** @return list<array<string, mixed>> published courses, in the teacher's order */
    public function published(): array;

    /** @return array<string, mixed>|null */
    public function course(string $id, bool $evenUnpublished = false): ?array;

    /** @return list<array<string, mixed>> the course's lessons in order (chapter, then lesson) */
    public function outline(string $courseId): array;

    /** @return array<string, mixed>|null the lesson, with its course's status as course_status */
    public function lesson(string $id): ?array;
}
