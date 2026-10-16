<?php

declare(strict_types=1);

namespace App\Modules\Media\Http\Controllers;

use App\Modules\Media\Contracts\VideoHost;
use App\Modules\Media\Models\MediaAsset;
use App\Modules\Media\Support\Library;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The account's video and file library. */
final class MediaController
{
    public function __construct(
        private readonly VideoHost $host,
        private readonly Library $library,
        private readonly CurrentTenant $tenant,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['kind' => ['nullable', 'in:video,file']]);
        $assets = MediaAsset::query()->when($data['kind'] ?? null, fn ($q, $k) => $q->where('kind', $k))->latest()->limit(500)->get();

        return response()->json(['data' => $assets->map(fn (MediaAsset $a) => $a->toSummary())->all()]);
    }

    /** A new video: the browser uploads it with what this returns (chunks to the API, or a provider's PUT). */
    public function createVideo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:200'],
            'size_bytes' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('services.video.max_mb', 4096) * 1024 * 1024],
        ], ['size_bytes.max' => 'الفيديو أكبر من '.(int) (config('services.video.max_mb', 4096) / 1024).' جيجا.']);
        $upload = $this->host->createUpload($data['title']);
        $asset = MediaAsset::query()->create([
            'kind' => 'video', 'title' => $data['title'], 'provider' => $this->host->name(), 'provider_id' => $upload['video_id'],
            'status' => 'uploading', 'size_bytes' => $data['size_bytes'] ?? null, 'uploaded_by_name' => $request->user()?->name,
        ]);

        return response()->json(['data' => [...$asset->toSummary(), 'upload' => [
            'method' => $upload['method'], 'url' => $upload['upload_url'], 'headers' => $upload['headers'],
            'chunk_bytes' => $upload['chunk_bytes'], 'expires_at' => $upload['expires_at'],
        ]]], 201);
    }

    /** The browser finished uploading (processing starts), or the screen polls: asks the host. */
    public function check(string $asset): JsonResponse
    {
        $found = MediaAsset::query()->findOrFail($asset);
        if ($found->status === 'uploading' && $found->provider_id !== null) {
            $this->host->finishUpload($found->provider_id);
            $found->update(['status' => 'processing']);
        }

        return response()->json(['data' => $this->library->refresh($found)->toSummary()]);
    }

    public function uploadFile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:51200'],
            'title' => ['nullable', 'string', 'max:200'],
        ], [], ['file' => 'الملف']);
        $file = $data['file'];
        $path = $file->store("media/{$this->tenant->idOrFail()}", 'local');

        $asset = MediaAsset::query()->create([
            'kind' => 'file', 'title' => $data['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'path' => $path, 'status' => 'ready', 'size_bytes' => $file->getSize(), 'uploaded_by_name' => $request->user()?->name,
        ]);

        return response()->json(['data' => $asset->toSummary()], 201);
    }

    /** A signed link (from MediaLibrary::fileUrl) streams the file; no sign-in, the signature is the permission. */
    public function file(string $tenant, string $asset, CurrentTenant $current): StreamedResponse
    {
        $found = $current->runAs($tenant, fn () => MediaAsset::query()->where('kind', 'file')->findOrFail($asset));

        return Storage::disk('local')->response((string) $found->path, $found->title.'.pdf', ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store']);
    }
}
