<?php

declare(strict_types=1);

namespace App\Modules\Courses\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $course_id
 * @property string $title
 * @property int $sort
 */
#[Fillable(['tenant_id', 'course_id', 'title', 'sort'])]
final class Chapter extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'course_chapters';

    protected function casts(): array
    {
        return ['sort' => 'integer'];
    }

    /** @return HasMany<Lesson, $this> */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('sort')->orderBy('created_at');
    }
}
