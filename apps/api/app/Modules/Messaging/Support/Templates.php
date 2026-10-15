<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Support;

/**
 * The messages an account sends on WhatsApp (to parents and students), their variables and
 * built-in wording. The app fills the variables; a line with a variable that has no value is left
 * out. Modules that send a message add its template here.
 */
final class Templates
{
    /**
     * @return array<string, array{label: string, group: string, variables: array<string, string>, body: string}>
     */
    public static function all(): array
    {
        $student = ['student' => 'اسم الطالب', 'teacher' => 'اسم المدرس', 'subject' => 'المادة', 'link' => 'اللينك'];

        return [
            'parent_report' => ['label' => 'تقرير الأسبوع لولي الأمر', 'group' => 'parents', 'variables' => [
                ...$student,
                'watched' => 'الدروس اللي شافها', 'exam' => 'آخر امتحان ودرجته', 'attendance' => 'الحضور', 'missing' => 'اللي لسه ما شافهوش',
            ], 'body' => implode("\n", [
                'تقرير الأسبوع: {student}',
                '{subject} · {teacher}',
                '✅ شاف {watched}',
                '📝 {exam}',
                '🏫 {attendance}',
                '⏳ لسه ما شافش: {missing}',
                'التفاصيل: {link}',
            ])],
            'parent_absence' => ['label' => 'غياب عن الحصة', 'group' => 'parents', 'variables' => [
                ...$student, 'session' => 'الحصة',
            ], 'body' => implode("\n", [
                'أهلاً، {student} ما حضرش {session} النهارده ({subject} · {teacher}).',
                'شرح الحصة هيكون متاح له على المنصة: {link}',
            ])],
            'student_welcome' => ['label' => 'ترحيب بطالب جديد', 'group' => 'students', 'variables' => [
                ...$student, 'course' => 'الكورس',
            ], 'body' => implode("\n", [
                'أهلاً يا {student} 👋',
                'اتفتح لك «{course}» مع {teacher}.',
                'ادخل من هنا وابدأ: {link}',
            ])],
            'renewal_reminder' => ['label' => 'تذكير بالتجديد', 'group' => 'students', 'variables' => [
                ...$student, 'course' => 'الكورس', 'expires' => 'آخر يوم',
            ], 'body' => implode("\n", [
                'أهلاً يا {student}،',
                'اشتراكك في «{course}» مع {teacher} بيخلص {expires}.',
                'جدّد بكود أو من هنا: {link}',
            ])],
        ];
    }

    /** The owner's switch a template belongs to (off = it isn't sent), if any. */
    public static function feature(string $key): ?string
    {
        return null;
    }

    public static function exists(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    public static function groupLabel(string $group): string
    {
        return match ($group) {
            'parents' => 'أولياء الأمور',
            default => 'الطلاب',
        };
    }
}
