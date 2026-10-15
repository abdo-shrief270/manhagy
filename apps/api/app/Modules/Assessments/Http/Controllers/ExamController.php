<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Http\Controllers;

use App\Modules\Assessments\Models\AttemptAnswer;
use App\Modules\Assessments\Models\Exam;
use App\Modules\Assessments\Models\ExamAttempt;
use App\Modules\Assessments\Models\ExamQuestion;
use App\Modules\Assessments\Models\Question;
use App\Modules\Courses\Contracts\CourseCatalog;
use App\Modules\Learners\Contracts\LearnerDirectory;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** The exam behind an exam lesson: its settings, its questions, and the results. */
final class ExamController
{
    public function __construct(
        private readonly CourseCatalog $courses,
        private readonly LearnerDirectory $learners,
        private readonly Auditor $auditor,
    ) {}

    public function show(string $lesson): JsonResponse
    {
        $found = $this->lesson($lesson);
        $exam = Exam::query()->where('lesson_id', $found['id'])->first();

        return response()->json(['data' => $this->detail($found, $exam)]);
    }

    public function save(Request $request, string $lesson): JsonResponse
    {
        $found = $this->lesson($lesson);
        $data = $request->validate([
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after:opens_at'],
            'attempts_allowed' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'shuffle_questions' => ['sometimes', 'boolean'],
            'shuffle_options' => ['sometimes', 'boolean'],
            'pass_percent' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'show_answers' => ['sometimes', Rule::in(Exam::SHOW_ANSWERS)],
            'questions' => ['required', 'array', 'min:1', 'max:200'],
            'questions.*.id' => ['required', 'uuid', 'distinct'],
            'questions.*.points' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [], ['duration_minutes' => 'مدة الامتحان', 'questions' => 'الأسئلة']);

        $bank = Question::query()->where('course_id', $found['course_id'])->where('active', true)
            ->whereIn('id', array_column($data['questions'], 'id'))->get()->keyBy('id');
        if ($bank->count() !== count($data['questions'])) {
            throw new DomainRuleException('فيه سؤال مش من بنك أسئلة الكورس ده أو متقفل.', 'exam_question_invalid');
        }
        $items = array_map(fn (array $q, int $i) => ['question_id' => $q['id'], 'points' => $q['points'] ?? $bank[$q['id']]->points, 'sort' => $i + 1], $data['questions'], array_keys($data['questions']));

        $exam = DB::transaction(function () use ($found, $data, $items): Exam {
            $exam = Exam::query()->firstOrNew(['lesson_id' => $found['id']]);
            $before = $exam->exists ? $exam->items()->get(['question_id', 'points', 'sort'])->map(fn ($i) => [$i->question_id, $i->points])->all() : [];
            $after = array_map(fn (array $i) => [$i['question_id'], $i['points']], $items);
            if ($exam->exists && $before !== $after && ExamAttempt::query()->where('exam_id', $exam->id)->exists()) {
                throw new DomainRuleException('طلاب بدأوا الامتحان ده. تقدر تغيّر المواعيد والإعدادات بس، مش الأسئلة.', 'exam_has_attempts');
            }
            $exam->fill(array_diff_key($data, ['questions' => true]))->save();
            if ($before !== $after) {
                ExamQuestion::query()->where('exam_id', $exam->id)->delete();
                foreach ($items as $item) {
                    ExamQuestion::query()->create([...$item, 'exam_id' => $exam->id]);
                }
            }
            $this->auditor->record('assessments.exam_saved', "إعداد امتحان «{$found['title']}»: ".count($items).' سؤال', $exam);

            return $exam;
        });

        return response()->json(['data' => $this->detail($found, $exam->refresh())]);
    }

    /** Every attempt, and how each question went (hardest first). */
    public function results(string $lesson): JsonResponse
    {
        $found = $this->lesson($lesson);
        $exam = Exam::query()->where('lesson_id', $found['id'])->firstOrFail();
        $attempts = ExamAttempt::query()->where('exam_id', $exam->id)->orderByDesc('percent')->orderBy('submitted_at')->get();
        $names = [];
        foreach ($attempts->pluck('learner_id')->unique() as $id) {
            $names[$id] = $this->learners->find($id)['name'] ?? null;
        }
        $submitted = $attempts->where('status', 'submitted');
        $answers = AttemptAnswer::query()->whereIn('attempt_id', $submitted->pluck('id'))
            ->selectRaw('question_id, count(*) as answered, sum(case when correct then 1 else 0 end) as right_count')->groupBy('question_id')->get()->keyBy('question_id');
        $questions = $exam->items()->with('question')->get()->map(function (ExamQuestion $i) use ($answers, $submitted): array {
            $row = $answers[$i->question_id] ?? null;

            return [
                'id' => $i->question_id, 'body' => $i->question->body, 'answered' => (int) ($row->answered ?? 0),
                'correct_percent' => $submitted->isEmpty() ? null : intdiv((int) ($row->right_count ?? 0) * 100, $submitted->count()),
            ];
        })->sortBy(fn (array $q) => $q['correct_percent'] ?? 101)->values()->all();

        return response()->json(['data' => [
            'summary' => [
                'attempts' => $submitted->count(),
                'in_progress' => $attempts->where('status', 'in_progress')->count(),
                'average_percent' => $submitted->isEmpty() ? null : (int) round($submitted->avg('percent')),
                'pass_percent' => $submitted->isEmpty() ? null : intdiv($submitted->filter(fn ($a) => $a->percent >= $exam->pass_percent)->count() * 100, $submitted->count()),
            ],
            'attempts' => $attempts->map(fn (ExamAttempt $a) => [
                'id' => $a->id, 'learner_id' => $a->learner_id, 'learner_name' => $names[$a->learner_id], 'number' => $a->number,
                'status' => $a->status, 'score' => $a->score, 'max_score' => $a->max_score, 'percent' => $a->percent,
                'passed' => $a->percent !== null && $a->percent >= $exam->pass_percent, 'leaves' => $a->leaves,
                'started_at' => $a->started_at->toIso8601String(), 'submitted_at' => $a->submitted_at?->toIso8601String(), 'submitted_by' => $a->submitted_by,
            ])->all(),
            'questions' => $questions,
        ]]);
    }

    /** @return array<string, mixed> */
    private function lesson(string $id): array
    {
        $lesson = $this->courses->lesson($id);
        if ($lesson === null || $lesson['kind'] !== 'exam') {
            abort(404);
        }

        return $lesson;
    }

    /**
     * @param  array<string, mixed>  $lesson
     * @return array<string, mixed>
     */
    private function detail(array $lesson, ?Exam $exam): array
    {
        return [
            'lesson_id' => $lesson['id'],
            'course_id' => $lesson['course_id'],
            'title' => $lesson['title'],
            'exam' => $exam === null ? null : [
                'duration_minutes' => $exam->duration_minutes, 'opens_at' => $exam->opens_at?->toIso8601String(), 'closes_at' => $exam->closes_at?->toIso8601String(),
                'attempts_allowed' => $exam->attempts_allowed, 'shuffle_questions' => $exam->shuffle_questions, 'shuffle_options' => $exam->shuffle_options,
                'pass_percent' => $exam->pass_percent, 'show_answers' => $exam->show_answers,
                'attempts' => ExamAttempt::query()->where('exam_id', $exam->id)->count(),
            ],
            'questions' => $exam === null ? [] : $exam->items()->with('question')->get()
                ->map(fn (ExamQuestion $i) => [...$i->question->toSummary(), 'points' => $i->points])->all(),
        ];
    }
}
