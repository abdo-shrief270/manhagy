<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Controllers;

use App\Modules\Courses\Contracts\CourseCatalog;
use App\Modules\Engagement\Models\LessonQuestion;
use App\Modules\Learners\Contracts\LearnerDirectory;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The teacher's / assistants' inbox of students' questions. */
final class QuestionInboxController
{
    public function __construct(private readonly LearnerDirectory $learners, private readonly CourseCatalog $courses) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(['open', 'answered', 'hidden'])], 'lesson_id' => ['nullable', 'uuid']]);
        $status = $data['status'] ?? 'open';
        $rows = LessonQuestion::query()->where('status', $status)->when($data['lesson_id'] ?? null, fn ($q, $id) => $q->where('lesson_id', $id))
            ->orderBy('created_at', $status === 'open' ? 'asc' : 'desc')->limit(200)->get();
        $names = [];
        $lessons = [];

        return response()->json([
            'data' => $rows->map(function (LessonQuestion $q) use (&$names, &$lessons): array {
                $names[$q->learner_id] ??= $this->learners->find($q->learner_id)['name'] ?? null;
                $lessons[$q->lesson_id] ??= $this->courses->lesson($q->lesson_id);

                return [
                    ...$q->toSummary(), 'learner_id' => $q->learner_id, 'learner_name' => $names[$q->learner_id],
                    'lesson_title' => $lessons[$q->lesson_id]['title'] ?? null, 'course_id' => $lessons[$q->lesson_id]['course_id'] ?? null,
                ];
            })->all(),
            'meta' => ['open' => LessonQuestion::query()->where('status', 'open')->count()],
        ]);
    }

    public function answer(Request $request, string $question): JsonResponse
    {
        $data = $request->validate(['answer' => ['required', 'string', 'min:1', 'max:5000'], 'public' => ['sometimes', 'boolean']], [], ['answer' => 'الرد']);
        $found = LessonQuestion::query()->findOrFail($question);
        $found->update([
            'answer' => $data['answer'], 'public' => $data['public'] ?? $found->public, 'status' => 'answered',
            'answered_by_name' => $request->user()?->name, 'answered_at' => now(),
        ]);

        return response()->json(['data' => $found->toSummary()]);
    }

    public function hide(string $question): JsonResponse
    {
        $found = LessonQuestion::query()->findOrFail($question);
        if ($found->status === 'hidden') {
            throw new DomainRuleException('السؤال ده مخفي فعلاً.', 'question_hidden');
        }
        $found->update(['status' => 'hidden']);

        return response()->json(['data' => $found->toSummary()]);
    }
}
