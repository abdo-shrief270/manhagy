<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Http\Controllers;

use App\Modules\Assessments\Models\ExamAttempt;
use App\Modules\Assessments\Support\Sitting;
use App\Modules\Learners\Contracts\LearnerDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The student sitting an exam (learner routes). */
final class LearnerExamController
{
    public function __construct(private readonly Sitting $sitting, private readonly LearnerDirectory $learners) {}

    public function show(string $lesson): JsonResponse
    {
        return response()->json(['data' => $this->sitting->overview($this->learnerId(), $lesson)]);
    }

    public function start(string $lesson): JsonResponse
    {
        $attempt = $this->sitting->start($this->learnerId(), $lesson);

        return response()->json(['data' => $this->sitting->paper($attempt)], $attempt->wasRecentlyCreated ? 201 : 200);
    }

    public function paper(string $attempt): JsonResponse
    {
        return response()->json(['data' => $this->sitting->paper($this->attempt($attempt))]);
    }

    public function answer(Request $request, string $attempt, string $question): JsonResponse
    {
        $data = $request->validate(['answer' => ['present']]);
        $this->sitting->answer($this->attempt($attempt), $question, $data['answer']);

        return response()->json(['data' => ['saved' => true, 'server_now' => now()->toIso8601String()]]);
    }

    public function leave(string $attempt): JsonResponse
    {
        return response()->json(['data' => ['leaves' => $this->sitting->leave($this->attempt($attempt))]]);
    }

    public function submit(string $attempt): JsonResponse
    {
        $done = $this->sitting->submit($this->attempt($attempt));

        return response()->json(['data' => $this->sitting->result($done)]);
    }

    public function result(string $attempt): JsonResponse
    {
        return response()->json(['data' => $this->sitting->result($this->attempt($attempt))]);
    }

    private function learnerId(): string
    {
        return ($this->learners->current() ?? abort(401))['learner_id'];
    }

    private function attempt(string $id): ExamAttempt
    {
        return ExamAttempt::query()->with('exam')->where('learner_id', $this->learnerId())->findOrFail($id);
    }
}
