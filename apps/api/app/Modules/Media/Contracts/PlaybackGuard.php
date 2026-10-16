<?php

declare(strict_types=1);

namespace App\Modules\Media\Contracts;

/**
 * Whether a viewer (the playback session a link was issued for) may still receive a video's key.
 * Implemented by the module that runs playback sessions (Access); without one, everyone may.
 */
interface PlaybackGuard
{
    public function allows(string $viewer, string $videoId): bool;
}
