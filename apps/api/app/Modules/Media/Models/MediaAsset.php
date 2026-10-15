<?php

declare(strict_types=1);

namespace App\Modules\Media\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A video at the provider or a file on our private disk.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $kind video | file
 * @property string $title
 * @property string|null $provider
 * @property string|null $provider_id
 * @property string|null $path file: path on the local disk
 * @property string $status uploading | processing | ready | failed
 * @property int|null $duration_seconds
 * @property int|null $size_bytes
 * @property string|null $uploaded_by_name
 * @property Carbon $created_at
 */
#[Fillable(['tenant_id', 'kind', 'title', 'provider', 'provider_id', 'path', 'status', 'duration_seconds', 'size_bytes', 'uploaded_by_name'])]
final class MediaAsset extends Model
{
    use BelongsToTenant, HasUuids;

    protected function casts(): array
    {
        return ['duration_seconds' => 'integer', 'size_bytes' => 'integer'];
    }

    /** @return array<string, mixed> */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'title' => $this->title,
            'status' => $this->status,
            'duration_seconds' => $this->duration_seconds,
            'size_bytes' => $this->size_bytes,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
