<?php

namespace Tests\Feature\Live;

use App\Modules\Live\Models\LiveSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesShops;
use Tests\Concerns\RunsAPlatform;
use Tests\TestCase;

/** Live lessons on LiveKit: tokens and what they allow, the floor, chat / polls, ending, the recording becoming the lesson. */
class LiveTest extends TestCase
{
    use CreatesShops, RefreshDatabase, RunsAPlatform;

    private const SECRET = 'livekit-secret-for-tests-0123456789';

    private string $live;

    private string $recordings;

    /** @var list<array<string, mixed>> what the fake server says is in the room */
    private array $participants = [];

    private string $egressStatus = 'EGRESS_ACTIVE';

    protected function setUp(): void
    {
        parent::setUp();
        $this->recordings = sys_get_temp_dir().'/live-recordings-'.bin2hex(random_bytes(4));
        @mkdir($this->recordings);
        config(['services.live' => [
            'url' => 'wss://live.manhagy.test', 'api_url' => 'http://livekit:7880', 'key' => 'APItest', 'secret' => self::SECRET,
            'recording' => true, 'recordings_path' => $this->recordings, 'egress_path' => '/out', 'ingress' => true,
        ]]);
        Http::fake(function (Request $request) {
            return match (basename((string) parse_url($request->url(), PHP_URL_PATH))) {
                'StartRoomCompositeEgress' => Http::response(['egress_id' => 'EG_1', 'status' => 'EGRESS_STARTING']),
                'ListEgress' => Http::response(['items' => [['egress_id' => 'EG_1', 'status' => $this->egressStatus, 'file_results' => [['filename' => '/out/recording.mp4']]]]]),
                'ListParticipants' => Http::response(['participants' => $this->participants]),
                'recording.mp4' => Http::response('fake-mp4-bytes'),
                'CreateIngress' => Http::response(['ingress_id' => 'IN_1', 'url' => 'rtmp://live.manhagy.test/x', 'stream_key' => 'obs-key-1']),
                default => Http::response([]),
            };
        });
        $this->setUpPlatform();
        $chapter = $this->asTeacher()->getJson("/api/v1/courses/{$this->courseId}")->json('data.chapters.0.id');
        $this->postJson("/api/v1/courses/{$this->courseId}/lessons", ['chapter_id' => $chapter, 'kind' => 'live', 'title' => 'مراجعة لايف'])
            ->assertUnprocessable()->assertJsonPath('code', 'live_time_required');
        $detail = $this->postJson("/api/v1/courses/{$this->courseId}/lessons", ['chapter_id' => $chapter, 'kind' => 'live', 'title' => 'مراجعة لايف', 'publish_at' => now()->addHour()->toIso8601String()])->assertCreated()->json('data');
        $this->live = collect($detail['chapters'][0]['lessons'])->firstWhere('kind', 'live')['id'];
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->recordings.'/*') ?: []);
        @rmdir($this->recordings);
        parent::tearDown();
    }

    /** @return array<string, mixed> the claims of a token, after checking its signature */
    private function claims(string $jwt): array
    {
        [$h, $p, $s] = explode('.', $jwt);
        $this->assertSame(rtrim(strtr(base64_encode(hash_hmac('sha256', "{$h}.{$p}", self::SECRET, true)), '+/', '-_'), '='), $s);

        return json_decode(base64_decode(strtr($p, '-_', '+/')), true);
    }

    /** @return Collection<int, array<string, mixed>> the bodies sent to one server API method */
    private function sent(string $method): Collection
    {
        return Http::recorded()->filter(fn ($pair) => str_ends_with($pair[0]->url(), "/{$method}"))->map(fn ($pair) => $pair[0]->data())->values();
    }

    private function lastSignal(): string
    {
        return json_decode(base64_decode($this->sent('SendData')->last()['data']), true)['type'];
    }

    private function enroll(): void
    {
        $this->asTeacher()->postJson('/api/v1/enrollments', ['learner_id' => $this->learnerId, 'course_id' => $this->courseId])->assertSuccessful();
    }

    /** @return array<string, mixed> */
    private function goLive(): array
    {
        return $this->asTeacher()->postJson("/api/v1/live/{$this->live}/join")->assertOk()->json('data');
    }

    public function test_the_teacher_goes_live_and_an_enrolled_student_joins(): void
    {
        $studio = $this->asTeacher()->getJson("/api/v1/live/{$this->live}")->assertOk()->json('data');
        $this->assertSame('idle', $studio['status']);
        $this->assertTrue($studio['server_ready']);

        // Not enrolled: the room refuses. Enrolled, before the teacher starts: the waiting room, no token.
        $this->asStudent()->postJson("/api/v1/learner/live/{$this->live}/join")->assertForbidden()->assertJsonPath('code', 'not_enrolled');
        $this->enroll();
        $waiting = $this->asStudent()->postJson("/api/v1/learner/live/{$this->live}/join")->assertOk()->json('data');
        $this->assertSame('idle', $waiting['status']);
        $this->assertArrayNotHasKey('livekit', $waiting);
        $this->asStudent()->postJson("/api/v1/learner/lessons/{$this->live}/open")->assertForbidden()->assertJsonPath('code', 'not_yet');

        // The teacher comes in (an hour early is fine): the room, the recording, a token that publishes.
        $on = $this->goLive();
        $this->assertSame('live', $on['status']);
        $this->assertSame('recording', $on['recording']);
        $session = LiveSession::withoutTenancy()->sole();
        $this->assertSame("live-{$session->id}", $this->sent('CreateRoom')->first()['name']);
        $this->assertSame("/out/live-{$session->id}.mp4", $this->sent('StartRoomCompositeEgress')->first()['file_outputs'][0]['filepath']);
        $teacher = $this->claims($on['livekit']['token']);
        $this->assertSame('wss://live.manhagy.test', $on['livekit']['url']);
        $this->assertSame('APItest', $teacher['iss']);
        $this->assertTrue($teacher['video']['canPublish']);
        $this->assertContains('screen_share', $teacher['video']['canPublishSources']);
        $this->assertSame("live-{$session->id}", $teacher['video']['room']);
        $this->assertDatabaseHas('audit_log', ['action' => 'live.started']);

        // The student: watches and listens, can't publish anything.
        $joined = $this->asStudent()->postJson("/api/v1/learner/live/{$this->live}/join")->assertOk()->json('data');
        $this->assertSame('live', $joined['status']);
        $this->assertSame('يوسف أحمد · 01012345487', $joined['watermark']['text']);
        $student = $this->claims($joined['livekit']['token']);
        $this->assertSame('s:'.$this->learnerId, $student['sub']);
        $this->assertSame('يوسف أحمد', $student['name']);
        $this->assertFalse($student['video']['canPublish']);
        $this->assertFalse($student['video']['canPublishData']);
        $this->assertTrue($student['video']['canSubscribe']);

        // Beats credit the minutes; viewers counted.
        $this->travel(30)->seconds();
        $beat = $this->asStudent()->postJson("/api/v1/learner/live/attendances/{$joined['attendance_id']}/beat", ['playing' => true])->assertOk()->json('data');
        $this->assertSame(30, $beat['seconds']);
        $this->assertSame(1, $beat['viewers']);

        // The same student on another device: the older one is told it was replaced.
        $this->asStudent()->postJson("/api/v1/learner/live/{$this->live}/join")->assertOk();
        $this->asStudent()->postJson("/api/v1/learner/live/attendances/{$joined['attendance_id']}/beat", ['playing' => true])
            ->assertStatus(409)->assertJsonPath('reason', 'replaced');

        $attended = $this->asTeacher()->getJson("/api/v1/live/{$this->live}/attendance")->assertOk()->json('data');
        $this->assertSame('يوسف أحمد', $attended[0]['name']);
        $this->assertSame(2, $attended[0]['joins']);

        // OBS: an RTMP input joining as the teacher; the key stored encrypted.
        $obs = $this->postJson("/api/v1/live/{$this->live}/obs")->assertOk()->json('data.obs');
        $this->assertSame(['server' => 'rtmp://live.manhagy.test/x', 'key' => 'obs-key-1'], $obs);
        $this->assertNotSame('obs-key-1', DB::table('live_sessions')->value('ingress_key'));
    }

    public function test_a_raised_hand_gets_the_floor_and_gives_it_back(): void
    {
        $this->enroll();
        $this->asStudent()->postJson("/api/v1/learner/live/{$this->live}/hand", ['raised' => true])->assertUnprocessable()->assertJsonPath('code', 'live_not_on_air');
        $this->goLive();
        $identity = 's:'.$this->learnerId;

        $hand = $this->asStudent()->postJson("/api/v1/learner/live/{$this->live}/hand", ['raised' => true])->assertOk()->json('data');
        $this->assertSame('raised', $hand['status']);
        $this->assertSame($identity, $hand['identity']);
        $this->assertSame('hands', $this->lastSignal());
        $this->assertSame(['يوسف أحمد'], array_column($this->asTeacher()->getJson("/api/v1/live/{$this->live}/feed")->json('data.hands'), 'name'));

        // The teacher gives the microphone and the camera: the server is told at once.
        $this->postJson("/api/v1/live/{$this->live}/floor", ['learner_id' => $this->learnerId, 'allow' => true, 'camera' => true])
            ->assertOk()->assertJsonPath('data.status', 'speaking');
        $update = $this->sent('UpdateParticipant')->last();
        $this->assertSame($identity, $update['identity']);
        $this->assertTrue($update['permission']['can_publish']);
        $this->assertSame(['MICROPHONE', 'CAMERA'], $update['permission']['can_publish_sources']);

        // Reconnecting while speaking keeps it.
        $again = $this->asStudent()->postJson("/api/v1/learner/live/{$this->live}/join")->json('data');
        $this->assertSame(['microphone', 'camera'], $this->claims($again['livekit']['token'])['video']['canPublishSources']);
        $this->assertSame('speaking', $again['hand']['status']);

        // Lowering the hand while speaking gives the floor back; the teacher can also give and take it.
        $this->asStudent()->postJson("/api/v1/learner/live/{$this->live}/hand", ['raised' => false])->assertOk()->assertJsonPath('data', null);
        $this->assertFalse($this->sent('UpdateParticipant')->last()['permission']['can_publish']);
        $this->assertSame([], $this->asTeacher()->getJson("/api/v1/live/{$this->live}/feed")->json('data.hands'));
        $this->postJson("/api/v1/live/{$this->live}/floor", ['learner_id' => $this->learnerId, 'allow' => true])->assertOk();
        $this->assertSame(['MICROPHONE'], $this->sent('UpdateParticipant')->last()['permission']['can_publish_sources']);
        $this->postJson("/api/v1/live/{$this->live}/floor", ['learner_id' => $this->learnerId, 'allow' => false])->assertOk()->assertJsonPath('data.status', 'done');
        $this->assertSame([], $this->sent('UpdateParticipant')->last()['permission']['can_publish_sources']);
        $this->assertNull($this->asStudent()->getJson("/api/v1/learner/live/{$this->live}/feed")->json('data.hand'));
    }

    public function test_chat_questions_moderation_and_polls(): void
    {
        $this->enroll();
        $this->asStudent()->postJson("/api/v1/learner/live/{$this->live}/messages", ['kind' => 'chat', 'body' => 'أهلاً'])->assertUnprocessable()->assertJsonPath('code', 'live_not_on_air');
        $this->goLive();

        $this->asStudent()->postJson("/api/v1/learner/live/{$this->live}/messages", ['kind' => 'chat', 'body' => 'أهلاً يا مستر'])->assertCreated();
        $question = $this->asStudent()->postJson("/api/v1/learner/live/{$this->live}/messages", ['kind' => 'question', 'body' => 'يعني إيه مقاومة نوعية؟'])->assertCreated()->json('data');
        $this->assertTrue($question['mine']);
        $this->assertSame('feed', $this->lastSignal());

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
        $this->assertSame('poll', $this->lastSignal());
        $this->assertNull($this->asStudent()->getJson("/api/v1/learner/live/{$this->live}/feed")->json('data.poll.results'));
        $this->asStudent()->postJson("/api/v1/learner/live/polls/{$poll['id']}/vote", ['option' => 0])->assertOk()->assertJsonPath('data.results', [1, 0, 0]);
        $this->asStudent()->postJson("/api/v1/learner/live/polls/{$poll['id']}/vote", ['option' => 1])->assertOk()->assertJsonPath('data.my_vote', 0);
        $this->asStudent($other)->postJson("/api/v1/learner/live/polls/{$poll['id']}/vote", ['option' => 2])->assertOk()->assertJsonPath('data.results', [1, 0, 1]);
        $this->asTeacher()->postJson("/api/v1/live/polls/{$poll['id']}/close")->assertOk();
        $this->asStudent()->postJson("/api/v1/learner/live/polls/{$poll['id']}/vote", ['option' => 1])->assertUnprocessable()->assertJsonPath('code', 'poll_closed');
    }

    public function test_ending_collects_the_recording_and_it_becomes_the_lesson(): void
    {
        $this->enroll();
        $this->goLive();
        $session = LiveSession::withoutTenancy()->sole();
        $joined = $this->asStudent()->postJson("/api/v1/learner/live/{$this->live}/join")->json('data');

        $this->asTeacher()->postJson("/api/v1/live/{$this->live}/end")->assertOk()->assertJsonPath('data.status', 'ended')->assertJsonPath('data.recording', 'collecting');
        $this->assertDatabaseHas('audit_log', ['action' => 'live.ended']);
        $this->assertSame('EG_1', $this->sent('StopEgress')->first()['egress_id']);
        $this->assertSame("live-{$session->id}", $this->sent('DeleteRoom')->first()['room']);
        $this->assertSame('ended', $this->lastSignal());

        // Students are told; the room can't be entered again.
        $this->asStudent()->postJson("/api/v1/learner/live/attendances/{$joined['attendance_id']}/beat", ['playing' => true])->assertStatus(409)->assertJsonPath('reason', 'session_ended');
        $this->assertArrayNotHasKey('livekit', $this->asStudent()->postJson("/api/v1/learner/live/{$this->live}/join")->json('data'));
        $this->asTeacher()->postJson("/api/v1/live/{$this->live}/join")->assertUnprocessable()->assertJsonPath('code', 'live_ended');

        // Egress still writing: nothing yet. Done: the file (seen from the API's side) is imported (fake host = ready at once).
        $this->artisan('live:sync')->assertSuccessful();
        $this->assertSame('collecting', $session->refresh()->recording);
        file_put_contents($this->recordings.'/recording.mp4', 'fake-mp4-bytes');
        $this->egressStatus = 'EGRESS_COMPLETE';
        $this->artisan('live:sync')->assertSuccessful();
        $this->assertSame('ready', $session->refresh()->recording);

        $open = $this->asStudent()->postJson("/api/v1/learner/lessons/{$this->live}/open")->assertOk()->json('data');
        $this->assertSame('video', $open['kind']);
        $this->assertTrue($this->asTeacher()->getJson('/api/v1/live')->assertOk()->json('data.0.has_replay'));
    }

    public function test_from_a_live_server_of_its_own_the_recording_is_downloaded(): void
    {
        config(['services.live.recordings_url' => 'https://live.manhagy.test/recordings']);
        $this->goLive();
        $this->postJson("/api/v1/live/{$this->live}/end")->assertOk();
        $this->egressStatus = 'EGRESS_COMPLETE';
        $this->artisan('live:sync')->assertSuccessful();

        Http::assertSent(fn ($r) => $r->url() === 'https://live.manhagy.test/recordings/recording.mp4' && $r->header('Authorization') === ['Bearer '.self::SECRET]);
        $this->assertSame('ready', LiveSession::withoutTenancy()->sole()->recording);
    }

    public function test_a_room_left_empty_ends_by_itself(): void
    {
        $this->goLive();
        $session = LiveSession::withoutTenancy()->sole();
        $this->participants = [['identity' => 't:x']];
        $this->artisan('live:sync');
        $this->participants = [];
        $this->artisan('live:sync');
        $this->travel(10)->minutes();
        $this->artisan('live:sync');
        $this->assertSame('live', $session->refresh()->status);
        $this->travel(11)->minutes();
        $this->artisan('live:sync');
        $this->assertSame('ended', $session->refresh()->status);
    }

    public function test_without_a_server_the_studio_says_so(): void
    {
        config(['services.live.secret' => '']);
        $this->assertFalse($this->asTeacher()->getJson("/api/v1/live/{$this->live}")->json('data.server_ready'));
        $this->postJson("/api/v1/live/{$this->live}/join")->assertStatus(503)->assertJsonPath('code', 'live_unavailable');
    }
}
