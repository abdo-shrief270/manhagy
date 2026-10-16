<?php

declare(strict_types=1);

namespace App\Modules\Curriculum\Enums;

/**
 * The levels of the curriculum tree. Before university: stage → grade → (track) → subject → unit
 * → lesson. University: university → faculty → (department) → year → course → unit → lesson.
 * Skills (trainers' courses, for anyone): field → skill → unit → lesson.
 */
enum NodeKind: string
{
    case Stage = 'stage';
    case Grade = 'grade';
    case Track = 'track';
    case Subject = 'subject';
    case University = 'university';
    case Faculty = 'faculty';
    case Department = 'department';
    case Year = 'year';
    case Course = 'course';
    case Unit = 'unit';
    case Lesson = 'lesson';
    case Field = 'field';
    case Skill = 'skill';

    public function label(): string
    {
        return match ($this) {
            self::Stage => 'مرحلة',
            self::Grade => 'صف',
            self::Track => 'شعبة',
            self::Subject => 'مادة',
            self::University => 'جامعة',
            self::Faculty => 'كلية',
            self::Department => 'قسم',
            self::Year => 'فرقة',
            self::Course => 'مقرر',
            self::Unit => 'وحدة',
            self::Lesson => 'درس',
            self::Field => 'مجال',
            self::Skill => 'مهارة',
        };
    }

    /** @return list<self> what may sit directly under this level */
    public function children(): array
    {
        return match ($this) {
            self::Stage => [self::Grade],
            self::Grade => [self::Track, self::Subject],
            self::Track => [self::Subject],
            self::Subject, self::Course, self::Skill => [self::Unit],
            self::Unit => [self::Lesson],
            self::University => [self::Faculty],
            self::Faculty => [self::Department, self::Year],
            self::Department => [self::Year],
            self::Year => [self::Course],
            self::Field => [self::Skill],
            self::Lesson => [],
        };
    }

    /** @return list<self> the levels at the top of the tree */
    public static function roots(): array
    {
        return [self::Stage, self::University, self::Field];
    }

    /** A course hangs on a subject, a university course or a skill (or deeper). */
    public function teachable(): bool
    {
        return in_array($this, [self::Subject, self::Course, self::Skill, self::Unit, self::Lesson], true);
    }

    public function allows(self $child): bool
    {
        return in_array($child, $this->children(), true);
    }
}
