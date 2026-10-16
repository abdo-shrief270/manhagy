<?php

declare(strict_types=1);

namespace App\Modules\Sites\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $slug
 * @property string $title
 * @property string|null $tagline
 * @property string $color
 * @property int $device_limit
 * @property bool $registration_open
 * @property bool $require_parent_phone
 */
#[Fillable(['tenant_id', 'slug', 'title', 'tagline', 'color', 'device_limit', 'registration_open', 'require_parent_phone'])]
final class Site extends Model
{
    use BelongsToTenant, HasUuids;

    protected function casts(): array
    {
        return ['device_limit' => 'integer', 'registration_open' => 'boolean', 'require_parent_phone' => 'boolean'];
    }

    /** @return array<string, mixed> */
    public function toSummary(): array
    {
        return [
            'tenant_id' => $this->tenant_id,
            'slug' => $this->slug,
            'title' => $this->title,
            'tagline' => $this->tagline,
            'color' => $this->color,
            'device_limit' => $this->device_limit,
            'registration_open' => $this->registration_open,
            'require_parent_phone' => $this->require_parent_phone,
            'url' => ($host = (string) config('services.sites.host')) !== '' ? "https://{$this->slug}.{$host}" : null,
        ];
    }
}
