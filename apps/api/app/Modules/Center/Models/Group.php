<?php

declare(strict_types=1);

namespace App\Modules\Center\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string|null $teacher_id
 * @property string $name
 * @property string|null $grade_node_id
 * @property string|null $course_id
 * @property string|null $room
 * @property list<array{day: int, time: string, minutes: int}> $schedule
 * @property int $session_fee
 * @property int|null $monthly_fee
 * @property int|null $capacity
 * @property bool $active
 */
#[Fillable(['tenant_id', 'teacher_id', 'name', 'grade_node_id', 'course_id', 'room', 'schedule', 'session_fee', 'monthly_fee', 'capacity', 'active'])]
final class Group extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'center_groups';

    protected $attributes = ['active' => true, 'session_fee' => 0];

    protected function casts(): array
    {
        return ['schedule' => 'array', 'session_fee' => 'integer', 'monthly_fee' => 'integer', 'capacity' => 'integer', 'active' => 'boolean'];
    }
}
