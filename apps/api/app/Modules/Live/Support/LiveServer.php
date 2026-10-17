<?php

declare(strict_types=1);

namespace App\Modules\Live\Support;

use App\Modules\Live\Models\LiveSession;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The broadcast server (MediaMTX), wherever it runs: every address comes from `services.live`, so
 * moving it to a server of its own is a change of settings. It asks our hook before anyone
 * publishes or reads (Hook); we ask it for recordings (playback server) and what's on air (API),
 * as user `manhagy` with the shared secret.
 */
final class LiveServer
{
    public const SERVICE_USER = 'manhagy';

    public function configured(): bool
    {
        return config('services.live.hls_url') !== '' && config('services.live.secret') !== '';
    }

    public function hlsUrl(LiveSession $session, string $token): string
    {
        return config('services.live.hls_url')."/{$session->path()}/index.m3u8?t={$token}";
    }

    /** @return array{whip: string|null, rtmp_server: string|null, rtmp_key: string} */
    public function publishing(LiveSession $session): array
    {
        $key = urlencode($session->stream_key);
        $whip = (string) config('services.live.whip_url');
        $rtmp = (string) config('services.live.rtmp_url');

        return [
            'whip' => $whip === '' ? null : "{$whip}/{$session->path()}/whip?key={$key}",
            'rtmp_server' => $rtmp === '' ? null : "{$rtmp}/live",
            'rtmp_key' => "{$session->id}?key={$key}",
        ];
    }

    /** @return list<array{start: string, duration: float}> the recorded spans of a session */
    public function recordings(LiveSession $session): array
    {
        $res = $this->client()->get(config('services.live.playback_url').'/list', ['path' => $session->path()]);
        if ($res->status() === 404) {
            return [];
        }
        if (! $res->successful()) {
            throw new RuntimeException("The live server's recordings answered {$res->status()}.");
        }

        return array_map(fn (array $s) => ['start' => (string) $s['start'], 'duration' => (float) $s['duration']], (array) $res->json());
    }

    /** Downloads [start, start + seconds] of a session's recording as one MP4 file. */
    public function download(LiveSession $session, string $start, float $seconds, string $to): void
    {
        $res = $this->client()->timeout(3600)->sink($to)->get(config('services.live.playback_url').'/get', [
            'path' => $session->path(), 'start' => $start, 'duration' => (string) ceil($seconds), 'format' => 'mp4',
        ]);
        if (! $res->successful()) {
            throw new RuntimeException("The live server's recording download answered {$res->status()}.");
        }
    }

    /** @return list<string>|null paths being published right now; null when the server can't be asked */
    public function onAir(): ?array
    {
        if (config('services.live.api_url') === '') {
            return null;
        }
        try {
            $res = $this->client()->get(config('services.live.api_url').'/v3/paths/list', ['itemsPerPage' => 1000]);
        } catch (ConnectionException) {
            return null; // the live server is down: decide nothing
        }
        if (! $res->successful()) {
            return null;
        }

        return collect($res->json('items', []))->filter(fn (array $p) => (bool) ($p['ready'] ?? false))->pluck('name')->values()->all();
    }

    /** Disconnects whoever is publishing the session (it ended: the hook refuses them coming back). */
    public function kick(LiveSession $session): void
    {
        if (config('services.live.api_url') === '') {
            return;
        }
        try {
            $source = $this->client()->get(config('services.live.api_url')."/v3/paths/get/{$session->path()}")->json('source');
            $endpoint = match ($source['type'] ?? null) {
                'rtmpConn' => 'rtmpconns', 'rtmpsConn' => 'rtmpsconns', 'webRTCSession' => 'webrtcsessions',
                'rtspSession' => 'rtspsessions', 'srtConn' => 'srtconns', default => null,
            };
            if ($endpoint !== null) {
                $this->client()->post(config('services.live.api_url')."/v3/{$endpoint}/kick/{$source['id']}");
            }
        } catch (ConnectionException) {
            // The live server is down: nothing is being published anyway.
        }
    }

    private function client(): PendingRequest
    {
        return Http::withBasicAuth(self::SERVICE_USER, (string) config('services.live.secret'))->timeout(15)->acceptJson();
    }
}
