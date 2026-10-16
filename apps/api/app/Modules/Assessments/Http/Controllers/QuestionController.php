<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Http\Controllers;

use App\Modules\Assessments\Models\AttemptAnswer;
use App\Modules\Assessments\Models\ExamQuestion;
use App\Modules\Assessments\Models\Question;
use App\Modules\Courses\Contracts\CourseCatalog;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The question bank of a course (closed questions: multiple choice, true / false, fill in). */
final class QuestionController
{
    public function __construct(private readonly CourseCatalog $courses) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'uuid'],
            'chapter_id' => ['nullable', 'uuid'],
            'kind' => ['nullable', Rule::in(Question::KINDS)],
            'active' => ['nullable', 'boolean'],
        ]);
        $rows = Question::query()->where('course_id', $data['course_id'])
            ->when($data['chapter_id'] ?? null, fn ($q, $id) => $q->where('chapter_id', $id))
            ->when($data['kind'] ?? null, fn ($q, $k) => $q->where('kind', $k))
            ->when($request->has('active'), fn ($q) => $q->where('active', $request->boolean('active')))
            ->orderBy('created_at')->limit(2000)->get();

        return response()->json(['data' => $rows->map(fn (Question $q) => $q->toSummary())->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['course_id' => ['required', 'uuid'], 'kind' => ['required', Rule::in(Question::KINDS)]]);
        $this->courses->course($data['course_id'], evenUnpublished: true) ?? throw new DomainRuleException('الكورس ده مش موجود.', 'course_unknown', 404);
        $question = Question::query()->create([...$data, ...$this->content($request, $data['course_id'], $data['kind'], null)]);

        return response()->json(['data' => $question->toSummary()], 201);
    }

    public function update(Request $request, string $question): JsonResponse
    {
        $found = Question::query()->findOrFail($question);
        $content = $this->content($request, $found->course_id, $found->kind, $found);
        $changesGrading = (array_key_exists('options', $content) && $content['options'] != $found->options)
            || (array_key_exists('answer', $content) && $content['answer'] !== $found->answer);
        if ($changesGrading && AttemptAnswer::query()->where('question_id', $found->id)->exists()) {
            throw new DomainRuleException('طلاب جاوبوا على السؤال ده. اعمل سؤال جديد بدل ما تغيّر الإجابة.', 'question_answered');
        }
        $found->update($content);

        return response()->json(['data' => $found->toSummary()]);
    }

    /** A question in an exam or already answered is retired, not deleted. */
    public function destroy(string $question): JsonResponse
    {
        $found = Question::query()->findOrFail($question);
        if (ExamQuestion::query()->where('question_id', $found->id)->exists() || AttemptAnswer::query()->where('question_id', $found->id)->exists()) {
            $found->update(['active' => false]);

            return response()->json(['data' => $found->toSummary()]);
        }
        $found->delete();

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    private function content(Request $request, string $courseId, string $kind, ?Question $current): array
    {
        $required = $current === null ? 'required' : 'sometimes';
        $data = $request->validate([
            'body' => [$required, 'string', 'min:2', 'max:3000'],
            'chapter_id' => ['sometimes', 'nullable', 'uuid', Rule::in(array_column($this->courses->chapters($courseId), 'id'))],
            'explanation' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'points' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'difficulty' => ['sometimes', Rule::in(Question::DIFFICULTIES)],
            'active' => ['sometimes', 'boolean'],
            // mcq: the choices and the index of the right one
            'options' => [$kind === 'mcq' ? $required : 'prohibited', 'array', 'min:2', 'max:6'],
            'options.*' => ['string', 'min:1', 'max:500', 'distinct'],
            'correct' => [in_array($kind, ['mcq', 'true_false'], true) ? $required : 'prohibited', $kind === 'mcq' ? 'integer' : 'boolean'],
            // fill: the accepted answers
            'accepted' => [$kind === 'fill' ? $required : 'prohibited', 'array', 'min:1', 'max:5'],
            'accepted.*' => ['string', 'min:1', 'max:200'],
        ], [], ['body' => 'السؤال', 'options' => 'الاختيارات', 'correct' => 'الإجابة الصح', 'accepted' => 'الإجابات المقبولة']);

        $out = array_intersect_key($data, array_flip(['body', 'chapter_id', 'explanation', 'points', 'difficulty', 'active']));
        if ($kind === 'mcq' && (isset($data['options']) || isset($data['correct']))) {
            $texts = $data['options'] ?? array_column($current->options ?? [], 'text');
            $options = array_map(fn (int $i, string $text) => ['id' => chr(97 + $i), 'text' => $text], array_keys($texts), $texts);
            $index = $data['correct'] ?? array_search($current?->answer, array_column($options, 'id'), true);
            if (! is_int($index) || ! isset($options[$index])) {
                throw new DomainRuleException('اختار الإجابة الصح من الاختيارات.', 'question_answer_missing');
            }
            $out += ['options' => $options, 'answer' => $options[$index]['id']];
        } elseif ($kind === 'true_false' && isset($data['correct'])) {
            $out['answer'] = (bool) $data['correct'];
        } elseif ($kind === 'fill' && isset($data['accepted'])) {
            $out['answer'] = array_values(array_unique(array_map('trim', $data['accepted'])));
        } elseif (in_array($kind, Question::MANUAL, true) && $current === null) {
            $out['answer'] = null;
        }

        return $out;
    }
}
