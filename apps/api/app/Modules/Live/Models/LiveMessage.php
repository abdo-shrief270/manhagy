<?php

declare(strict_types=1);

namespace App\Modules\Live\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $seq
 * @property string $session_id
 * @property string|null $learner_id null = the teacher / an assistant
 * @property string $author_name
 * @property string $kind chat | question
 * @property string $body
 * @property bool $pinned
 * @property bool $answered
 * @property bool $hidden
 * @property Carbon $created_at
 */
#[Fillable(['tenant_id', 'session_id', 'learner_id', 'author_name', 'kind', 'body', 'pinned', 'answered', 'hidden', 'created_at'])]
final class LiveMessage extends Model
{
    use BelongsToTenant, HasUuids;

    public const UPDATED_AT = null;

    protected $attributes = ['pinned' => false, 'answered' => false, 'hidden' => false];

    protected function casts(): array
    {
        return ['seq' => 'integer', 'pinned' => 'boolean', 'answered' => 'boolean', 'hidden' => 'boolean', 'created_at' => 'datetime'];
    }

    /** @return array<string, mixed> */
    public function toSummary(?string $viewer = null): array
    {
        return [
            'id' => $this->id, 'seq' => $this->seq, 'kind' => $this->kind, 'body' => $this->body,
            'author' => $this->author_name, 'from_teacher' => $this->learner_id === null,
            'mine' => $viewer !== null && $this->learner_id === $viewer,
            'pinned' => $this->pinned, 'answered' => $this->answered, 'hidden' => $this->hidden,
            'at' => $this->created_at->toIso8601String(),
        ];
    }
}
