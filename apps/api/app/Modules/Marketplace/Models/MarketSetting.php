<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $tenant_id
 * @property bool $listed
 * @property string|null $headline
 * @property string|null $about
 * @property list<string> $hidden_courses
 */
#[Fillable(['tenant_id', 'listed', 'headline', 'about', 'hidden_courses'])]
final class MarketSetting extends Model
{
    use BelongsToTenant, HasUuids;

    protected $attributes = ['listed' => false, 'hidden_courses' => '[]'];

    protected function casts(): array
    {
        return ['listed' => 'boolean', 'hidden_courses' => 'array'];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate([]);
    }
}
