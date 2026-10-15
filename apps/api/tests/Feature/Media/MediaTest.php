<?php

namespace Tests\Feature\Media;

use App\Modules\Media\Contracts\MediaLibrary;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesShops;
use Tests\TestCase;

/** The video / file library behind the courses (fake video host in tests). */
class MediaTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    public function test_a_video_is_uploaded_to_the_provider_and_becomes_playable(): void
    {
        $owner = $this->actingAsOwnerOf();
        $video = $this->postJson('/api/v1/media/videos', ['title' => 'الحصة الأولى', 'size_bytes' => 1000])->assertCreated()->json('data');
        $this->assertSame('uploading', $video['status']);
        $this->assertNotEmpty($video['upload']['url']);

        $library = app(MediaLibrary::class);
        app(CurrentTenant::class)->set($owner->tenant_id);
        $this->assertSame('video_not_ready', rescue(fn () => $library->playback($video['id'], 300), fn (DomainRuleException $e) => $e->errorCode, false));

        $checked = $this->postJson("/api/v1/media/{$video['id']}/check")->assertOk()->json('data');
        $this->assertSame('ready', $checked['status']);
        $this->assertGreaterThan(0, $checked['duration_seconds']);
        $this->assertNotEmpty($library->playback($video['id'], 300));

        $this->assertCount(1, $this->getJson('/api/v1/media?kind=video')->assertOk()->json('data'));
        $this->assertCount(0, $this->getJson('/api/v1/media?kind=file')->json('data'));

        // Another account never sees it.
        Sanctum::actingAs($this->registerShop());
        $this->assertCount(0, $this->getJson('/api/v1/media')->json('data'));
        $this->postJson("/api/v1/media/{$video['id']}/check")->assertNotFound();
    }

    public function test_a_pdf_is_served_only_through_a_signed_link(): void
    {
        Storage::fake('local');
        $owner = $this->actingAsOwnerOf();
        $this->postJson('/api/v1/media/files', ['file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain')])->assertUnprocessable();
        $file = $this->postJson('/api/v1/media/files', ['file' => UploadedFile::fake()->create('ملزمة.pdf', 20, 'application/pdf')])->assertCreated()->json('data');
        $this->assertSame(['file', 'ملزمة', 'ready'], [$file['kind'], $file['title'], $file['status']]);

        app(CurrentTenant::class)->set($owner->tenant_id);
        $url = app(MediaLibrary::class)->fileUrl($file['id'], 10);
        app(CurrentTenant::class)->set(null);
        $this->app['auth']->forgetGuards();

        $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(strtok($url, '?'))->assertForbidden();
        $this->travel(11)->minutes();
        $this->get($url)->assertForbidden();
    }

    public function test_media_needs_the_upload_permission(): void
    {
        $this->getJson('/api/v1/media')->assertUnauthorized();
    }
}
