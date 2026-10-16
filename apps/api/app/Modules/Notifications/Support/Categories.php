<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

/**
 * The kinds of notification a user can mute for push (the bell always shows them). A
 * notification's category comes from its type; `permission` = who could receive it at all.
 */
final class Categories
{
    /** @var array<string, array{label: string, description: string, permission: string, prefixes: list<string>, urgent?: bool}> */
    public const ALL = [
        'billing' => [
            'label' => 'تجديد الاشتراك',
            'description' => 'قبل ما الاشتراك أو التجربة تخلص، ولو اتأخر التجديد.',
            'permission' => 'owner',
            'prefixes' => ['billing.'],
        ],
        'wallet' => [
            'label' => 'تحويلات الطلاب',
            'description' => 'لما طالب يحوّل فلوس ويستنى تأكيد الشحن.',
            'permission' => 'wallet.confirm',
            'prefixes' => ['wallet.'],
        ],
        'questions' => [
            'label' => 'أسئلة الطلاب',
            'description' => 'لما طالب يسأل تحت درس.',
            'permission' => 'engagement.answer',
            'prefixes' => ['engagement.'],
        ],
    ];

    public static function of(string $type): ?string
    {
        foreach (self::ALL as $key => $category) {
            foreach ($category['prefixes'] as $prefix) {
                if (str_starts_with($type, $prefix)) {
                    return $key;
                }
            }
        }

        return null;
    }
}
