<?php

declare(strict_types=1);

namespace App\Modules\Courses\Http\Controllers;

use App\Modules\Courses\Models\Chapter;
use App\Modules\Courses\Models\Course;
use App\Modules\Courses\Models\Lesson;
use App\Modules\Courses\Support\Catalog;
use App\Modules\Curriculum\Contracts\CurriculumTree;
use App\Modules\Media\Contracts\MediaLibrary;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** The account's courses: the course, its chapters and lessons, publishing. */
final class CourseController
{
    public function __construct(
        private readonly Catalog $catalog,
        private readonly CurriculumTree $tree,
        private readonly MediaLibrary $media,
        private readonly Auditor $auditor,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(Course::STATUSES)]]);
        $courses = Course::query()->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->orderByRaw("case status when 'published' then 0 when 'draft' then 1 else 2 end")->orderBy('sort')->latest()->get();

        return response()->json(['data' => $this->catalog->summaries($courses)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateCourse($request, true);
        $course = DB::transaction(function () use ($data): Course {
            $course = Course::query()->create([...$data, 'status' => 'draft', 'sort' => (int) Course::query()->max('sort') + 1]);
            Chapter::query()->create(['course_id' => $course->id, 'title' => 'الفصل الأول', 'sort' => 1]);

            return $course;
        });

        return response()->json(['data' => $this->detail($course)], 201);
    }

    public function show(string $course): JsonResponse
    {
        return response()->json(['data' => $this->detail(Course::query()->findOrFail($course))]);
    }

    public function update(Request $request, string $course): JsonResponse
    {
        $found = Course::query()->findOrFail($course);
        $data = $this->validateCourse($request, false);
        $status = $data['status'] ?? null;
        unset($data['status']);

        DB::transaction(function () use ($found, $data, $status): void {
            $found->fill($data);
            if ($status !== null && $status !== $found->status) {
                if ($status === 'published' && ! $found->lessons()->exists()) {
                    throw new DomainRuleException('ضيف درس واحد على الأقل قبل ما تنشر الكورس.', 'course_empty');
                }
                $found->status = $status;
                $found->published_at ??= $status === 'published' ? now() : null;
                $this->auditor->record("courses.{$status}", "الكورس «{$found->title}»: ".['published' => 'اتنشر', 'draft' => 'رجع مسودة', 'archived' => 'اتقفل'][$status], $found);
            }
            $found->save();
        });

        return response()->json(['data' => $this->detail($found)]);
    }

    /** Only a course that was never published: a published one is archived (its students keep their history). */
    public function destroy(string $course): JsonResponse
    {
        $found = Course::query()->findOrFail($course);
        if ($found->published_at !== null) {
            throw new DomainRuleException('الكورس ده اتنشر قبل كده، اقفله بدل ما تمسحه.', 'course_was_published');
        }
        $found->delete();

        return response()->json(null, 204);
    }

    public function storeChapter(Request $request, string $course): JsonResponse
    {
        $found = Course::query()->findOrFail($course);
        $data = $request->validate(['title' => ['required', 'string', 'min:2', 'max:150']]);
        Chapter::query()->create(['course_id' => $found->id, 'title' => $data['title'], 'sort' => (int) $found->chapters()->max('sort') + 1]);

        return response()->json(['data' => $this->detail($found)], 201);
    }

    public function updateChapter(Request $request, string $course, string $chapter): JsonResponse
    {
        $found = Course::query()->findOrFail($course);
        $data = $request->validate(['title' => ['required', 'string', 'min:2', 'max:150']]);
        Chapter::query()->where('course_id', $found->id)->findOrFail($chapter)->update($data);

        return response()->json(['data' => $this->detail($found)]);
    }

    public function destroyChapter(string $course, string $chapter): JsonResponse
    {
        $found = Course::query()->findOrFail($course);
        $row = Chapter::query()->where('course_id', $found->id)->findOrFail($chapter);
        if ($row->lessons()->exists()) {
            throw new DomainRuleException('الفصل فيه دروس، انقلها أو امسحها الأول.', 'chapter_not_empty');
        }
        $row->delete();

        return response()->json(['data' => $this->detail($found)]);
    }

    public function storeLesson(Request $request, string $course): JsonResponse
    {
        $found = Course::query()->findOrFail($course);
        $data = $this->validateLesson($request, $found, null);
        Lesson::query()->create([...$data, 'course_id' => $found->id, 'sort' => (int) Lesson::query()->where('chapter_id', $data['chapter_id'])->max('sort') + 1]);

        return response()->json(['data' => $this->detail($found)], 201);
    }

    public function updateLesson(Request $request, string $course, string $lesson): JsonResponse
    {
        $found = Course::query()->findOrFail($course);
        $row = Lesson::query()->where('course_id', $found->id)->findOrFail($lesson);
        $row->update($this->validateLesson($request, $found, $row));

        return response()->json(['data' => $this->detail($found)]);
    }

    public function destroyLesson(string $course, string $lesson): JsonResponse
    {
        $found = Course::query()->findOrFail($course);
        $row = Lesson::query()->where('course_id', $found->id)->findOrFail($lesson);
        $this->auditor->record('courses.lesson_deleted', "اتمسح الدرس «{$row->title}» من «{$found->title}»", $found);
        $row->delete();

        return response()->json(['data' => $this->detail($found)]);
    }

    /** The whole order at once: chapters in order, each with its lessons in order (lessons may move between chapters). */
    public function order(Request $request, string $course): JsonResponse
    {
        $found = Course::query()->findOrFail($course);
        $data = $request->validate([
            'chapters' => ['required', 'array', 'min:1'],
            'chapters.*.id' => ['required', 'uuid'],
            'chapters.*.lessons' => ['present', 'array'],
            'chapters.*.lessons.*' => ['uuid'],
        ]);
        $chapterIds = Chapter::query()->where('course_id', $found->id)->pluck('id')->sort()->values()->all();
        $lessonIds = Lesson::query()->where('course_id', $found->id)->pluck('id')->sort()->values()->all();
        $sentChapters = collect($data['chapters'])->pluck('id')->sort()->values()->all();
        $sentLessons = collect($data['chapters'])->pluck('lessons')->flatten()->sort()->values()->all();
        if ($sentChapters !== $chapterIds || $sentLessons !== $lessonIds) {
            throw new DomainRuleException('الترتيب ده مش مطابق للكورس، حدّث الصفحة.', 'order_mismatch');
        }

        DB::transaction(function () use ($data): void {
            foreach ($data['chapters'] as $c => $chapter) {
                Chapter::query()->whereKey($chapter['id'])->update(['sort' => $c + 1]);
                foreach ($chapter['lessons'] as $l => $lesson) {
                    Lesson::query()->whereKey($lesson)->update(['chapter_id' => $chapter['id'], 'sort' => $l + 1]);
                }
            }
        });

        return response()->json(['data' => $this->detail($found)]);
    }

    /** @return array<string, mixed> */
    private function validateCourse(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';
        $data = $request->validate([
            'curriculum_node_id' => [$required, 'uuid'],
            'title' => [$required, 'string', 'min:2', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'price' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'access_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000'],
            'status' => [$creating ? 'prohibited' : 'sometimes', Rule::in(Course::STATUSES)],
            'sort' => ['sometimes', 'integer', 'min:0'],
        ], [], ['curriculum_node_id' => 'المادة', 'title' => 'اسم الكورس']);

        if (isset($data['curriculum_node_id'])) {
            $node = $this->tree->node($data['curriculum_node_id']);
            if ($node === null || ! $node['teachable']) {
                throw new DomainRuleException('اختار مادة أو وحدة أو مقرر من شجرة المناهج.', 'curriculum_not_teachable');
            }
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function validateLesson(Request $request, Course $course, ?Lesson $lesson): array
    {
        $required = $lesson === null ? 'required' : 'sometimes';
        $data = $request->validate([
            'chapter_id' => [$required, 'uuid', Rule::exists('course_chapters', 'id')->where('course_id', $course->id)],
            'kind' => [$lesson === null ? 'required' : 'prohibited', Rule::in(Lesson::KINDS)],
            'title' => [$required, 'string', 'min:2', 'max:150'],
            'media_id' => ['sometimes', 'nullable', 'uuid'],
            'free_preview' => ['sometimes', 'boolean'],
            'publish_at' => ['sometimes', 'nullable', 'date'],
            'requires_previous' => ['sometimes', 'boolean'],
            'max_views' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'count_percent' => ['sometimes', 'integer', 'min:10', 'max:100'],
        ], [], ['chapter_id' => 'الفصل', 'title' => 'اسم الدرس']);

        $kind = $data['kind'] ?? $lesson?->kind;
        if ($kind === 'exam') {
            $data['media_id'] = null;
        } elseif (array_key_exists('media_id', $data) || $lesson === null) {
            $asset = isset($data['media_id']) ? $this->media->find($data['media_id']) : null;
            if ($asset === null || $asset['kind'] !== ($kind === 'video' ? 'video' : 'file')) {
                throw new DomainRuleException($kind === 'video' ? 'اختار فيديو من مكتبتك.' : 'اختار ملف PDF من مكتبتك.', 'lesson_media_invalid');
            }
            $data['duration_seconds'] = $asset['duration_seconds'];
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function detail(Course $course): array
    {
        $course->refresh();
        $chapters = $course->chapters()->with('lessons')->get();
        $media = [];
        foreach ($chapters->pluck('lessons')->flatten()->pluck('media_id')->filter()->unique() as $id) {
            $media[$id] = $this->media->find($id);
        }

        return [
            ...$this->catalog->summaries(collect([$course]))[0],
            'chapters' => $chapters->map(fn (Chapter $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'lessons' => $c->lessons->map(function (Lesson $l) use ($media): array {
                    $asset = $l->media_id !== null ? ($media[$l->media_id] ?? null) : null;
                    if ($asset !== null && $l->duration_seconds === null && $asset['duration_seconds'] !== null) {
                        $l->update(['duration_seconds' => $asset['duration_seconds']]); // the provider finished after the lesson was saved
                    }

                    return [...$l->toSummary(), 'media' => $asset === null ? null : ['title' => $asset['title'], 'status' => $asset['status']]];
                })->all(),
            ])->all(),
        ];
    }
}
