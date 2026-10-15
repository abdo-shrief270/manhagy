<?php

declare(strict_types=1);

namespace App\Modules\Codes\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $name
 * @property string|null $phone
 * @property string $kind library | center | assistant | other
 * @property string|null $notes
 * @property bool $active
 */
#[Fillable(['tenant_id', 'name', 'phone', 'kind', 'notes', 'active'])]
final class Distributor extends Model
{
    use BelongsToTenant, HasUuids;

    public const KINDS = ['library', 'center', 'assistant', 'other'];

    protected $attributes = ['active' => true, 'kind' => 'library'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
