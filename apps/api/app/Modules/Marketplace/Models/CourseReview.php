<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $course_id
 * @property string $learner_id
 * @property string $first_name
 * @property int $rating 1–5
 * @property string|null $comment
 * @property bool $hidden
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['tenant_id', 'course_id', 'learner_id', 'first_name', 'rating', 'comment', 'hidden'])]
final class CourseReview extends Model
{
    use BelongsToTenant, HasUuids;

    protected $attributes = ['hidden' => false];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'hidden' => 'boolean'];
    }

    /** @return array<string, mixed> what anyone may see */
    public function toPublic(): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'at' => $this->updated_at->toIso8601String(),
        ];
    }
}
