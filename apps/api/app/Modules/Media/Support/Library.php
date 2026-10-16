<?php

declare(strict_types=1);

namespace App\Modules\Media\Support;

use App\Modules\Media\Contracts\MediaLibrary;
use App\Modules\Media\Contracts\VideoHost;
use App\Modules\Media\Models\MediaAsset;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Support\Facades\URL;

final class Library implements MediaLibrary
{
    public function __construct(private readonly VideoHost $host) {}

    public function find(string $id): ?array
    {
        return MediaAsset::query()->find($id)?->toSummary();
    }

    public function playback(string $id, int $ttlSeconds, ?string $viewer = null): array
    {
        $asset = MediaAsset::query()->find($id);
        if ($asset === null || $asset->kind !== 'video' || $asset->status !== 'ready') {
            throw new DomainRuleException('الفيديو ده لسه مش جاهز.', 'video_not_ready', 409);
        }

        return $this->host->playback((string) $asset->provider_id, $ttlSeconds, $viewer);
    }

    public function fileUrl(string $id, int $minutes): string
    {
        $asset = MediaAsset::query()->findOrFail($id);

        return URL::temporarySignedRoute('media.file', now()->addMinutes($minutes), ['tenant' => $asset->tenant_id, 'asset' => $asset->id]);
    }

    /** Asks the provider how an uploaded video is doing. */
    public function refresh(MediaAsset $asset): MediaAsset
    {
        if ($asset->kind === 'video' && $asset->status !== 'ready' && $asset->provider_id !== null) {
            $status = $this->host->status($asset->provider_id);
            if ($status['state'] !== 'uploading' || $asset->status === 'uploading') {
                $asset->update(['status' => $status['state'], 'duration_seconds' => $status['duration_seconds'] ?? $asset->duration_seconds]);
            }
        }

        return $asset;
    }
}
