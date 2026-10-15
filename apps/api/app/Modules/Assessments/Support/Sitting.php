<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Support;

use App\Modules\Access\Contracts\LessonAccess;
use App\Modules\Assessments\Models\AttemptAnswer;
use App\Modules\Assessments\Models\Exam;
use App\Modules\Assessments\Models\ExamAttempt;
use App\Modules\Assessments\Models\ExamQuestion;
use App\Modules\Courses\Contracts\CourseCatalog;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Support\Facades\DB;

/**
 * A student sitting an exam: the time is the server's (ends_at fixed at the start, a few
 * seconds of grace for the network), every answer saved on its own, the order fixed per
 * attempt by its seed, and an attempt past its time is submitted for the student.
 */
final class Sitting
{
    public const GRACE_SECONDS = 10;

    public function __construct(
        private readonly CourseCatalog $courses,
        private readonly LessonAccess $access,
    ) {}

    /** @return array{0: array<string, mixed>, 1: Exam|null} the exam lesson and its exam (null = not set up yet) */
    public function exam(string $lessonId): array
    {
        $lesson = $this->courses->lesson($lessonId);
        if ($lesson === null || $lesson['kind'] !== 'exam' || $lesson['course_status'] !== 'published') {
            abort(404);
        }

        return [$lesson, Exam::query()->where('lesson_id', $lessonId)->first()];
    }

    /** The exam page before starting: window, attempts, last result. */
    public function overview(string $learnerId, string $lessonId): array
    {
        [$lesson, $exam] = $this->exam($lessonId);
        if ($exam !== null) {
            $this->closeExpired($learnerId, $exam);
        }
        $attempts = $exam === null ? collect() : ExamAttempt::query()->where('exam_id', $exam->id)->where('learner_id', $learnerId)->orderBy('number')->get();
        $items = $exam?->items()->get() ?? collect();

        return [
            'lesson_id' => $lesson['id'],
            'title' => $lesson['title'],
            'ready' => $exam !== null && $items->isNotEmpty(),
            'duration_minutes' => $exam?->duration_minutes,
            'opens_at' => $exam?->opens_at?->toIso8601String(),
            'closes_at' => $exam?->closes_at?->toIso8601String(),
            'attempts_allowed' => $exam?->attempts_allowed,
            'attempts_used' => $attempts->count(),
            'questions_count' => $items->count(),
            'max_score' => (int) $items->sum('points'),
            'pass_percent' => $exam?->pass_percent,
            'in_progress' => $attempts->firstWhere('status', 'in_progress')?->id,
            'attempts' => $attempts->where('status', 'submitted')->map(fn (ExamAttempt $a) => [
                'id' => $a->id, 'number' => $a->number, 'score' => $a->score, 'max_score' => $a->max_score, 'percent' => $a->percent,
                'passed' => $a->percent >= $exam->pass_percent, 'submitted_at' => $a->submitted_at?->toIso8601String(),
            ])->values()->all(),
            'locked' => $this->access->lockReason($learnerId, $lessonId),
            'server_now' => now()->toIso8601String(),
        ];
    }

    public function start(string $learnerId, string $lessonId): ExamAttempt
    {
        [, $exam] = $this->exam($lessonId);
        $reason = $this->access->lockReason($learnerId, $lessonId);
        if ($reason !== null) {
            throw new DomainRuleException(LessonAccess::MESSAGES[$reason], $reason, 403);
        }
        if ($exam === null || ! $exam->items()->exists()) {
            throw new DomainRuleException('الامتحان ده لسه بيتجهّز.', 'exam_not_ready', 409);
        }
        if ($exam->opens_at !== null && $exam->opens_at->isFuture()) {
            throw new DomainRuleException('الامتحان لسه ما فتحش.', 'exam_not_open', 409, ['opens_at' => $exam->opens_at->toIso8601String()]);
        }
        if ($exam->closes_at !== null && $exam->closes_at->isPast()) {
            throw new DomainRuleException('الامتحان قفل.', 'exam_closed', 409);
        }
        $this->closeExpired($learnerId, $exam);

        return DB::transaction(function () use ($learnerId, $exam): ExamAttempt {
            $mine = ExamAttempt::query()->where('exam_id', $exam->id)->where('learner_id', $learnerId)->lockForUpdate()->get();
            if (($open = $mine->firstWhere('status', 'in_progress')) !== null) {
                return $open;
            }
            if ($mine->count() >= $exam->attempts_allowed) {
                throw new DomainRuleException('خلصت محاولاتك في الامتحان ده.', 'attempts_used_up', 403);
            }
            $ends = now()->addMinutes($exam->duration_minutes);
            if ($exam->closes_at !== null && $exam->closes_at->lt($ends)) {
                $ends = $exam->closes_at->copy();
            }

            return ExamAttempt::query()->create([
                'exam_id' => $exam->id, 'learner_id' => $learnerId, 'number' => $mine->count() + 1, 'seed' => random_int(1, 2_000_000_000),
                'started_at' => now(), 'ends_at' => $ends, 'max_score' => (int) $exam->items()->sum('points'),
            ]);
        });
    }

    /** @return array<string, mixed> the paper: questions in this attempt's order, without answers */
    public function paper(ExamAttempt $attempt): array
    {
        $this->finishIfLate($attempt);
        $exam = $attempt->exam;
        $saved = $attempt->answers()->get()->keyBy('question_id');

        return [
            'id' => $attempt->id,
            'status' => $attempt->status,
            'started_at' => $attempt->started_at->toIso8601String(),
            'ends_at' => $attempt->ends_at->toIso8601String(),
            'server_now' => now()->toIso8601String(),
            'max_score' => $attempt->max_score,
            'questions' => array_map(fn (ExamQuestion $item) => [
                'id' => $item->question_id,
                'kind' => $item->question->kind,
                'body' => $item->question->body,
                'options' => $item->question->kind === 'mcq' ? $this->order($attempt->seed, $item->question_id, $item->question->options ?? [], $exam->shuffle_options) : null,
                'points' => $item->points,
                'answer' => $saved[$item->question_id]->answer ?? null,
            ], $this->items($attempt)),
        ];
    }

    public function answer(ExamAttempt $attempt, string $questionId, mixed $answer): void
    {
        $this->ensureOpen($attempt);
        $item = ExamQuestion::query()->with('question')->where('exam_id', $attempt->exam_id)->where('question_id', $questionId)->first() ?? abort(404);
        if (! Grader::wellFormed($item->question, $answer)) {
            throw new DomainRuleException('الإجابة دي مش من اختيارات السؤال.', 'answer_invalid');
        }
        $correct = Grader::correct($item->question, $answer);
        AttemptAnswer::query()->updateOrCreate(['attempt_id' => $attempt->id, 'question_id' => $questionId], [
            'answer' => $answer, 'correct' => $correct, 'points_awarded' => $correct ? $item->points : 0, 'answered_at' => now(),
        ]);
    }

    public function leave(ExamAttempt $attempt): int
    {
        $this->ensureOpen($attempt);
        $attempt->increment('leaves');

        return $attempt->leaves;
    }

    public function submit(ExamAttempt $attempt, string $by = 'learner'): ExamAttempt
    {
        return DB::transaction(function () use ($attempt, $by): ExamAttempt {
            $locked = ExamAttempt::query()->with('exam')->lockForUpdate()->findOrFail($attempt->id);
            if ($locked->status === 'submitted') {
                return $locked;
            }
            $score = (int) $locked->answers()->sum('points_awarded');
            $locked->update([
                'status' => 'submitted', 'submitted_by' => $by, 'submitted_at' => $by === 'timer' ? $locked->ends_at : now(),
                'score' => $score, 'percent' => $locked->max_score > 0 ? intdiv($score * 100, $locked->max_score) : 0,
            ]);
            $this->access->complete($locked->learner_id, $locked->exam->lesson_id);

            return $locked;
        });
    }

    /** @return array<string, mixed> */
    public function result(ExamAttempt $attempt): array
    {
        $this->finishIfLate($attempt);
        if ($attempt->status !== 'submitted') {
            throw new DomainRuleException('سلّم الامتحان الأول.', 'attempt_in_progress', 409);
        }
        $exam = $attempt->exam;
        $visible = $exam->answersVisible(true);
        $saved = $attempt->answers()->get()->keyBy('question_id');

        return [
            'id' => $attempt->id,
            'score' => $attempt->score,
            'max_score' => $attempt->max_score,
            'percent' => $attempt->percent,
            'passed' => $attempt->percent >= $exam->pass_percent,
            'submitted_by' => $attempt->submitted_by,
            'answers_visible' => $visible,
            'answers_at' => $exam->show_answers === 'after_close' ? $exam->closes_at?->toIso8601String() : null,
            'review' => $visible ? array_map(fn (ExamQuestion $item) => [
                'id' => $item->question_id,
                'kind' => $item->question->kind,
                'body' => $item->question->body,
                'options' => $item->question->options,
                'points' => $item->points,
                'your_answer' => $saved[$item->question_id]->answer ?? null,
                'correct' => (bool) ($saved[$item->question_id]->correct ?? false),
                'correct_answer' => $item->question->answer,
                'explanation' => $item->question->explanation,
            ], $this->items($attempt)) : null,
        ];
    }

    /** Submits the student's attempts on this exam whose time is up. */
    public function closeExpired(string $learnerId, Exam $exam): void
    {
        ExamAttempt::query()->where('exam_id', $exam->id)->where('learner_id', $learnerId)->where('status', 'in_progress')
            ->where('ends_at', '<', now()->subSeconds(self::GRACE_SECONDS))->get()
            ->each(fn (ExamAttempt $a) => $this->submit($a, 'timer'));
    }

    private function finishIfLate(ExamAttempt $attempt): void
    {
        if ($attempt->status === 'in_progress' && $attempt->ends_at->lt(now()->subSeconds(self::GRACE_SECONDS))) {
            $attempt->setRawAttributes($this->submit($attempt, 'timer')->getAttributes(), true);
        }
    }

    private function ensureOpen(ExamAttempt $attempt): void
    {
        $this->finishIfLate($attempt);
        if ($attempt->status === 'submitted') {
            throw new DomainRuleException($attempt->submitted_by === 'timer' ? 'الوقت خلص، والامتحان اتسلّم بإجاباتك اللي اتحفظت.' : 'الامتحان ده اتسلّم.', 'attempt_closed', 409, ['submitted_by' => $attempt->submitted_by]);
        }
    }

    /** @return list<ExamQuestion> */
    private function items(ExamAttempt $attempt): array
    {
        $items = ExamQuestion::query()->with('question')->where('exam_id', $attempt->exam_id)->orderBy('sort')->get()->all();

        return $attempt->exam->shuffle_questions ? $this->order($attempt->seed, '', $items, true, fn (ExamQuestion $i) => $i->question_id) : $items;
    }

    /**
     * The same order every time for this attempt, different between students.
     *
     * @template T
     *
     * @param  list<T>  $list
     * @return list<T>
     */
    private function order(int $seed, string $salt, array $list, bool $shuffle, ?callable $key = null): array
    {
        if (! $shuffle) {
            return $list;
        }
        $key ??= fn (array $option) => $option['id'];
        usort($list, fn ($a, $b) => strcmp(hash('sha256', "{$seed}|{$salt}|".$key($a)), hash('sha256', "{$seed}|{$salt}|".$key($b))));

        return $list;
    }
}
