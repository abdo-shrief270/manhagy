<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Controllers;

use App\Modules\Access\Contracts\LessonAccess;
use App\Modules\Courses\Contracts\CourseCatalog;
use App\Modules\Engagement\Models\LessonQuestion;
use App\Modules\Learners\Contracts\LearnerDirectory;
use App\Modules\Notifications\Contracts\Notifications;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** A student's questions under a lesson, and the answers the teacher made public. */
final class LearnerQuestionController
{
    public const OPEN_LIMIT = 10;

    public function __construct(private readonly LearnerDirectory $learners, private readonly LessonAccess $access) {}

    public function index(string $lesson): JsonResponse
    {
        $learner = $this->learner();
        $this->access->lockReason($learner['learner_id'], $lesson); // 404 outside a published course
        $rows = LessonQuestion::query()->where('lesson_id', $lesson)->where('status', '!=', 'hidden')
            ->where(fn ($q) => $q->where('learner_id', $learner['learner_id'])->orWhere(fn ($q) => $q->where('public', true)->where('status', 'answered')))
            ->orderByDesc('created_at')->limit(200)->get();

        return response()->json(['data' => $rows->map(fn (LessonQuestion $q) => [
            ...$q->toSummary(),
            'mine' => $q->learner_id === $learner['learner_id'],
            'asked_by' => $q->learner_id === $learner['learner_id'] ? null : Str::before((string) ($this->learners->find($q->learner_id)['name'] ?? ''), ' '),
        ])->all()]);
    }

    public function store(Request $request, string $lesson, Notifications $notifications, CourseCatalog $courses): JsonResponse
    {
        $learner = $this->learner();
        $reason = $this->access->lockReason($learner['learner_id'], $lesson);
        if ($reason !== null) {
            throw new DomainRuleException(LessonAccess::MESSAGES[$reason], $reason, 403);
        }
        $data = $request->validate(['body' => ['required', 'string', 'min:3', 'max:2000'], 'position' => ['nullable', 'integer', 'min:0']], [], ['body' => 'السؤال']);
        if (LessonQuestion::query()->where('learner_id', $learner['learner_id'])->where('status', 'open')->count() >= self::OPEN_LIMIT) {
            throw new DomainRuleException('عندك أسئلة كتير لسه مستنية رد. استنى لما يتردّ عليها.', 'questions_open_limit');
        }
        $title = $courses->lesson($lesson)['title'] ?? '';
        $question = DB::transaction(function () use ($learner, $lesson, $data, $notifications, $title): LessonQuestion {
            $question = LessonQuestion::query()->create(['lesson_id' => $lesson, 'learner_id' => $learner['learner_id'], 'body' => $data['body'], 'position' => $data['position'] ?? null]);
            $notifications->notify('engagement.question', "سؤال على «{$title}»", "{$learner['name']}: ".Str::limit($data['body'], 120), 'i-lucide-message-circle-question', '/questions', 'engagement.answer');

            return $question;
        });

        return response()->json(['data' => [...$question->toSummary(), 'mine' => true, 'asked_by' => null]], 201);
    }

    /** @return array<string, mixed> */
    private function learner(): array
    {
        return $this->learners->current() ?? abort(401);
    }
}
