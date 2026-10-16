<?php

declare(strict_types=1);

namespace App\Modules\Access\Support;

use App\Modules\Access\Models\PlaybackSession;
use App\Modules\Media\Contracts\PlaybackGuard;

/** A video's key goes only to a playback session still open: not ended, and beating. */
final class SessionGuard implements PlaybackGuard
{
    public function allows(string $viewer, string $videoId): bool
    {
        return PlaybackSession::withoutTenancy()->whereKey($viewer)->whereNull('ended_at')
            ->where('last_beat_at', '>', now()->subSeconds(Player::STALE_SECONDS))->exists();
    }
}
