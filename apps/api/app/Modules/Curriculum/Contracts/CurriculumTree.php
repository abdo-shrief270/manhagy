<?php

declare(strict_types=1);

namespace App\Modules\Curriculum\Contracts;

/**
 * The platform's curriculum tree, read by everyone: a teacher's course hangs on one of its nodes,
 * the marketplace and the student's «اختار صفك» browse it. Nodes are arrays:
 * {id, parent_id, kind, kind_label, name, code, status, sort, has_children}.
 */
interface CurriculumTree
{
    /** @return list<array<string, mixed>> published children of a node (null = the roots), in order */
    public function children(?string $parentId): array;

    /** @return array<string, mixed>|null a published node, or any node with $evenUnpublished */
    public function node(string $id, bool $evenUnpublished = false): ?array;

    /** @return list<array<string, mixed>> the nodes from the root down to this one */
    public function path(string $id): array;

    /** «تالت ثانوي › علمي علوم › فيزياء» */
    public function label(string $id): string;

    /** @return list<array<string, mixed>> published nodes whose name matches every word, each with its `label` */
    public function search(string $query, ?string $kind = null, int $limit = 20): array;

    /**
     * A teacher adds what is missing under a node: it waits for the platform admins («للمراجعة»)
     * but its suggester may use it meanwhile. The same name under the same parent = that node.
     *
     * @return array<string, mixed>
     */
    public function suggest(string $parentId, string $kind, string $name, ?string $code, string $tenantId, string $byName): array;
}
