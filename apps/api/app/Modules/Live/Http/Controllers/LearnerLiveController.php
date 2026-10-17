<?php

declare(strict_types=1);

namespace App\Modules\Live\Http\Controllers;

use App\Modules\Learners\Contracts\LearnerDirectory;
use App\Modules\Live\Support\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The student in a live lesson: waiting room, joining, beats, chat and questions, polls. */
final class LearnerLiveController
{
    public function __construct(private readonly Room $room, private readonly LearnerDirectory $learners) {}

    public function show(string $lesson): JsonResponse
    {
        [$found, $session] = $this->room->enter($this->learner(), $lesson);

        return response()->json(['data' => $this->room->state($found, $session)]);
    }

    public function join(Request $request, string $lesson): JsonResponse
    {
        return response()->json(['data' => $this->room->join($this->learner(), $lesson, $request->ip())]);
    }

    public function beat(Request $request, string $attendance): JsonResponse
    {
        $data = $request->validate(['playing' => ['required', 'boolean']]);

        return response()->json(['data' => $this->room->beat($this->learner(), $attendance, (bool) $data['playing'])]);
    }

    public function leave(string $attendance): JsonResponse
    {
        $this->room->leave($this->learner(), $attendance);

        return response()->json(null, 204);
    }

    public function feed(Request $request, string $lesson): JsonResponse
    {
        $request->validate(['after' => ['nullable', 'integer', 'min:0']]);
        $after = $request->integer('after');
        $learner = $this->learner();
        [$found, $session] = $this->room->enter($learner, $lesson);

        return response()->json(['data' => $this->room->feed($found, $session, $learner['learner_id'], $after)]);
    }

    public function post(Request $request, string $lesson): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['chat', 'question'])],
            'body' => ['required', 'string', 'min:1', 'max:500'],
        ], [], ['body' => 'الرسالة']);
        $learner = $this->learner();
        [, $session] = $this->room->enter($learner, $lesson);

        return response()->json(['data' => $this->room->post($learner, $session, $data['kind'], trim($data['body']))], 201);
    }

    public function hand(Request $request, string $lesson): JsonResponse
    {
        $data = $request->validate(['raised' => ['required', 'boolean']]);
        $learner = $this->learner();
        [, $session] = $this->room->enter($learner, $lesson);

        return response()->json(['data' => $this->room->hand($learner, $session, (bool) $data['raised'])]);
    }

    public function vote(Request $request, string $poll): JsonResponse
    {
        $data = $request->validate(['option' => ['required', 'integer', 'min:0', 'max:9']]);

        return response()->json(['data' => $this->room->vote($this->learner(), $poll, (int) $data['option'])]);
    }

    /** @return array<string, mixed> */
    private function learner(): array
    {
        return $this->learners->current() ?? abort(401);
    }
}
