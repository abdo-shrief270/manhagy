<?php

declare(strict_types=1);

namespace App\Modules\Courses\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $curriculum_node_id
 * @property string $title
 * @property string|null $description
 * @property int $price piasters
 * @property int|null $access_days
 * @property string $status draft | published | archived
 * @property int $sort
 * @property Carbon|null $published_at
 */
#[Fillable(['tenant_id', 'curriculum_node_id', 'title', 'description', 'price', 'access_days', 'status', 'sort', 'published_at'])]
final class Course extends Model
{
    use BelongsToTenant, HasUuids;

    public const STATUSES = ['draft', 'published', 'archived'];

    protected function casts(): array
    {
        return ['price' => 'integer', 'access_days' => 'integer', 'sort' => 'integer', 'published_at' => 'datetime'];
    }

    /** @return HasMany<Chapter, $this> */
    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class)->orderBy('sort')->orderBy('created_at');
    }

    /** @return HasMany<Lesson, $this> */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }
}
