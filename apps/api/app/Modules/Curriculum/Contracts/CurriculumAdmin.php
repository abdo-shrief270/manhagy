<?php

declare(strict_types=1);

namespace App\Modules\Curriculum\Contracts;

/** The platform admins keep the curriculum tree. */
interface CurriculumAdmin
{
    public const STATUSES = ['published', 'review', 'hidden'];

    /** The tree's levels (Enums\NodeKind values). */
    public const KINDS = ['stage', 'grade', 'track', 'subject', 'university', 'faculty', 'department', 'year', 'course', 'unit', 'lesson', 'field', 'skill'];

    /**
     * Children of a node (null = roots) in every status with how many children each has, or with
     * `status` alone every node in that status anywhere (with its `label`).
     *
     * @param  array{parent?: string|null, status?: string|null, q?: string|null}  $filters
     * @return list<array<string, mixed>>
     */
    public function browse(array $filters): array;

    /**
     * @param  array{parent_id?: string|null, kind?: string, name?: string, code?: string|null, status?: string, sort?: int}  $data
     * @return array<string, mixed>
     */
    public function save(?string $id, array $data): array;

    /** Refused (`curriculum_has_children`) while something hangs under it. */
    public function delete(string $id): void;

    public function toReview(): int;
}
