<?php

declare(strict_types=1);

namespace App\Modules\Courses\Support;

use App\Modules\Courses\Contracts\LessonMedia;
use App\Modules\Courses\Models\Lesson;

final class Replays implements LessonMedia
{
    public function attachReplay(string $lessonId, string $mediaId, ?int $durationSeconds): void
    {
        $lesson = Lesson::query()->where('kind', 'live')->find($lessonId);
        if ($lesson === null) {
            return;
        }
        // It may have gone live before its time: the recording opens now.
        $lesson->update([
            'media_id' => $mediaId, 'duration_seconds' => $durationSeconds,
            'publish_at' => $lesson->publish_at !== null && $lesson->publish_at->isFuture() ? now() : $lesson->publish_at,
        ]);
    }
}
