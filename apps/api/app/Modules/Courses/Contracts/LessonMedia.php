<?php

declare(strict_types=1);

namespace App\Modules\Courses\Contracts;

/** For the Live module: a live lesson's recording becomes the lesson's video once it's ready. */
interface LessonMedia
{
    public function attachReplay(string $lessonId, string $mediaId, ?int $durationSeconds): void;
}
