<?php

declare(strict_types=1);

namespace App\Modules\Learners\Contracts;

/**
 * For other modules: the signed-in student and the account's students. A student is an array:
 * {learner_id, profile_id, name, phone, grade_node_id, parent_phone, blocked}.
 */
interface LearnerDirectory
{
    /** @return array<string, mixed>|null the student on this request (learner routes), null otherwise */
    public function current(): ?array;

    /** @return array<string, mixed>|null this account's student */
    public function find(string $learnerId): ?array;
}
