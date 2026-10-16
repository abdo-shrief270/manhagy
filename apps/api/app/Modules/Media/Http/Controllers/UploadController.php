<?php

declare(strict_types=1);

namespace App\Modules\Media\Http\Controllers;

use App\Modules\Media\Contracts\VideoHost;
use App\Modules\Media\Models\MediaAsset;
use App\Modules\Media\Support\Hosts\LocalVideoHost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** A video's original arriving in chunks (self-hosted videos): how much arrived, the next chunk. */
final class UploadController
{
    public function __construct(private readonly VideoHost $host) {}

    public function show(string $video): JsonResponse
    {
        $this->asset($video);

        return response()->json(['data' => ['received' => $this->local()->received($video)]]);
    }

    public function append(Request $request, string $video): JsonResponse
    {
        $this->asset($video);
        $offset = (int) $request->query('offset', '0');
        $received = $this->local()->append($video, $offset, $request->getContent(true));

        return response()->json(['data' => ['received' => $received]]);
    }

    /** Only the account's own video still uploading. */
    private function asset(string $video): MediaAsset
    {
        return MediaAsset::query()->where('kind', 'video')->where('provider', 'local')->where('provider_id', $video)->firstOrFail();
    }

    private function local(): LocalVideoHost
    {
        return $this->host instanceof LocalVideoHost ? $this->host : abort(404);
    }
}
