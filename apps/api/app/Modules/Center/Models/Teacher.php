<?php

declare(strict_types=1);

namespace App\Modules\Center\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $name
 * @property string|null $phone
 * @property string|null $subject
 * @property string $share_kind percent | per_student
 * @property int $share_value
 * @property bool $active
 */
#[Fillable(['tenant_id', 'name', 'phone', 'subject', 'share_kind', 'share_value', 'active'])]
final class Teacher extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'center_teachers';

    protected $attributes = ['share_kind' => 'percent', 'share_value' => 0, 'active' => true];

    protected function casts(): array
    {
        return ['share_value' => 'integer', 'active' => 'boolean'];
    }
}
