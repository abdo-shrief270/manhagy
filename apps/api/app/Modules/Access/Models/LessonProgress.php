<?php

declare(strict_types=1);

namespace App\Modules\Access\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $learner_id
 * @property string $lesson_id
 * @property int $views
 * @property int $extra_views
 * @property int $watched_seconds
 * @property int $pending_seconds
 * @property int $last_position
 * @property Carbon|null $completed_at
 * @property Carbon|null $last_viewed_at
 */
#[Fillable(['tenant_id', 'learner_id', 'lesson_id', 'views', 'extra_views', 'watched_seconds', 'pending_seconds', 'last_position', 'completed_at', 'last_viewed_at'])]
final class LessonProgress extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'lesson_progress';

    protected $attributes = ['views' => 0, 'extra_views' => 0, 'watched_seconds' => 0, 'pending_seconds' => 0, 'last_position' => 0];

    protected function casts(): array
    {
        return [
            'views' => 'integer', 'extra_views' => 'integer', 'watched_seconds' => 'integer', 'pending_seconds' => 'integer',
            'last_position' => 'integer', 'completed_at' => 'datetime', 'last_viewed_at' => 'datetime',
        ];
    }

    /** null = unlimited */
    public function viewsLeft(?int $maxViews): ?int
    {
        return $maxViews === null ? null : max(0, $maxViews + $this->extra_views - $this->views);
    }
}
