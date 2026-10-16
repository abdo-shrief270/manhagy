<?php

declare(strict_types=1);

namespace App\Modules\Identity\Enums;

/**
 * What an account is (a teacher, a center, and later the LMS institutions). Each optional module
 * says which kinds it is for (ModuleManifest::$shopTypes). Teachers, centers and skills
 * trainers can sign up; the institution kinds exist so the data and permissions are ready for the LMS phase.
 */
enum AccountKind: string
{
    case Teacher = 'teacher';
    case Center = 'center';
    case Trainer = 'trainer';
    case Institute = 'institute';
    case School = 'school';
    case College = 'college';
    case University = 'university';

    public function label(): string
    {
        return match ($this) {
            self::Teacher => 'مدرس',
            self::Center => 'سنتر',
            self::Trainer => 'مدرب مهارات',
            self::Institute => 'معهد',
            self::School => 'مدرسة',
            self::College => 'كلية',
            self::University => 'جامعة',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Teacher => 'بتشرح لطلابك أونلاين أو في سنتر',
            self::Center => 'قاعات ومدرسين ومجموعات',
            self::Trainer => 'برمجة، جرافيك، لغات، تسويق… لأي حد عايز يتعلّم',
            self::Institute => 'معهد أو أكاديمية',
            self::School => 'مدرسة بفصولها ومدرسينها',
            self::College => 'كلية بأقسامها ومقرراتها',
            self::University => 'جامعة بكلياتها',
        };
    }

    /** @return list<self> what an account can pick today */
    public static function selectable(): array
    {
        return [self::Teacher, self::Center, self::Trainer];
    }

    /** @return list<self> */
    public function parts(): array
    {
        return [$this];
    }

    /**
     * @param  list<self>  $types
     */
    public static function labels(array $types): string
    {
        return implode(' + ', array_map(fn (self $t) => $t->label(), $types));
    }
}
