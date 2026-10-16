<?php

declare(strict_types=1);

namespace App\Modules\Courses\Support;

use App\Modules\Courses\Contracts\MarketCourses;
use App\Modules\Courses\Models\Chapter;
use App\Modules\Courses\Models\Course;
use App\Modules\Courses\Models\Lesson;
use App\Support\Tenancy\CurrentTenant;
use Carbon\CarbonInterface;

final class MarketFeed implements MarketCourses
{
    public function __construct(private readonly Catalog $catalog, private readonly CurrentTenant $tenant) {}

    public function forTenant(string $tenantId): array
    {
        return $this->tenant->runAs($tenantId, fn () => $this->catalog->published());
    }

    public function changedSince(CarbonInterface $since): array
    {
        return collect([Course::class, Chapter::class, Lesson::class])
            ->flatMap(fn (string $model) => $model::withoutTenancy()->where('updated_at', '>', $since)->distinct()->pluck('tenant_id'))
            ->unique()->values()->all();
    }

    public function syllabus(string $tenantId, string $courseId): array
    {
        return $this->tenant->runAs($tenantId, function () use ($courseId): array {
            $chapters = [];
            foreach ($this->catalog->outline($courseId) as $lesson) {
                $key = $lesson['chapter_id'];
                $chapters[$key] ??= ['title' => $lesson['chapter_title'], 'lessons' => []];
                $chapters[$key]['lessons'][] = [
                    'title' => $lesson['title'], 'kind' => $lesson['kind'],
                    'duration_seconds' => $lesson['duration_seconds'], 'free_preview' => (bool) $lesson['free_preview'],
                ];
            }

            return array_values($chapters);
        });
    }
}
