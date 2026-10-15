<?php

declare(strict_types=1);

namespace App\Modules\Courses\Support;

use App\Modules\Courses\Contracts\CourseCatalog;
use App\Modules\Courses\Models\Chapter;
use App\Modules\Courses\Models\Course;
use App\Modules\Courses\Models\Lesson;
use App\Modules\Curriculum\Contracts\CurriculumTree;
use Illuminate\Support\Collection;

final class Catalog implements CourseCatalog
{
    public function __construct(private readonly CurriculumTree $tree) {}

    public function published(): array
    {
        $courses = Course::query()->where('status', 'published')->orderBy('sort')->orderByDesc('published_at')->get();

        return $this->summaries($courses);
    }

    public function course(string $id, bool $evenUnpublished = false): ?array
    {
        $course = Course::query()->when(! $evenUnpublished, fn ($q) => $q->where('status', 'published'))->find($id);

        return $course === null ? null : $this->summaries(collect([$course]))[0];
    }

    public function outline(string $courseId): array
    {
        $chapters = Chapter::query()->where('course_id', $courseId)->with('lessons')->orderBy('sort')->orderBy('created_at')->get();
        $out = [];
        $previous = null;
        foreach ($chapters as $chapter) {
            foreach ($chapter->lessons as $lesson) {
                $out[] = [...$lesson->toSummary(), 'chapter_title' => $chapter->title, 'position' => count($out) + 1, 'previous_id' => $previous];
                $previous = $lesson->id;
            }
        }

        return $out;
    }

    public function chapters(string $courseId): array
    {
        return Chapter::query()->where('course_id', $courseId)->orderBy('sort')->orderBy('created_at')->get(['id', 'title'])
            ->map(fn (Chapter $c) => ['id' => $c->id, 'title' => $c->title])->all();
    }

    public function lesson(string $id): ?array
    {
        $lesson = Lesson::query()->find($id);
        if ($lesson === null) {
            return null;
        }
        $found = collect($this->outline($lesson->course_id))->firstWhere('id', $id);

        return [...$found, 'course_status' => Course::query()->whereKey($lesson->course_id)->value('status')];
    }

    /**
     * @param  Collection<int, Course>  $courses
     * @return list<array<string, mixed>>
     */
    public function summaries(Collection $courses): array
    {
        $ids = $courses->pluck('id')->all();
        $counts = Lesson::query()->whereIn('course_id', $ids)->selectRaw('course_id, count(*) as lessons, sum(case when free_preview then 1 else 0 end) as previews')
            ->groupBy('course_id')->get()->keyBy('course_id');
        $labels = [];
        foreach ($courses->pluck('curriculum_node_id')->unique() as $node) {
            $labels[$node] = $this->tree->label($node);
        }

        return $courses->map(fn (Course $c) => [
            'id' => $c->id,
            'title' => $c->title,
            'description' => $c->description,
            'price' => $c->price,
            'access_days' => $c->access_days,
            'status' => $c->status,
            'sort' => $c->sort,
            'grade_node_id' => $c->curriculum_node_id,
            'grade' => $labels[$c->curriculum_node_id] ?? null,
            'lessons_count' => (int) ($counts[$c->id]->lessons ?? 0),
            'free_previews' => (int) ($counts[$c->id]->previews ?? 0),
            'published_at' => $c->published_at?->toIso8601String(),
        ])->values()->all();
    }
}
