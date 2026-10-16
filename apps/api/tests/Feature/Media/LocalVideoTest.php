<?php

namespace Tests\Feature\Media;

use App\Modules\Media\Support\Video\HlsToken;
use App\Modules\Media\Support\Video\Transcoder;
use App\Modules\Media\Support\Video\VideoFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesShops;
use Tests\Concerns\RunsAPlatform;
use Tests\TestCase;

/**
 * Videos on our own server: chunked upload, ffmpeg → encrypted HLS, and playback where the key goes
 * only to a playback session still open.
 */
class LocalVideoTest extends TestCase
{
    use CreatesShops, RefreshDatabase, RunsAPlatform;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->setUpPlatform(); // its own lesson uses the fake host
        config(['services.video.driver' => 'local', 'services.video.chunk_mb' => 1, 'services.video.renditions' => [240 => 300, 360 => 500, 720 => 1500]]);
        // Controllers are kept on their routes: drop them so they take the self-hosted driver.
        foreach ($this->app['router']->getRoutes() as $route) {
            $route->controller = null;
        }
    }

    /** A video lesson open to everyone (free preview) on a video id we prepare by hand. */
    private function lessonOn(string $videoId, int $duration = 12): string
    {
        $asset = DB::table('media_assets')->insertGetId([
            'id' => (string) Str::uuid7(), 'tenant_id' => $this->teacher->tenant_id, 'kind' => 'video', 'title' => 'محلي',
            'provider' => 'local', 'provider_id' => $videoId, 'status' => 'ready', 'duration_seconds' => $duration, 'created_at' => now(), 'updated_at' => now(),
        ], 'id');
        $chapter = $this->asTeacher()->getJson("/api/v1/courses/{$this->courseId}")->json('data.chapters.0.id');
        $detail = $this->postJson("/api/v1/courses/{$this->courseId}/lessons", ['chapter_id' => $chapter, 'kind' => 'video', 'title' => 'محلي', 'media_id' => $asset, 'free_preview' => true])->assertCreated()->json('data');

        return collect($detail['chapters'][0]['lessons'])->firstWhere('title', 'محلي')['id'];
    }

    /** A request to one of our HLS addresses (absolute in the playlist / playback). */
    private function hls(string $url): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withoutToken()->get(preg_replace('#^https?://[^/]+#', '', $url));
    }

    /** Hand-made HLS files: what the transcoder leaves behind. */
    private function fakeEncoded(string $id): void
    {
        $disk = Storage::disk('local');
        $disk->put("videos/{$id}/enc.key", str_repeat('k', 16));
        $disk->put("videos/{$id}/360p/index.m3u8", "#EXTM3U\n#EXT-X-VERSION:3\n#EXT-X-TARGETDURATION:6\n#EXT-X-PLAYLIST-TYPE:VOD\n#EXT-X-KEY:METHOD=AES-128,URI=\"key://KEY\",IV=0x00\n#EXTINF:6.0,\n0000.ts\n#EXTINF:6.0,\n0001.ts\n#EXT-X-ENDLIST\n");
        $disk->put("videos/{$id}/360p/0000.ts", 'segment-0');
        $disk->put("videos/{$id}/360p/0001.ts", 'segment-1');
        $disk->put("videos/{$id}/poster.jpg", 'jpg');
        VideoFiles::writeState($id, ['state' => 'ready', 'duration_seconds' => 12, 'renditions' => [['height' => 360, 'width' => 640, 'bandwidth' => 700000]]]);
    }

    public function test_playback_hands_the_key_only_to_an_open_session(): void
    {
        $id = (string) Str::uuid7();
        $this->fakeEncoded($id);
        $lesson = $this->lessonOn($id);

        $open = $this->asStudent()->postJson("/api/v1/learner/lessons/{$lesson}/open")->assertOk()->json('data');
        $master = $this->hls($open['playback']['url'])->assertOk()->assertHeader('Content-Type', 'application/vnd.apple.mpegurl')->getContent();
        $this->assertStringContainsString('RESOLUTION=640x360', $master);
        preg_match('/^(360p\.m3u8\?t=\S+)$/m', $master, $m);
        $base = preg_replace('#master\.m3u8.*$#', '', $open['playback']['url']);

        $rendition = $this->hls($base.$m[1])->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/URI="key\?t=[^"]+"/', $rendition);
        $this->assertStringNotContainsString('key://KEY', $rendition);
        preg_match('/URI="(key\?t=[^"]+)"/', $rendition, $key);
        preg_match('/^(360p\/0000\.ts\?t=\S+)$/m', $rendition, $segment);

        $this->assertSame(str_repeat('k', 16), $this->hls($base.$key[1])->assertOk()->getContent());
        $this->assertSame('segment-0', $this->hls($base.$segment[1])->assertOk()->streamedContent());
        $this->hls($open['playback']['poster'])->assertOk();

        // A forged or another video's token, a wrong scope.
        $this->hls($base.'key?t=abc.def')->assertForbidden();
        $this->hls($base.'key?t='.HlsToken::make((string) Str::uuid7(), 'k', time() + 60, null))->assertForbidden();
        $this->hls($base.'key?t='.HlsToken::make($id, 's', time() + 60, null))->assertForbidden();

        // The student opens the video on another device: the copied link gets no key any more.
        $this->asStudent()->postJson("/api/v1/learner/lessons/{$lesson}/open")->assertOk();
        $this->hls($base.$key[1])->assertForbidden();
        // The new session's key works, until its key address expires (5 minutes).
        $fresh = $this->asStudent()->postJson("/api/v1/learner/lessons/{$lesson}/open")->assertOk()->json('data');
        $variant = $this->hls(preg_replace('#master\.m3u8#', '360p.m3u8', $fresh['playback']['url']))->assertOk()->getContent();
        preg_match('/URI="(key\?t=[^"]+)"/', $variant, $key2);
        $this->hls($base.$key2[1])->assertOk();
        $this->travel(6)->minutes();
        $this->hls($base.$key2[1])->assertForbidden();
    }

    public function test_the_ladder_never_upscales(): void
    {
        $this->assertSame([240, 360], array_keys(Transcoder::ladder(480)));
        $this->assertSame([240, 360, 720], array_keys(Transcoder::ladder(1080)));
        $this->assertSame([240], array_keys(Transcoder::ladder(144)));
    }

    public function test_a_real_upload_is_encoded_into_encrypted_hls(): void
    {
        if (trim((string) shell_exec('command -v ffmpeg')) === '') {
            $this->markTestSkipped('ffmpeg is not installed here.');
        }
        $source = tempnam(sys_get_temp_dir(), 'video').'.mp4';
        exec('ffmpeg -hide_banner -loglevel error -y -f lavfi -i testsrc=size=854x480:rate=25 -f lavfi -i sine=frequency=440 -t 8 -vf noise=alls=40:allf=t -c:v libx264 -b:v 2500k -c:a aac -shortest '.escapeshellarg($source));
        $bytes = (string) file_get_contents($source);
        $this->assertGreaterThan(1024 * 1024, strlen($bytes), 'two chunks at least');

        $created = $this->asTeacher()->postJson('/api/v1/media/videos', ['title' => 'حصة محلية', 'size_bytes' => strlen($bytes)])->assertCreated()->json('data');
        $this->assertSame('chunks', $created['upload']['method']);
        $video = basename($created['upload']['url']);
        $chunk = $created['upload']['chunk_bytes'];
        $path = "/api/v1/media/uploads/{$video}";

        // A chunk out of place is refused and says where to continue.
        $this->call('PUT', "{$path}?offset=5", [], [], [], ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'], 'xx')
            ->assertStatus(409)->assertJsonPath('code', 'upload_offset')->assertJsonPath('received', 0);
        for ($offset = 0; $offset < strlen($bytes); $offset += $chunk) {
            $this->call('PUT', "{$path}?offset={$offset}", [], [], [], ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'], substr($bytes, $offset, $chunk))->assertOk();
        }
        $this->getJson($path)->assertOk()->assertJsonPath('data.received', strlen($bytes));

        // Finishing encodes it (the queue runs inline in tests).
        $done = $this->postJson("/api/v1/media/{$created['id']}/check")->assertOk()->json('data');
        $this->assertSame('ready', $done['status'], json_encode(VideoFiles::state($video)));
        $this->assertSame(8, $done['duration_seconds']);
        $state = VideoFiles::state($video);
        $this->assertSame([240, 360], array_column($state['renditions'], 'height'));
        $this->assertFileDoesNotExist(VideoFiles::path($video, 'source'));
        $playlist = (string) file_get_contents(VideoFiles::path($video, '360p/index.m3u8'));
        $this->assertStringContainsString('METHOD=AES-128,URI="key://KEY"', $playlist);
        $this->assertFileExists(VideoFiles::path($video, 'poster.jpg'));
        // The segments really are encrypted: not an MPEG-TS sync byte at the start.
        $this->assertNotSame("\x47", substr((string) file_get_contents(VideoFiles::path($video, '360p/0000.ts')), 0, 1));
        // An upload that is finished takes no more chunks.
        $this->call('PUT', "{$path}?offset=".strlen($bytes), [], [], [], ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'], 'x')->assertStatus(409);
        @unlink($source);
    }
}
