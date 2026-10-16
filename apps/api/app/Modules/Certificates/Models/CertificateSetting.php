<?php

declare(strict_types=1);

namespace App\Modules\Certificates\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $course_id
 * @property bool $enabled
 * @property int|null $min_exam_percent
 * @property string $title
 * @property string|null $signer_name
 * @property string|null $signer_title
 */
#[Fillable(['tenant_id', 'course_id', 'enabled', 'min_exam_percent', 'title', 'signer_name', 'signer_title'])]
final class CertificateSetting extends Model
{
    use BelongsToTenant, HasUuids;

    protected $attributes = ['enabled' => false, 'title' => 'شهادة إتمام'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'min_exam_percent' => 'integer'];
    }

    public static function for(string $courseId): self
    {
        return self::query()->firstOrNew(['course_id' => $courseId]);
    }

    /** @return array<string, mixed> */
    public function toSummary(): array
    {
        return [
            'course_id' => $this->course_id, 'enabled' => $this->enabled, 'min_exam_percent' => $this->min_exam_percent,
            'title' => $this->title, 'signer_name' => $this->signer_name, 'signer_title' => $this->signer_title,
        ];
    }
}
