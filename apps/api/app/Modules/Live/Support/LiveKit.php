<?php

declare(strict_types=1);

namespace App\Modules\Live\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Our self-hosted LiveKit server (WebRTC SFU): access tokens for the browsers (a JWT signed with the
 * API secret) and its server API (Twirp JSON: rooms, participants' permissions, data, Egress
 * recordings, Ingress for OBS). Every address and key comes from `services.live`, so the server can
 * move to a machine of its own with a change of settings.
 */
final class LiveKit
{
    /** What a browser may publish once allowed (LiveKit track sources). */
    public const SOURCES_TEACHER = ['camera', 'microphone', 'screen_share', 'screen_share_audio'];

    public function configured(): bool
    {
        return config('services.live.url') !== '' && config('services.live.key') !== '' && config('services.live.secret') !== '';
    }

    public function url(): string
    {
        return (string) config('services.live.url');
    }

    public static function room(string $sessionId): string
    {
        return "live-{$sessionId}";
    }

    /**
     * An access token for a browser joining `$room`.
     *
     * @param  array<string, mixed>  $grant  extra video grants (canPublish, canPublishSources…)
     */
    public function token(string $room, string $identity, string $name, array $grant, int $ttlSeconds = 6 * 3600, ?string $metadata = null): string
    {
        return $this->jwt([
            'sub' => $identity,
            'name' => $name,
            'metadata' => $metadata,
            'exp' => time() + $ttlSeconds,
            'video' => ['room' => $room, 'roomJoin' => true, 'canSubscribe' => true, 'canPublish' => false, 'canPublishData' => false, ...$grant],
        ]);
    }

    public function createRoom(string $room): void
    {
        // The room lives while anyone is in it; ended by us (deleteRoom).
        $this->call('RoomService', 'CreateRoom', ['name' => $room, 'empty_timeout' => 3600, 'departure_timeout' => 3600], ['roomCreate' => true]);
    }

    public function deleteRoom(string $room): void
    {
        $this->call('RoomService', 'DeleteRoom', ['room' => $room], ['roomCreate' => true], allowMissing: true);
    }

    /** @param  list<string>  $sources  what the participant may publish (empty = nothing) */
    public function setPublish(string $room, string $identity, array $sources): void
    {
        $this->call('RoomService', 'UpdateParticipant', ['room' => $room, 'identity' => $identity, 'permission' => [
            'can_subscribe' => true, 'can_publish' => $sources !== [], 'can_publish_data' => false, 'can_publish_sources' => array_map('strtoupper', $sources),
        ]], ['roomAdmin' => true, 'room' => $room], allowMissing: true);
    }

    public function remove(string $room, string $identity): void
    {
        $this->call('RoomService', 'RemoveParticipant', ['room' => $room, 'identity' => $identity], ['roomAdmin' => true, 'room' => $room], allowMissing: true);
    }

    /** @return list<array<string, mixed>> who's in the room (identity, name, tracks…) */
    public function participants(string $room): array
    {
        return (array) ($this->call('RoomService', 'ListParticipants', ['room' => $room], ['roomAdmin' => true, 'room' => $room], allowMissing: true)['participants'] ?? []);
    }

    public function mute(string $room, string $identity, string $trackSid): void
    {
        $this->call('RoomService', 'MutePublishedTrack', ['room' => $room, 'identity' => $identity, 'track_sid' => $trackSid, 'muted' => true], ['roomAdmin' => true, 'room' => $room], allowMissing: true);
    }

    /**
     * A small message to everyone in the room (or some): «something changed, look again».
     *
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $to  identities; empty = everyone
     */
    public function signal(string $room, array $payload, array $to = []): void
    {
        try {
            $this->call('RoomService', 'SendData', [
                'room' => $room, 'data' => base64_encode((string) json_encode($payload)), 'kind' => 'RELIABLE',
                'destination_identities' => $to, 'topic' => 'manhagy',
            ], ['roomAdmin' => true, 'room' => $room], allowMissing: true);
        } catch (RuntimeException) {
            // Only a nudge: clients also refresh on their own.
        }
    }

    /** Records the whole room (the active speaker / the shared screen large) into one MP4 on the Egress side. */
    public function startRecording(string $room, string $file): string
    {
        $res = $this->call('Egress', 'StartRoomCompositeEgress', [
            'room_name' => $room, 'layout' => 'speaker',
            'file_outputs' => [['file_type' => 'MP4', 'filepath' => $file]],
        ], ['roomRecord' => true]);

        return (string) ($res['egress_id'] ?? $res['egressId'] ?? '');
    }

    public function stopRecording(string $egressId): void
    {
        $this->call('Egress', 'StopEgress', ['egress_id' => $egressId], ['roomRecord' => true], allowMissing: true);
    }

    /** @return array{status: string, file: string|null, error: string|null}|null */
    public function recording(string $egressId): ?array
    {
        $res = $this->call('Egress', 'ListEgress', ['egress_id' => $egressId], ['roomRecord' => true]);
        $item = ($res['items'] ?? [])[0] ?? null;
        if ($item === null) {
            return null;
        }
        $file = ($item['file_results'] ?? $item['fileResults'] ?? [])[0] ?? ($item['file'] ?? null);

        return [
            'status' => (string) ($item['status'] ?? ''),
            'file' => $file === null ? null : (string) ($file['filename'] ?? $file['location'] ?? ''),
            'error' => isset($item['error']) && $item['error'] !== '' ? (string) $item['error'] : null,
        ];
    }

    /** @return array{id: string, url: string, key: string} an RTMP input for OBS that joins the room as the teacher */
    public function createIngress(string $room, string $identity, string $name): array
    {
        $res = $this->call('Ingress', 'CreateIngress', [
            'input_type' => 'RTMP_INPUT', 'name' => $room, 'room_name' => $room,
            'participant_identity' => $identity, 'participant_name' => $name,
        ], ['ingressAdmin' => true]);

        return ['id' => (string) ($res['ingress_id'] ?? $res['ingressId'] ?? ''), 'url' => (string) ($res['url'] ?? ''), 'key' => (string) ($res['stream_key'] ?? $res['streamKey'] ?? '')];
    }

    public function deleteIngress(string $ingressId): void
    {
        $this->call('Ingress', 'DeleteIngress', ['ingress_id' => $ingressId], ['ingressAdmin' => true], allowMissing: true);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $grant
     * @return array<string, mixed>
     */
    private function call(string $service, string $method, array $body, array $grant, bool $allowMissing = false): array
    {
        $token = $this->jwt(['exp' => time() + 600, 'video' => $grant]);
        try {
            $res = $this->client()->withToken($token)->post(config('services.live.api_url')."/twirp/livekit.{$service}/{$method}", $body);
        } catch (ConnectionException $e) {
            throw new RuntimeException("LiveKit is unreachable: {$e->getMessage()}");
        }
        if ($res->status() === 404 && $allowMissing) {
            return [];
        }
        if (! $res->successful()) {
            throw new RuntimeException("LiveKit {$service}.{$method} answered {$res->status()}: ".mb_substr($res->body(), 0, 200));
        }

        return (array) $res->json();
    }

    private function client(): PendingRequest
    {
        return Http::timeout(15)->acceptJson()->asJson();
    }

    /** @param  array<string, mixed>  $claims */
    private function jwt(array $claims): string
    {
        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $header = $b64((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $b64((string) json_encode(array_filter([
            'iss' => (string) config('services.live.key'), 'nbf' => time() - 10, ...$claims,
        ], fn ($v) => $v !== null)));

        return "{$header}.{$payload}.".$b64(hash_hmac('sha256', "{$header}.{$payload}", (string) config('services.live.secret'), true));
    }
}
