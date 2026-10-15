<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

/**
 * Roles every new account starts with. Owners can edit them or add their own. A module that adds
 * permissions for these roles later gives them to existing accounts in a migration.
 */
final class DefaultRoles
{
    /** What an assistant does for the teacher (kept to the permissions that exist). */
    private const ASSISTANT = [
        'messages.send',
        // Later modules: grading, answering questions, codes, unlocking devices…
        'assessments.grade', 'engagement.answer', 'codes.manage', 'learners.view', 'learners.devices',
    ];

    /**
     * @param  list<string>  $allPermissions
     * @return array<string, array{name: string, permissions: list<string>}>
     */
    public static function all(array $allPermissions): array
    {
        return [
            'manager' => [
                'name' => 'مدير',
                'permissions' => array_values(array_diff($allPermissions, ['roles.manage', 'audit.view'])),
            ],
            'assistant' => [
                'name' => 'مساعد',
                'permissions' => array_values(array_intersect(self::ASSISTANT, $allPermissions)),
            ],
        ];
    }
}
