<?php

declare(strict_types=1);

namespace App\Modules\Media\Support\Video;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Turns an uploaded original into encrypted HLS with ffmpeg: one rendition per configured height up
 * to the original's (the smallest always), 6-second segments, AES-128 with a key of its own, and a
 * poster. The original is deleted afterwards unless `services.video.keep_source`.
 */
final class Transcoder
{
    public function run(string $id): void
    {
        $source = VideoFiles::path($id, 'source');
        if (! is_file($source)) {
            throw new RuntimeException('The original is missing.');
        }
        $probe = $this->probe($source);
        $ladder = self::ladder($probe['height']);

        $key = random_bytes(16);
        File::put(VideoFiles::path($id, 'enc.key'), $key);
        File::put(VideoFiles::path($id, 'keyinfo'), "key://KEY\n".VideoFiles::path($id, 'enc.key')."\n".bin2hex(random_bytes(16))."\n");

        $renditions = [];
        foreach ($ladder as $height => $kbps) {
            $dir = VideoFiles::path($id, "{$height}p");
            File::ensureDirectoryExists($dir);
            $this->ffmpeg([
                '-y', '-i', $source, '-map', '0:v:0', '-map', '0:a:0?',
                '-vf', "scale=-2:{$height}", '-c:v', 'libx264', '-preset', (string) config('services.video.preset', 'veryfast'),
                '-profile:v', 'main', '-pix_fmt', 'yuv420p',
                // A keyframe every 6 s: segments of 6 s, and seeking lands where the student taps.
                '-force_key_frames', 'expr:gte(t,n_forced*6)', '-sc_threshold', '0', '-b:v', "{$kbps}k", '-maxrate', (int) ($kbps * 1.1).'k', '-bufsize', ($kbps * 2).'k',
                '-c:a', 'aac', '-b:a', '128k', '-ac', '2',
                '-hls_time', '6', '-hls_playlist_type', 'vod', '-hls_key_info_file', VideoFiles::path($id, 'keyinfo'),
                '-hls_segment_filename', "{$dir}/%04d.ts", "{$dir}/index.m3u8",
            ]);
            $renditions[] = [
                'height' => $height,
                'width' => (int) (round($probe['width'] * $height / max(1, $probe['height']) / 2) * 2),
                'bandwidth' => (int) (($kbps + 128) * 1100),
            ];
        }
        $this->ffmpeg(['-y', '-ss', (string) max(1, (int) ($probe['duration'] * 0.1)), '-i', $source, '-frames:v', '1', '-vf', 'scale=-2:540', '-q:v', '4', VideoFiles::path($id, 'poster.jpg')]);

        File::delete(VideoFiles::path($id, 'keyinfo'));
        if (! config('services.video.keep_source')) {
            File::delete($source);
        }
        VideoFiles::writeState($id, [
            'state' => 'ready',
            'duration_seconds' => (int) round($probe['duration']),
            'renditions' => $renditions,
            'size_bytes' => self::size(VideoFiles::path($id)),
        ]);
    }

    /**
     * The heights to encode for an original this tall, with their bitrates.
     *
     * @return array<int, int>
     */
    public static function ladder(int $sourceHeight): array
    {
        $all = (array) config('services.video.renditions', [360 => 700]);
        ksort($all);
        $fits = array_filter($all, fn (int $height) => $height <= max($sourceHeight, (int) array_key_first($all)), ARRAY_FILTER_USE_KEY);

        return $fits === [] ? [(int) array_key_first($all) => (int) reset($all)] : $fits;
    }

    /** @return array{duration: float, width: int, height: int} */
    private function probe(string $source): array
    {
        $result = Process::timeout(120)->run([(string) config('services.video.ffprobe', 'ffprobe'), '-v', 'error', '-print_format', 'json', '-show_format', '-show_streams', $source]);
        if (! $result->successful()) {
            throw new RuntimeException('Not a video file.');
        }
        $info = (array) json_decode($result->output(), true);
        $video = collect($info['streams'] ?? [])->firstWhere('codec_type', 'video');
        if ($video === null) {
            throw new RuntimeException('No video stream.');
        }

        return ['duration' => (float) ($info['format']['duration'] ?? $video['duration'] ?? 0), 'width' => (int) $video['width'], 'height' => (int) $video['height']];
    }

    /** @param  list<string>  $args */
    private function ffmpeg(array $args): void
    {
        $result = Process::timeout(4 * 3600)->run([(string) config('services.video.ffmpeg', 'ffmpeg'), '-hide_banner', '-loglevel', 'error', ...$args]);
        if (! $result->successful()) {
            throw new RuntimeException('ffmpeg failed: '.mb_substr(trim($result->errorOutput()), -300));
        }
    }

    private static function size(string $dir): int
    {
        return (int) collect(File::allFiles($dir))->sum(fn ($f) => $f->getSize());
    }
}
