<?php

declare(strict_types=1);

namespace App\Modules\Media\Http\Controllers;

use App\Modules\Media\Contracts\PlaybackGuard;
use App\Modules\Media\Support\Video\HlsToken;
use App\Modules\Media\Support\Video\VideoFiles;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Self-hosted videos as HLS, no sign-in: the token in every address is the permission. Playlists are
 * rewritten per request, so each segment carries a stream token and the key line a short key token.
 */
final class HlsController
{
    /** How long a key address stays valid after its playlist was handed out. */
    private const KEY_SECONDS = 300;

    public function __construct(private readonly PlaybackGuard $guard) {}

    public function master(Request $request, string $video): Response
    {
        $token = $this->stream($request, $video);
        $state = VideoFiles::state($video);
        abort_unless(($state['state'] ?? null) === 'ready', 404);
        // The renditions are fetched now and again on quality switches: they get a token for the whole watch.
        $streamToken = HlsToken::make($video, 's', time() + max(3600, 3 * (int) ($state['duration_seconds'] ?? 0) + 3600), $token['viewer']);
        $lines = ['#EXTM3U', '#EXT-X-VERSION:3'];
        foreach ($state['renditions'] ?? [] as $r) {
            $lines[] = "#EXT-X-STREAM-INF:BANDWIDTH={$r['bandwidth']},RESOLUTION={$r['width']}x{$r['height']}";
            $lines[] = "{$r['height']}p.m3u8?t={$streamToken}";
        }

        return $this->playlist(implode("\n", $lines)."\n");
    }

    public function rendition(Request $request, string $video, int $height): Response
    {
        $token = $this->stream($request, $video);
        $file = VideoFiles::path($video, "{$height}p/index.m3u8");
        abort_unless(is_file($file), 404);
        $keyToken = HlsToken::make($video, 'k', time() + self::KEY_SECONDS, $token['viewer']);
        $t = (string) $request->query('t');
        $out = [];
        foreach (preg_split('/\R/', (string) file_get_contents($file)) ?: [] as $line) {
            $out[] = match (true) {
                str_starts_with($line, '#EXT-X-KEY') => str_replace('URI="key://KEY"', 'URI="key?t='.$keyToken.'"', $line),
                $line !== '' && ! str_starts_with($line, '#') => "{$height}p/{$line}?t={$t}",
                default => $line,
            };
        }

        return $this->playlist(implode("\n", $out));
    }

    /** The decryption key: only for a fresh key token whose viewer is still watching. */
    public function key(Request $request, string $video): Response
    {
        $token = HlsToken::read($request->query('t'), $video, 'k') ?? abort(403);
        abort_if($token['viewer'] !== null && ! $this->guard->allows($token['viewer'], $video), 403);
        $file = VideoFiles::path($video, 'enc.key');
        abort_unless(is_file($file), 404);

        return response((string) file_get_contents($file), 200, ['Content-Type' => 'application/octet-stream', 'Cache-Control' => 'no-store']);
    }

    public function segment(Request $request, string $video, int $height, string $segment): BinaryFileResponse
    {
        $this->stream($request, $video, checkViewer: false); // encrypted: useless without the key
        $file = VideoFiles::path($video, "{$height}p/{$segment}.ts");
        abort_unless(is_file($file), 404);

        return response()->file($file, ['Content-Type' => 'video/mp2t', 'Cache-Control' => 'private, max-age=86400']);
    }

    public function poster(Request $request, string $video): BinaryFileResponse
    {
        $this->stream($request, $video, checkViewer: false);
        $file = VideoFiles::path($video, 'poster.jpg');
        abort_unless(is_file($file), 404);

        return response()->file($file, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=3600']);
    }

    /** @return array{expires: int, viewer: string|null} */
    private function stream(Request $request, string $video, bool $checkViewer = true): array
    {
        $token = HlsToken::read($request->query('t'), $video, 's') ?? abort(403);
        abort_if($checkViewer && $token['viewer'] !== null && ! $this->guard->allows($token['viewer'], $video), 403);

        return $token;
    }

    private function playlist(string $body): Response
    {
        return response($body, 200, ['Content-Type' => 'application/vnd.apple.mpegurl', 'Cache-Control' => 'no-store']);
    }
}
