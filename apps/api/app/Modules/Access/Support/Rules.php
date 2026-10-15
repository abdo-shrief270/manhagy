<?php

declare(strict_types=1);

namespace App\Modules\Access\Support;

use App\Modules\Access\Models\LessonProgress;
use Illuminate\Support\Carbon;

/**
 * Why a student can't open a lesson right now (null = they can). The same answer locks the
 * lesson in the outline and refuses it on open.
 */
final class Rules
{
    public const MESSAGES = [
        'not_yet' => 'الدرس ده لسه ما اتفتحش.',
        'not_enrolled' => 'اشترك في الكورس الأول.',
        'previous_required' => 'خلّص الدرس اللي قبله الأول.',
        'views_used_up' => 'خلصت مرات المشاهدة للدرس ده. كلّم المدرس لو محتاج تاني.',
        'exam_soon' => 'الامتحانات جاية قريب.',
    ];

    /**
     * @param  array<string, mixed>  $lesson  from CourseCatalog::outline()
     * @param  list<array<string, mixed>>  $outline
     * @param  array<string, LessonProgress>  $progress  by lesson id
     */
    public static function lockReason(array $lesson, array $outline, bool $enrolled, array $progress): ?string
    {
        if ($lesson['publish_at'] !== null && Carbon::parse($lesson['publish_at'])->isFuture()) {
            return 'not_yet';
        }
        if (! $lesson['free_preview'] && ! $enrolled) {
            return 'not_enrolled';
        }
        if ($lesson['requires_previous'] && ($previous = self::previousToFinish($lesson, $outline)) !== null
            && ($progress[$previous]->completed_at ?? null) === null) {
            return 'previous_required';
        }
        if ($lesson['kind'] === 'exam') {
            return 'exam_soon';
        }
        $row = $progress[$lesson['id']] ?? null;
        if ($lesson['max_views'] !== null && ($row?->viewsLeft($lesson['max_views']) ?? $lesson['max_views']) === 0) {
            return 'views_used_up';
        }

        return null;
    }

    /**
     * The nearest earlier video / PDF lesson (exams don't block until Assessments exists).
     *
     * @param  array<string, mixed>  $lesson
     * @param  list<array<string, mixed>>  $outline
     */
    private static function previousToFinish(array $lesson, array $outline): ?string
    {
        $byId = array_column($outline, null, 'id');
        $id = $lesson['previous_id'];
        while ($id !== null && ($byId[$id]['kind'] ?? null) === 'exam') {
            $id = $byId[$id]['previous_id'];
        }

        return $id;
    }
}
