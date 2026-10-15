<?php

declare(strict_types=1);

namespace App\Modules\Access\Http\Controllers;

use App\Modules\Access\Contracts\Enrollments;
use App\Modules\Access\Models\Enrollment;
use App\Modules\Access\Models\LessonProgress;
use App\Modules\Courses\Contracts\CourseCatalog;
use App\Modules\Learners\Contracts\LearnerDirectory;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** The account's side: who is enrolled in what, opening a course by hand, extra views, a student's progress. */
final class EnrollmentController
{
    public function __construct(
        private readonly Enrollments $enrollments,
        private readonly LearnerDirectory $learners,
        private readonly CourseCatalog $courses,
        private readonly Auditor $auditor,
        private readonly CurrentTenant $tenant,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['course_id' => ['nullable', 'uuid'], 'learner_id' => ['nullable', 'uuid'], 'active' => ['nullable', 'boolean']]);
        $rows = Enrollment::query()
            ->when($data['course_id'] ?? null, fn ($q, $id) => $q->where('course_id', $id))
            ->when($data['learner_id'] ?? null, fn ($q, $id) => $q->where('learner_id', $id))
            ->when($request->boolean('active'), fn ($q) => $q->active())
            ->latest()->limit(500)->get();

        $names = [];
        foreach ($rows->pluck('learner_id')->unique() as $id) {
            $names[$id] = $this->learners->find($id)['name'] ?? null;
        }
        $titles = [];
        foreach ($rows->pluck('course_id')->unique() as $id) {
            $titles[$id] = $this->courses->course($id, evenUnpublished: true)['title'] ?? null;
        }

        return response()->json(['data' => $rows->map(fn (Enrollment $e) => [
            ...$e->toSummary(), 'learner_name' => $names[$e->learner_id], 'course_title' => $titles[$e->course_id],
        ])->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'learner_id' => ['required', 'uuid'],
            'course_id' => ['required', 'uuid'],
            'days' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);
        $learner = $this->learners->find($data['learner_id']) ?? throw new DomainRuleException('الطالب ده مش مسجّل على منصتك.', 'learner_unknown', 404);

        $enrollment = DB::transaction(function () use ($data, $learner, $request): array {
            $enrollment = $this->enrollments->grant($learner['learner_id'], $data['course_id'], 'manual', $data['days'] ?? null, $request->user()?->name);
            $title = $this->courses->course($data['course_id'], evenUnpublished: true)['title'];
            $this->auditor->record('access.enrolled', "اشتراك {$learner['name']} في «{$title}» بالإيد", null, ['enrollment_id' => $enrollment['id']], $this->tenant->idOrFail());

            return $enrollment;
        });

        return response()->json(['data' => $enrollment], 201);
    }

    public function revoke(Request $request, string $enrollment): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:2', 'max:200']], [], ['reason' => 'السبب']);
        $found = Enrollment::query()->findOrFail($enrollment);
        if ($found->revoked_at !== null) {
            throw new DomainRuleException('الاشتراك ده اتلغى قبل كده.', 'enrollment_revoked');
        }
        DB::transaction(function () use ($found, $data): void {
            $found->update(['revoked_at' => now(), 'revoked_reason' => $data['reason']]);
            $name = $this->learners->find($found->learner_id)['name'] ?? '';
            $title = $this->courses->course($found->course_id, evenUnpublished: true)['title'] ?? '';
            $this->auditor->record('access.revoked', "إلغاء اشتراك {$name} في «{$title}»: {$data['reason']}", $found);
        });

        return response()->json(['data' => $found->toSummary()]);
    }

    public function extraViews(Request $request): JsonResponse
    {
        $data = $request->validate([
            'learner_id' => ['required', 'uuid'],
            'lesson_id' => ['required', 'uuid'],
            'views' => ['required', 'integer', 'min:1', 'max:20'],
        ]);
        $learner = $this->learners->find($data['learner_id']) ?? throw new DomainRuleException('الطالب ده مش مسجّل على منصتك.', 'learner_unknown', 404);
        $lesson = $this->courses->lesson($data['lesson_id']) ?? abort(404);
        if ($lesson['max_views'] === null) {
            throw new DomainRuleException('الدرس ده مشاهداته مفتوحة.', 'views_unlimited');
        }

        $row = DB::transaction(function () use ($learner, $lesson, $data): LessonProgress {
            $row = LessonProgress::query()->firstOrCreate(['learner_id' => $learner['learner_id'], 'lesson_id' => $lesson['id']]);
            $row->increment('extra_views', $data['views']);
            $this->auditor->record('access.extra_views', "{$data['views']} مشاهدة زيادة لـ {$learner['name']} في «{$lesson['title']}»", $row);

            return $row;
        });

        return response()->json(['data' => ['lesson_id' => $lesson['id'], 'views' => $row->views, 'views_left' => $row->viewsLeft($lesson['max_views'])]]);
    }

    /** A student's progress in each course they have (or had) access to. */
    public function progress(Request $request): JsonResponse
    {
        $data = $request->validate(['learner_id' => ['required', 'uuid']]);
        $learner = $this->learners->find($data['learner_id']) ?? abort(404);
        $courseIds = Enrollment::query()->where('learner_id', $learner['learner_id'])->distinct()->pluck('course_id');
        $progress = LessonProgress::query()->where('learner_id', $learner['learner_id'])->get()->keyBy('lesson_id');

        $out = [];
        foreach ($courseIds as $courseId) {
            $course = $this->courses->course($courseId, evenUnpublished: true);
            if ($course === null) {
                continue;
            }
            $out[] = [
                'course_id' => $courseId,
                'title' => $course['title'],
                'active' => $this->enrollments->hasAccess($learner['learner_id'], $courseId),
                'lessons' => array_map(function (array $lesson) use ($progress): array {
                    $row = $progress[$lesson['id']] ?? null;

                    return [
                        'id' => $lesson['id'], 'title' => $lesson['title'], 'kind' => $lesson['kind'],
                        'views' => $row->views ?? 0, 'max_views' => $lesson['max_views'], 'extra_views' => $row->extra_views ?? 0,
                        'watched_seconds' => $row->watched_seconds ?? 0, 'completed_at' => $row?->completed_at?->toIso8601String(),
                        'last_viewed_at' => $row?->last_viewed_at?->toIso8601String(),
                    ];
                }, $this->courses->outline($courseId)),
            ];
        }

        return response()->json(['data' => $out]);
    }
}
