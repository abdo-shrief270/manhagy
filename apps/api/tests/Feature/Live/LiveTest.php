<?php

namespace Tests\Feature\Live;

use App\Modules\Live\Models\LiveSession;
use App\Modules\Live\Support\LiveServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesShops;
use Tests\Concerns\RunsAPlatform;
use Tests\TestCase;

/** Live lessons: the broadcast server asks our hook, students join / beat / chat, the recording becomes the lesson. */
class LiveTest extends TestCase
{
    use CreatesShops, RefreshDatabase, RunsAPlatform;

    private const SECRET = 'live-secret-for-tests-0123456789';

    private string $live;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.live' => [
            'hls_url' => 'https://live.manhagy.test', 'whip_url' => 'https://live.manhagy.test', 'rtmp_url' => 'rtmp://live.manhagy.test:1935',
            'playback_url' => 'http://live:9996', 'api_url' => 'http://live:9997', 'secret' => self::SECRET,
        ]]);
        $this->setUpPlatform();
        $chapter = $this->asTeacher()->getJson("/api/v1/courses/{$this->courseId}")->json('data.chapters.0.id');
        $this->postJson("/api/v1/courses/{$this->courseId}/lessons", ['chapter_id' => $chapter, 'kind' => 'live', 'title' => 'مراجعة لايف'])
            ->assertUnprocessable()->assertJsonPath('code', 'live_time_required');
        $detail = $this->postJson("/api/v1/courses/{$this->courseId}/lessons", ['chapter_id' => $chapter, 'kind' => 'live', 'title' => 'مراجعة لايف', 'publish_at' => now()->addHour()->toIso8601String()])->assertCreated()->json('data');
        $this->live = collect($detail['chapters'][0]['lessons'])->firstWhere('kind', 'live')['id'];
    }

    private function hook(array $request): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withoutToken()->postJson('/api/v1/public/live/hook/'.self::SECRET, $request);
    }

    private function enroll(): void
    {
        $this->asTeacher()->postJson('/api/v1/enrollments', ['learner_id' => $this->learnerId, 'course_id' => $this->courseId])->assertSuccessful();
    }

    public function test_the_teacher_goes_live_and_an_enrolled_student_watches(): void
    {
        $studio = $this->asTeacher()->getJson("/api/v1/live/{$this->live}")->assertOk()->json('data');
        $this->assertSame('idle', $studio['status']);
        $this->assertStringContainsString("/live/{$studio['session_id']}/whip?key=", $studio['publishing']['whip']);
        $this->assertSame('rtmp://live.manhagy.test:1935/live', $studio['publishing']['rtmp_server']);
        $key = DB::table('live_sessions')->value('id') && LiveSession::withoutTenancy()->sole()->stream_key;
        $session = LiveSession::withoutTenancy()->sole();
        $path = "live/{$session->id}";

        // Not enrolled: the room refuses.
        $this->asStudent()->postJson("/api/v1/learner/live/{$this->live}/join")->assertForbidden()->assertJsonPath('code', 'not_enrolled');
        $this->enroll();
        // Before the teacher starts: the waiting room, no stream.
        $waiting = $this->asStudent()->postJson("/api/v1/learner/live/{$this->live}/join")->assertOk()->json('data');
        $this->assertSame('idle', $waiting['status']);
        $this->assertArrayNotHasKey('stream_url', $waiting);
        // The outline sends the student to the room.
        $this->asStudent()->postJson("/api/v1/learner/lessons/{$this->live}/open")->assertForbidden()->assertJsonPath('code', 'not_yet');

        // Publishing: a wrong key is refused, the right one puts it on air (an hour early is fine).
        $this->hook(['action' => 'publish', 'path' => $path, 'query' => 'key=wrong', 'protocol' => 'webrtc'])->assertStatus(401);
        $this->hook(['action' => 'publish', 'path' => $path, 'query' => 'key='.$session->stream_key, 'protocol' => 'webrtc'])->assertOk();
        $this->assertSame('live', $session->refresh()->status);

        $joined = $this->asStudent()->postJson("/api/v1/learner/live/{$this->live}/join")->assertOk()->json('data');
        $this->assertSame('live', $joined['status']);
        $this->assertSame('يوسف أحمد · 01012345487', $joined['watermark']['text']);
        $this->assertStringStartsWith("https://live.manhagy.test/{$path}/index.m3u8?t=", $joined['stream_url']);
        $query = parse_url($joined['stream_url'], PHP_URL_QUERY);

        // Reading: the live server asks with the student's token.
        $this->hook(['action' => 'read', 'path' => $path, 'query' => $query, 'protocol' => 'hls'])->assertOk();
        $this->hook(['action' => 'read', 'path' => $path, 'query' => 't=forged.token', 'protocol' => 'hls'])->assertStatus(401);
        $this->hook(['action' => 'read', 'path' => 'live/'.Str::uuid(), 'query' => $query, 'protocol' => 'hls'])->assertStatus(401);

        // Beats credit the minutes; viewers counted.
        $this->travel(30)->seconds();
        $beat = $this->asStudent()->postJson("/api/v1/learner/live/attendances/{$joined['attendance_id']}/beat", ['playing' => true])->assertOk()->json('data');
        $this->assertSame(30, $beat['seconds']);
        $this->assertSame(1, $beat['viewers']);

        // The same student on another device: the first one's token stops working.
        $other = $this->asStudent()->postJson("/api/v1/learner/live/{$this->live}/join")->assertOk()->json('data');
        $this->hook(['action' => 'read', 'path' => $path, 'query' => $query, 'protocol' => 'hls'])->assertStatus(401);
        $this->asStudent()->postJson("/api/v1/learner/live/attendances/{$joined['attendance_id']}/beat", ['playing' => true])
            ->assertStatus(409)->assertJsonPath('reason', 'replaced');
        $this->hook(['action' => 'read', 'path' => $path, 'query' => parse_url($other['stream_url'], PHP_URL_QUERY), 'protocol' => 'hls'])->assertOk();

        // Our own service user (recordings, the API); anyone else is refused.
        $this->hook(['action' => 'playback', 'path' => $path, 'user' => LiveServer::SERVICE_USER, 'password' => self::SECRET])->assertOk();
        $this->hook(['action' => 'playback', 'path' => $path, 'user' => LiveServer::SERVICE_USER, 'password' => 'nope'])->assertStatus(401);
        $this->withoutToken()->postJson('/api/v1/public/live/hook/wrong-secret-0000000000', ['action' => 'publish'])->assertNotFound();

        // Attendance for the teacher.
        $attended = $this->asTeacher()->getJson("/api/v1/live/{$this->live}/attendance")->assertOk()->json('data');
        $this->assertSame('يوسف أحمد', $attended[0]['name']);
        $this->assertSame(2, $attended[0]['joins']);
    }

    public function test_chat_questions_moderation_and_polls(): void
    {
        $this->enroll();
        $session = $this->asTeacher()->getJson("/api/v1/live/{$this->live}")->json('data');
        $this->asStudent()->postJson("/api/v1/learner/live/{$this->live}/messages", ['kind' => 'chat', 'body' => 'أهلاً'])->assertUnprocessable()->assertJsonPath('code', 'live_not_on_air');
        $this->hook(['action' => 'publish', 'path' => "live/{$session['session_id']}", 'query' => 'key='.LiveSession::withoutTenancy()->sole()->stream_key]);

        $this->asStudent()->postJson("/api/v1/learner/live/{$this->live}/messages", ['kind' => 'chat', 'body' => 'أهلاً يا مستر'])->assertCreated();
        $question = $this->asStudent()->postJson("/api/v1/learner/live/{$this->live}/messages", ['kind' => 'question', 'body' => 'يعني إيه مقاومة نوعية؟'])->assertCreated()->json('data');
        $this->assertTrue($question['mine']);

        // Another student sees the chat, not someone else's question.
        $other = $this->registerStudent('01099988877', 'منة خالد');
        $this->asTeacher()->postJson('/api/v1/enrollments', ['learner_id' => DB::table('learners')->where('phone', '+201099988877')->value('id'), 'course_id' => $this->courseId])->assertSuccessful();
        $feed = $this->asStudent($other)->getJson("/api/v1/learner/live/{$this->live}/feed")->assertOk()->json('data');
        $this->assertSame(['أهلاً يا مستر'], array_column($feed['messages'], 'body'));

        // The teacher pins the question (everyone sees it) and hides a chat message; replies.
        $all = $this->asTeacher()->getJson("/api/v1/live/{$this->live}/feed")->json('data.messages');
        $this->assertCount(2, $all);
        $this->patchJson("/api/v1/live/messages/{$question['id']}", ['pinned' => true, 'answered' => true])->assertOk();
        $this->patchJson("/api/v1/live/messages/{$all[0]['id']}", ['hidden' => true])->assertOk();
        $this->postJson("/api/v1/live/{$this->live}/messages", ['body' => 'سؤال حلو، هشرحه دلوقتي'])->assertCreated();
        Cache::flush();
        $feed = $this->asStudent($other)->getJson("/api/v1/learner/live/{$this->live}/feed")->json('data');
        $this->assertSame(['يعني إيه مقاومة نوعية؟', 'سؤال حلو، هشرحه دلوقتي'], array_column($feed['messages'], 'body'));
        $this->assertTrue($feed['messages'][1]['from_teacher']);

        // Questions only: chat refused, questions still go.
        $this->asTeacher()->patchJson("/api/v1/live/{$this->live}", ['chat_mode' => 'questions'])->assertOk();
        $this->asStudent()->postJson("/api/v1/learner/live/{$this->live}/messages", ['kind' => 'chat', 'body' => 'هاي'])->assertUnprocessable()->assertJsonPath('code', 'live_chat_closed');
        $this->asStudent()->postJson("/api/v1/learner/live/{$this->live}/messages", ['kind' => 'question', 'body' => 'تاني سؤال'])->assertCreated();

        // A poll: results after voting, once per student.
        $poll = $this->asTeacher()->postJson("/api/v1/live/{$this->live}/polls", ['question' => 'فهمت؟', 'options' => ['أيوه', 'لأ', 'نص نص']])->assertCreated()->json('data');
        $seen = $this->asStudent()->getJson("/api/v1/learner/live/{$this->live}/feed")->json('data.poll');
        $this->assertNull($seen['results']);
        $this->asStudent()->postJson("/api/v1/learner/live/polls/{$poll['id']}/vote", ['option' => 0])->assertOk()->assertJsonPath('data.results', [1, 0, 0]);
        $this->asStudent()->postJson("/api/v1/learner/live/polls/{$poll['id']}/vote", ['option' => 1])->assertOk()->assertJsonPath('data.my_vote', 0);
        $this->asStudent($other)->postJson("/api/v1/learner/live/polls/{$poll['id']}/vote", ['option' => 2])->assertOk()->assertJsonPath('data.results', [1, 0, 1]);
        $this->asTeacher()->postJson("/api/v1/live/polls/{$poll['id']}/close")->assertOk();
        $this->asStudent()->postJson("/api/v1/learner/live/polls/{$poll['id']}/vote", ['option' => 1])->assertUnprocessable()->assertJsonPath('code', 'poll_closed');
    }

    public function test_ending_collects_the_recording_and_it_becomes_the_lesson(): void
    {
        $this->enroll();
        $this->asTeacher()->getJson("/api/v1/live/{$this->live}");
        $session = LiveSession::withoutTenancy()->sole();
        $this->hook(['action' => 'publish', 'path' => $session->path(), 'query' => 'key='.$session->stream_key]);
        $joined = $this->asStudent()->postJson("/api/v1/learner/live/{$this->live}/join")->json('data');

        Http::fake([
            'live:9996/list*' => Http::response([['start' => '2026-10-17T10:00:00Z', 'duration' => 1800.5], ['start' => '2026-10-17T10:31:00Z', 'duration' => 600]]),
            'live:9996/get*' => Http::response('fake-mp4-bytes'),
        ]);
        $this->asTeacher()->postJson("/api/v1/live/{$this->live}/end")->assertOk()->assertJsonPath('data.status', 'ended');
        $this->assertDatabaseHas('audit_log', ['action' => 'live.ended']);

        // Students are told; publishing again is refused.
        $this->asStudent()->postJson("/api/v1/learner/live/attendances/{$joined['attendance_id']}/beat", ['playing' => true])->assertStatus(409)->assertJsonPath('reason', 'session_ended');
        $this->hook(['action' => 'publish', 'path' => $session->path(), 'query' => 'key='.$session->stream_key])->assertStatus(401);

        // The queue runs inline here: downloaded from the first second to the last, imported (fake host = ready at once).
        Http::assertSent(fn ($r) => str_contains($r->url(), '/get?') && str_contains($r->url(), 'duration=2460') && $r->hasHeader('Authorization'));
        $session->refresh();
        $this->assertSame('processing', $session->recording);
        $this->artisan('live:sync')->assertSuccessful();
        $this->assertSame('ready', $session->refresh()->recording);

        // The lesson now plays the recording.
        $open = $this->asStudent()->postJson("/api/v1/learner/lessons/{$this->live}/open")->assertOk()->json('data');
        $this->assertSame('video', $open['kind']);
        $this->assertSame(1, $this->asTeacher()->getJson('/api/v1/live')->assertOk()->json('data.0.has_replay') ? 1 : 0);
    }
}
