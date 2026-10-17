<?php

declare(strict_types=1);

namespace App\Modules\Live\Support;

use App\Modules\Live\Models\LiveAttendance;
use App\Modules\Live\Models\LiveSession;

/**
 * MediaMTX asks before every publish, read, recording download or API call (authMethod: http).
 * Publish: the session's stream key (`?key=`, or the password / token), not after it ended.
 * Read: a student's LiveToken (`?t=`) whose attendance is still open, while on air.
 * Playback / API: our own service user with the shared secret.
 */
final class Hook
{
    /** @param  array<string, mixed>  $request  MediaMTX's JSON */
    public function allows(array $request): bool
    {
        $action = (string) ($request['action'] ?? '');
        if (in_array($action, ['playback', 'api', 'metrics'], true)) {
            return ($request['user'] ?? '') === LiveServer::SERVICE_USER && hash_equals((string) config('services.live.secret'), (string) ($request['password'] ?? ''));
        }
        if (! preg_match('#^live/([0-9a-f-]{36})$#', (string) ($request['path'] ?? ''), $m)) {
            return false;
        }
        parse_str((string) ($request['query'] ?? ''), $query);
        $session = LiveSession::withoutTenancy()->find($m[1]);
        if ($session === null) {
            return false;
        }

        return match ($action) {
            'publish' => $this->publish($session, $request, $query),
            'read' => $this->read($session, $query),
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $query
     */
    private function publish(LiveSession $session, array $request, array $query): bool
    {
        $given = (string) ($query['key'] ?? $request['password'] ?? $request['token'] ?? '');
        if ($session->status === 'ended' || $given === '' || ! hash_equals($session->stream_key, $given)) {
            return false;
        }
        $session->forceFill([
            'status' => 'live',
            'started_at' => $session->started_at ?? now(),
            'last_published_at' => now(),
        ])->save();

        return true;
    }

    /** @param  array<string, mixed>  $query */
    private function read(LiveSession $session, array $query): bool
    {
        $token = LiveToken::read(is_string($query['t'] ?? null) ? $query['t'] : null);
        if ($token === null || $token['session'] !== $session->id || $session->status !== 'live') {
            return false;
        }
        $attendance = LiveAttendance::withoutTenancy()->find($token['attendance']);

        return $attendance !== null && $attendance->session_id === $session->id && $attendance->isOpen();
    }
}
