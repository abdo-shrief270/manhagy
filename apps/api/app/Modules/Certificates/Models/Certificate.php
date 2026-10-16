<?php

declare(strict_types=1);

namespace App\Modules\Certificates\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $code
 * @property string $learner_id
 * @property string $course_id
 * @property string $learner_name
 * @property string $course_title
 * @property string $teacher_name
 * @property string $title
 * @property string|null $signer_name
 * @property string|null $signer_title
 * @property int $minutes
 * @property int|null $exam_percent
 * @property Carbon $issued_at
 * @property Carbon|null $revoked_at
 * @property string|null $revoke_reason
 */
#[Fillable(['tenant_id', 'code', 'learner_id', 'course_id', 'learner_name', 'course_title', 'teacher_name', 'title', 'signer_name', 'signer_title', 'minutes', 'exam_percent', 'issued_at', 'revoked_at', 'revoke_reason'])]
final class Certificate extends Model
{
    use BelongsToTenant, HasUuids;

    protected function casts(): array
    {
        return ['minutes' => 'integer', 'exam_percent' => 'integer', 'issued_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    /** @return array<string, mixed> what the public page prints */
    public function toPublic(): array
    {
        return [
            'code' => $this->code,
            'display_code' => substr($this->code, 0, 5).'-'.substr($this->code, 5),
            'title' => $this->title,
            'learner_name' => $this->learner_name,
            'course_title' => $this->course_title,
            'teacher_name' => $this->teacher_name,
            'signer_name' => $this->signer_name,
            'signer_title' => $this->signer_title,
            'minutes' => $this->minutes,
            'exam_percent' => $this->exam_percent,
            'issued_at' => $this->issued_at->toIso8601String(),
            'revoked' => $this->revoked_at !== null,
        ];
    }

    /** @return array<string, mixed> */
    public function toSummary(): array
    {
        return [...$this->toPublic(), 'id' => $this->id, 'learner_id' => $this->learner_id, 'course_id' => $this->course_id,
            'revoked_at' => $this->revoked_at?->toIso8601String(), 'revoke_reason' => $this->revoke_reason];
    }
}
