<?php

declare(strict_types=1);

namespace App\Modules\Access\Support;

use App\Modules\Access\Contracts\LessonAccess;
use App\Modules\Access\Models\LessonProgress;
use Illuminate\Support\Carbon;

/**
 * Why a student can't open a lesson right now (null = they can). The same answer locks the
 * lesson in the outline and refuses it on open.
 */
final class Rules
{
    public const MESSAGES = LessonAccess::MESSAGES;

    /**
     * @param  array<string, mixed>  $lesson  from CourseCatalog::outline()
     * @param  list<array<string, mixed>>  $outline
     * @param  array<string, LessonProgress>  $progress  by lesson id (an exam is completed by submitting it)
     */
    public static function lockReason(array $lesson, array $outline, bool $enrolled, array $progress): ?string
    {
        if ($lesson['publish_at'] !== null && Carbon::parse($lesson['publish_at'])->isFuture()) {
            return 'not_yet';
        }
        if (! $lesson['free_preview'] && ! $enrolled) {
            return 'not_enrolled';
        }
        if ($lesson['requires_previous'] && $lesson['previous_id'] !== null && ($progress[$lesson['previous_id']]->completed_at ?? null) === null) {
            return 'previous_required';
        }
        $row = $progress[$lesson['id']] ?? null;
        if ($lesson['max_views'] !== null && ($row?->viewsLeft($lesson['max_views']) ?? $lesson['max_views']) === 0) {
            return 'views_used_up';
        }

        return null;
    }
}
