<?php

declare(strict_types=1);

namespace App\Modules\Courses\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $course_id
 * @property string $chapter_id
 * @property string $kind video | pdf | exam | live (its recording becomes its media afterwards)
 * @property string $title
 * @property string|null $media_id
 * @property int|null $duration_seconds
 * @property bool $free_preview
 * @property Carbon|null $publish_at
 * @property bool $requires_previous
 * @property int|null $max_views
 * @property int $count_percent
 * @property int $sort
 */
#[Fillable(['tenant_id', 'course_id', 'chapter_id', 'kind', 'title', 'media_id', 'duration_seconds', 'free_preview', 'publish_at', 'requires_previous', 'max_views', 'count_percent', 'sort'])]
final class Lesson extends Model
{
    use BelongsToTenant, HasUuids;

    public const KINDS = ['video', 'pdf', 'exam', 'live'];

    protected $table = 'course_lessons';

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer', 'free_preview' => 'boolean', 'publish_at' => 'datetime',
            'requires_previous' => 'boolean', 'max_views' => 'integer', 'count_percent' => 'integer', 'sort' => 'integer',
        ];
    }

    /** @return array<string, mixed> */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'chapter_id' => $this->chapter_id,
            'kind' => $this->kind,
            'title' => $this->title,
            'media_id' => $this->media_id,
            'duration_seconds' => $this->duration_seconds,
            'free_preview' => $this->free_preview,
            'publish_at' => $this->publish_at?->toIso8601String(),
            'requires_previous' => $this->requires_previous,
            'max_views' => $this->max_views,
            'count_percent' => $this->count_percent,
            'sort' => $this->sort,
        ];
    }
}
