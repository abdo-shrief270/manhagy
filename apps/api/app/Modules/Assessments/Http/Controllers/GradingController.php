<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Http\Controllers;

use App\Modules\Assessments\Models\AttemptAnswer;
use App\Modules\Assessments\Models\ExamAttempt;
use App\Modules\Assessments\Models\ExamQuestion;
use App\Modules\Assessments\Models\Question;
use App\Modules\Courses\Contracts\CourseCatalog;
use App\Modules\Learners\Contracts\LearnerDirectory;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The assistants' queue: essay / photo answers of submitted attempts, oldest first; a grade + a comment each. */
final class GradingController
{
    public function __construct(private readonly LearnerDirectory $learners, private readonly CourseCatalog $courses) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['lesson_id' => ['nullable', 'uuid'], 'graded' => ['nullable', 'boolean']]);
        $graded = $request->boolean('graded');
        $rows = AttemptAnswer::query()
            ->join('exam_attempts as a', 'a.id', '=', 'attempt_answers.attempt_id')
            ->join('exams as e', 'e.id', '=', 'a.exam_id')
            ->join('questions as q', 'q.id', '=', 'attempt_answers.question_id')
            ->where('a.status', 'submitted')->whereIn('q.kind', Question::MANUAL)
            ->when($graded, fn ($q) => $q->whereNotNull('attempt_answers.graded_at'), fn ($q) => $q->whereNull('attempt_answers.correct'))
            ->when($data['lesson_id'] ?? null, fn ($q, $id) => $q->where('e.lesson_id', $id))
            ->orderBy($graded ? 'attempt_answers.graded_at' : 'a.submitted_at', $graded ? 'desc' : 'asc')->limit(100)
            ->get(['attempt_answers.*', 'a.learner_id', 'a.exam_id', 'a.submitted_at', 'e.lesson_id', 'q.kind', 'q.body', 'q.explanation']);

        $points = ExamQuestion::query()->whereIn('exam_id', $rows->pluck('exam_id')->unique())->get()->keyBy(fn ($i) => "{$i->exam_id}|{$i->question_id}");
        $names = [];
        $lessons = [];

        return response()->json([
            'data' => $rows->map(function (AttemptAnswer $a) use ($points, &$names, &$lessons): array {
                $names[$a->learner_id] ??= $this->learners->find($a->learner_id)['name'] ?? null;
                $lessons[$a->lesson_id] ??= $this->courses->lesson($a->lesson_id)['title'] ?? null;

                return [
                    'id' => $a->id, 'kind' => $a->kind, 'question' => $a->body, 'model_answer' => $a->explanation,
                    'answer' => $a->kind === 'essay' ? $a->answer : null, 'has_photo' => $a->photo_path !== null,
                    'max_points' => $points["{$a->exam_id}|{$a->question_id}"]->points ?? 0,
                    'points_awarded' => $a->graded_at !== null ? $a->points_awarded : null, 'comment' => $a->comment,
                    'learner_name' => $names[$a->learner_id], 'exam' => $lessons[$a->lesson_id],
                    'submitted_at' => $a->submitted_at, 'graded_by_name' => $a->graded_by_name,
                ];
            })->all(),
            'meta' => ['waiting' => $graded ? null : AttemptAnswer::query()->join('exam_attempts as a', 'a.id', '=', 'attempt_answers.attempt_id')
                ->where('a.status', 'submitted')->whereNull('attempt_answers.correct')->count()],
        ]);
    }

    public function photo(string $answer): StreamedResponse
    {
        $found = AttemptAnswer::query()->findOrFail($answer);
        abort_if($found->photo_path === null, 404);

        return Storage::disk('local')->response($found->photo_path, null, ['Content-Type' => 'image/webp', 'Cache-Control' => 'private, no-store']);
    }

    /** Grades one answer (again, if needed) and updates the attempt's score. */
    public function grade(Request $request, string $answer): JsonResponse
    {
        $data = $request->validate(['points' => ['required', 'integer', 'min:0'], 'comment' => ['nullable', 'string', 'max:2000']], [], ['points' => 'الدرجة']);
        $result = DB::transaction(function () use ($answer, $data, $request): array {
            $found = AttemptAnswer::query()->lockForUpdate()->findOrFail($answer);
            $attempt = ExamAttempt::query()->lockForUpdate()->findOrFail($found->attempt_id);
            $question = Question::query()->findOrFail($found->question_id);
            if (! in_array($question->kind, Question::MANUAL, true) || $attempt->status !== 'submitted') {
                throw new DomainRuleException('الإجابة دي مش محتاجة تصحيح.', 'not_gradable');
            }
            $max = (int) ExamQuestion::query()->where('exam_id', $attempt->exam_id)->where('question_id', $question->id)->value('points');
            if ($data['points'] > $max) {
                throw new DomainRuleException("الدرجة أكبر من درجة السؤال ({$max}).", 'points_too_high');
            }
            $found->update([
                'points_awarded' => $data['points'], 'correct' => $data['points'] >= $max, 'comment' => $data['comment'] ?? null,
                'graded_by_name' => $request->user()?->name, 'graded_at' => now(),
            ]);
            $score = (int) $attempt->answers()->sum('points_awarded');
            $pending = $attempt->answers()->whereNull('correct')->count();
            $attempt->update([
                'score' => $score, 'percent' => $attempt->max_score > 0 ? intdiv($score * 100, $attempt->max_score) : 0,
                'pending_grading' => $pending, 'graded_at' => $pending === 0 ? now() : null,
            ]);

            return ['attempt_id' => $attempt->id, 'score' => $score, 'percent' => $attempt->percent, 'pending_grading' => $pending];
        });

        return response()->json(['data' => $result]);
    }
}
