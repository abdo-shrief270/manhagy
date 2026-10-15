<?php

declare(strict_types=1);

namespace App\Modules\Access\Http\Controllers;

use App\Modules\Access\Models\Enrollment;
use App\Modules\Access\Models\LessonProgress;
use App\Modules\Access\Support\Player;
use App\Modules\Access\Support\Rules;
use App\Modules\Courses\Contracts\CourseCatalog;
use App\Modules\Learners\Contracts\LearnerDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The student's side of a teacher's platform: courses, the outline with what's open, watching. */
final class LearnerCourseController
{
    public function __construct(
        private readonly LearnerDirectory $learners,
        private readonly CourseCatalog $courses,
        private readonly Player $player,
    ) {}

    public function index(): JsonResponse
    {
        $learner = $this->learner();
        $enrollments = $this->enrollments($learner['learner_id']);
        $done = LessonProgress::query()->where('learner_id', $learner['learner_id'])->whereNotNull('completed_at')->pluck('lesson_id')->flip();

        return response()->json(['data' => array_map(fn (array $c) => [
            ...$this->publicCourse($c),
            'enrolled' => isset($enrollments[$c['id']]),
            'ends_at' => isset($enrollments[$c['id']]) ? $enrollments[$c['id']]->ends_at?->toIso8601String() : null,
            'completed_lessons' => $done->isEmpty() ? 0 : count(array_filter(array_column($this->courses->outline($c['id']), 'id'), fn (string $id) => $done->has($id))),
        ], $this->courses->published())]);
    }

    public function show(string $course): JsonResponse
    {
        $learner = $this->learner();
        $found = $this->courses->course($course) ?? abort(404);
        $enrollment = $this->enrollments($learner['learner_id'])[$found['id']] ?? null;
        $outline = $this->courses->outline($found['id']);
        $progress = LessonProgress::query()->where('learner_id', $learner['learner_id'])->whereIn('lesson_id', array_column($outline, 'id'))->get()->keyBy('lesson_id')->all();

        $chapters = [];
        foreach ($outline as $lesson) {
            $row = $progress[$lesson['id']] ?? null;
            $chapters[$lesson['chapter_id']] ??= ['id' => $lesson['chapter_id'], 'title' => $lesson['chapter_title'], 'lessons' => []];
            $chapters[$lesson['chapter_id']]['lessons'][] = [
                'id' => $lesson['id'],
                'kind' => $lesson['kind'],
                'title' => $lesson['title'],
                'duration_seconds' => $lesson['duration_seconds'],
                'free_preview' => $lesson['free_preview'],
                'publish_at' => $lesson['publish_at'],
                'locked' => Rules::lockReason($lesson, $outline, $enrollment !== null, $progress),
                'views_left' => $lesson['max_views'] === null ? null : ($row?->viewsLeft($lesson['max_views']) ?? $lesson['max_views']),
                'completed' => $row?->completed_at !== null,
                'last_position' => $row->last_position ?? 0,
            ];
        }

        return response()->json(['data' => [
            ...$this->publicCourse($found),
            'enrolled' => $enrollment !== null,
            'ends_at' => $enrollment?->ends_at?->toIso8601String(),
            'chapters' => array_values($chapters),
        ]]);
    }

    public function open(Request $request, string $lesson): JsonResponse
    {
        return response()->json(['data' => $this->player->open($this->learner(), $lesson, $request->ip())]);
    }

    public function beat(Request $request, string $session): JsonResponse
    {
        $data = $request->validate(['position' => ['required', 'integer', 'min:0'], 'playing' => ['required', 'boolean']]);

        return response()->json(['data' => $this->player->beat($this->learner(), $session, $data['position'], $data['playing'])]);
    }

    public function stop(string $session): JsonResponse
    {
        $this->player->stop($this->learner(), $session);

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    private function learner(): array
    {
        return $this->learners->current() ?? abort(401);
    }

    /** @return array<string, Enrollment> the active one per course */
    private function enrollments(string $learnerId): array
    {
        return Enrollment::query()->where('learner_id', $learnerId)->active()->orderByRaw('ends_at is null')->orderBy('ends_at')
            ->get()->keyBy('course_id')->all();
    }

    /**
     * @param  array<string, mixed>  $course
     * @return array<string, mixed>
     */
    private function publicCourse(array $course): array
    {
        return array_intersect_key($course, array_flip(['id', 'title', 'description', 'price', 'access_days', 'grade', 'lessons_count', 'free_previews']));
    }
}
