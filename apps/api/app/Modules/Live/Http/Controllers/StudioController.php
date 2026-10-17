<?php

declare(strict_types=1);

namespace App\Modules\Live\Http\Controllers;

use App\Modules\Courses\Contracts\CourseCatalog;
use App\Modules\Live\Models\LiveMessage;
use App\Modules\Live\Models\LivePoll;
use App\Modules\Live\Models\LiveSession;
use App\Modules\Live\Support\Studio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The teacher's studio: the live lessons, going live, the floor, chat moderation, polls, attendance, ending. */
final class StudioController
{
    public function __construct(private readonly Studio $studio) {}

    public function index(CourseCatalog $courses): JsonResponse
    {
        $lessons = $courses->liveLessons();
        $sessions = LiveSession::query()->whereIn('lesson_id', array_column($lessons, 'id'))->get()->keyBy('lesson_id');

        return response()->json(['data' => array_map(fn (array $l) => [
            ...$l,
            'status' => $sessions[$l['id']]->status ?? 'idle',
            'peak_viewers' => $sessions[$l['id']]->peak_viewers ?? 0,
            'recording' => $sessions[$l['id']]->recording ?? 'none',
        ], $lessons)]);
    }

    public function show(string $lesson): JsonResponse
    {
        [$found, $session] = $this->studio->session($lesson);

        return response()->json(['data' => $this->studio->state($found, $session)]);
    }

    /** Into the room: the host publishes (and the lesson goes live), a moderator watches hidden. */
    public function join(Request $request, string $lesson): JsonResponse
    {
        [$found, $session] = $this->studio->session($lesson);
        $user = $request->user();
        $access = $this->studio->join($session, (string) $user->getAuthIdentifier(), (string) $user->name, $user->can('live.host'));

        return response()->json(['data' => [...$this->studio->state($found, $session->refresh()), 'livekit' => $access]]);
    }

    public function update(Request $request, string $lesson): JsonResponse
    {
        $data = $request->validate(['chat_mode' => ['required', Rule::in(LiveSession::CHAT_MODES)]]);
        [$found, $session] = $this->studio->session($lesson);
        $session->forceFill($data)->save();
        $this->studio->nudge($session);

        return response()->json(['data' => $this->studio->state($found, $session)]);
    }

    public function floor(Request $request, string $lesson): JsonResponse
    {
        $data = $request->validate(['learner_id' => ['required', 'uuid'], 'allow' => ['required', 'boolean'], 'camera' => ['sometimes', 'boolean']]);
        [, $session] = $this->studio->session($lesson);

        return response()->json(['data' => $this->studio->floor($session, $data['learner_id'], (bool) $data['allow'], (bool) ($data['camera'] ?? false))]);
    }

    public function obs(Request $request, string $lesson): JsonResponse
    {
        [$found, $session] = $this->studio->session($lesson);
        $this->studio->obs($session, (string) $request->user()->getAuthIdentifier(), (string) $request->user()->name);

        return response()->json(['data' => $this->studio->state($found, $session)]);
    }

    public function end(string $lesson): JsonResponse
    {
        [$found, $session] = $this->studio->session($lesson);
        $this->studio->end($session);

        return response()->json(['data' => $this->studio->state($found, $session->refresh())]);
    }

    public function feed(Request $request, string $lesson): JsonResponse
    {
        $request->validate(['after' => ['nullable', 'integer', 'min:0']]);
        [, $session] = $this->studio->session($lesson);

        return response()->json(['data' => $this->studio->feed($session, $request->integer('after'))]);
    }

    public function post(Request $request, string $lesson): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'min:1', 'max:1000']], [], ['body' => 'الرسالة']);
        [, $session] = $this->studio->session($lesson);
        $message = LiveMessage::query()->create([
            'session_id' => $session->id, 'learner_id' => null, 'author_name' => (string) $request->user()?->name,
            'kind' => 'chat', 'body' => trim($data['body']), 'created_at' => now(),
        ]);
        $this->studio->nudge($session);

        return response()->json(['data' => $message->refresh()->toSummary()], 201);
    }

    public function moderate(Request $request, string $message): JsonResponse
    {
        $data = $request->validate(['pinned' => ['sometimes', 'boolean'], 'answered' => ['sometimes', 'boolean'], 'hidden' => ['sometimes', 'boolean']]);
        $found = LiveMessage::query()->findOrFail($message);
        $found->update($data);
        $session = LiveSession::query()->find($found->session_id);
        if ($session !== null) {
            $this->studio->nudge($session, 'moderated');
        }

        return response()->json(['data' => $found->toSummary()]);
    }

    public function poll(Request $request, string $lesson): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'min:2', 'max:300'],
            'options' => ['required', 'array', 'min:2', 'max:6'],
            'options.*' => ['required', 'string', 'max:100'],
        ], [], ['question' => 'السؤال', 'options' => 'الاختيارات']);
        [, $session] = $this->studio->session($lesson);
        LivePoll::query()->where('session_id', $session->id)->whereNull('closed_at')->update(['closed_at' => now()]);
        $poll = LivePoll::query()->create(['session_id' => $session->id, 'question' => $data['question'], 'options' => array_values(array_map('trim', $data['options']))]);
        $this->studio->nudge($session, 'poll');

        return response()->json(['data' => $poll->toSummary(true)], 201);
    }

    public function closePoll(string $poll): JsonResponse
    {
        $found = LivePoll::query()->findOrFail($poll);
        $found->update(['closed_at' => $found->closed_at ?? now()]);
        $session = LiveSession::query()->find($found->session_id);
        if ($session !== null) {
            $this->studio->nudge($session, 'poll');
        }

        return response()->json(['data' => $found->toSummary(true)]);
    }

    public function attendance(string $lesson): JsonResponse
    {
        [, $session] = $this->studio->session($lesson);

        return response()->json(['data' => $this->studio->attendance($session)]);
    }
}
