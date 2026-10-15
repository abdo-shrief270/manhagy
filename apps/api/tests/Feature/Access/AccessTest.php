<?php

namespace Tests\Feature\Access;

use App\Modules\Access\Contracts\Enrollments;
use App\Modules\Identity\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesShops;
use Tests\TestCase;

/** Who may watch what: enrollment, lesson locks, one playback session, counting a view by time watched. */
class AccessTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    private User $teacher;

    private string $course;

    /** @var array<string, string> lesson ids by name */
    private array $lessons = [];

    private string $learnerId;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teacher = $this->actingAsOwnerOf();
        $this->putJson('/api/v1/site', ['slug' => 'mr-mohamed'])->assertOk();

        $subject = DB::table('curriculum_nodes')->where('kind', 'subject')->value('id');
        $course = $this->postJson('/api/v1/courses', ['curriculum_node_id' => $subject, 'title' => 'فيزياء', 'access_days' => 30])->json('data');
        $this->course = $course['id'];
        $chapter = $course['chapters'][0]['id'];
        $video = fn () => tap($this->postJson('/api/v1/media/videos', ['title' => 'فيديو'])->json('data.id'), fn ($id) => $this->postJson("/api/v1/media/{$id}/check"));
        $pdf = DB::table('media_assets')->insertGetId([
            'id' => $pdfId = (string) str()->uuid7(), 'tenant_id' => $this->teacher->tenant_id, 'kind' => 'file', 'title' => 'ملزمة',
            'path' => 'media/x.pdf', 'status' => 'ready', 'created_at' => now(), 'updated_at' => now(),
        ], 'id');

        foreach ([
            'intro' => ['kind' => 'video', 'media_id' => $video(), 'free_preview' => true],
            'one' => ['kind' => 'video', 'media_id' => $video(), 'max_views' => 2, 'count_percent' => 10],
            'two' => ['kind' => 'video', 'media_id' => $video(), 'requires_previous' => true],
            'notes' => ['kind' => 'pdf', 'media_id' => $pdf],
            'later' => ['kind' => 'video', 'media_id' => $video(), 'publish_at' => now()->addDays(3)->toIso8601String()],
        ] as $name => $lesson) {
            $detail = $this->postJson("/api/v1/courses/{$this->course}/lessons", ['chapter_id' => $chapter, 'title' => $name, ...$lesson])->assertCreated()->json('data');
            $this->lessons[$name] = end($detail['chapters'][0]['lessons'])['id'];
        }
        $this->patchJson("/api/v1/courses/{$this->course}", ['status' => 'published'])->assertOk();

        $this->app['auth']->forgetGuards();
        $grade = DB::table('curriculum_nodes')->where('name', 'الصف التالت الثانوي')->value('id');
        $this->token = $this->postJson('/api/v1/public/sites/mr-mohamed/register', [
            'name' => 'يوسف أحمد', 'phone' => '01012345487', 'password' => 'secret12', 'password_confirmation' => 'secret12',
            'grade_id' => $grade, 'consent' => true, 'device_key' => 'device-key-0000000001', 'device_name' => 'Chrome',
        ])->assertCreated()->json('token');
        $this->learnerId = DB::table('learners')->value('id');
        $this->app['auth']->forgetGuards();
    }

    private function asStudent(): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->token);
    }

    private function asTeacher(): void
    {
        $this->app['auth']->forgetGuards();
        $this->withoutToken();
        Sanctum::actingAs($this->teacher);
    }

    /** @return array<string, array<string, mixed>> lessons by title */
    private function outline(): array
    {
        $course = $this->asStudent()->getJson("/api/v1/learner/courses/{$this->course}")->assertOk()->json('data');

        return collect($course['chapters'][0]['lessons'])->keyBy('title')->all();
    }

    private function enroll(): void
    {
        $this->asTeacher();
        $this->postJson('/api/v1/enrollments', ['learner_id' => $this->learnerId, 'course_id' => $this->course])->assertCreated();
    }

    public function test_before_enrolling_only_the_free_preview_opens(): void
    {
        $list = $this->asStudent()->getJson('/api/v1/learner/courses')->assertOk()->json('data');
        $this->assertSame([false, 5, 1], [$list[0]['enrolled'], $list[0]['lessons_count'], $list[0]['free_previews']]);

        $outline = $this->outline();
        $this->assertSame([null, 'not_enrolled', 'not_yet'], [$outline['intro']['locked'], $outline['one']['locked'], $outline['later']['locked']]);
        $this->asStudent()->postJson("/api/v1/learner/lessons/{$this->lessons['one']}/open")->assertForbidden()->assertJsonPath('code', 'not_enrolled');

        $open = $this->asStudent()->postJson("/api/v1/learner/lessons/{$this->lessons['intro']}/open")->assertOk()->json('data');
        $this->assertSame('video', $open['kind']);
        $this->assertNotEmpty($open['playback']);
        $this->assertSame('يوسف أحمد · 01012345487', $open['watermark']['text']);
        $this->assertSame(hash_hmac('sha256', "{$open['session_id']}|{$open['watermark']['text']}|{$open['watermark']['issued_at']}", config('app.key')), $open['watermark']['signature']);

        // A teacher's token can't use the student's routes.
        $this->asTeacher();
        $this->getJson('/api/v1/learner/courses')->assertForbidden();
    }

    public function test_an_enrolled_student_watches_and_a_view_counts_by_time_watched(): void
    {
        $this->enroll();
        $this->assertDatabaseHas('audit_log', ['action' => 'access.enrolled']);
        $outline = $this->outline();
        $this->assertSame([null, 2, 'previous_required'], [$outline['one']['locked'], $outline['one']['views_left'], $outline['two']['locked']]);

        $session = $this->asStudent()->postJson("/api/v1/learner/lessons/{$this->lessons['one']}/open")->assertOk()->json('data.session_id');

        // Paused: no time credited. Playing: the server's clock decides (60 s of a 600 s video, 10% = a view).
        $this->travel(30)->seconds();
        $this->asStudent()->postJson("/api/v1/learner/playback/{$session}/beat", ['position' => 30, 'playing' => false])->assertOk()->assertJsonPath('data.counted', false);
        $this->travel(40)->seconds();
        $this->asStudent()->postJson("/api/v1/learner/playback/{$session}/beat", ['position' => 70, 'playing' => true])->assertOk()->assertJsonPath('data.counted', false);
        $this->travel(25)->seconds();
        $beat = $this->asStudent()->postJson("/api/v1/learner/playback/{$session}/beat", ['position' => 95, 'playing' => true])->assertOk()->json('data');
        $this->assertSame([true, 1], [$beat['counted'], $beat['views_left']]);

        $outline = $this->outline();
        $this->assertTrue($outline['one']['completed']);
        $this->assertSame([95, null], [$outline['one']['last_position'], $outline['two']['locked']], 'the next lesson opens once this one is done');

        // A second device / tab: the first session stops.
        $second = $this->asStudent()->postJson("/api/v1/learner/lessons/{$this->lessons['two']}/open")->assertOk()->json('data.session_id');
        $this->asStudent()->postJson("/api/v1/learner/playback/{$session}/beat", ['position' => 100, 'playing' => true])
            ->assertStatus(409)->assertJsonPath('code', 'playback_ended')->assertJsonPath('reason', 'replaced');

        // No beat for a while: over.
        $this->travel(3)->minutes();
        $this->asStudent()->postJson("/api/v1/learner/playback/{$second}/beat", ['position' => 10, 'playing' => true])->assertStatus(409)->assertJsonPath('reason', 'expired');
        $this->assertDatabaseHas('playback_sessions', ['id' => $second, 'ended_reason' => 'expired']);
    }

    public function test_views_run_out_and_the_teacher_gives_more(): void
    {
        $this->enroll();
        for ($i = 0; $i < 2; $i++) {
            $session = $this->asStudent()->postJson("/api/v1/learner/lessons/{$this->lessons['one']}/open")->assertOk()->json('data.session_id');
            $this->travel(61)->seconds();
            $this->asStudent()->postJson("/api/v1/learner/playback/{$session}/beat", ['position' => 61, 'playing' => true])->assertOk()->assertJsonPath('data.counted', true);
            $this->asStudent()->postJson("/api/v1/learner/playback/{$session}/stop")->assertNoContent();
        }
        $this->assertSame('views_used_up', $this->outline()['one']['locked']);
        $this->asStudent()->postJson("/api/v1/learner/lessons/{$this->lessons['one']}/open")->assertForbidden()->assertJsonPath('code', 'views_used_up');

        $this->asTeacher();
        $this->postJson('/api/v1/access/extra-views', ['learner_id' => $this->learnerId, 'lesson_id' => $this->lessons['intro'], 'views' => 1])->assertUnprocessable()->assertJsonPath('code', 'views_unlimited');
        $this->postJson('/api/v1/access/extra-views', ['learner_id' => $this->learnerId, 'lesson_id' => $this->lessons['one'], 'views' => 1])->assertOk()->assertJsonPath('data.views_left', 1);
        $this->assertSame(1, $this->outline()['one']['views_left']);

        $this->asTeacher();
        $progress = $this->getJson("/api/v1/access/progress?learner_id={$this->learnerId}")->assertOk()->json('data.0.lessons');
        $this->assertSame([2, 1, 122], [$progress[1]['views'], $progress[1]['extra_views'], $progress[1]['watched_seconds']]);
    }

    public function test_a_pdf_opens_through_a_short_signed_link(): void
    {
        $this->enroll();
        $open = $this->asStudent()->postJson("/api/v1/learner/lessons/{$this->lessons['notes']}/open")->assertOk()->json('data');
        $this->assertSame('pdf', $open['kind']);
        $this->assertStringContainsString('signature=', $open['file_url']);
        $this->assertTrue($this->outline()['notes']['completed']);
    }

    public function test_revoking_and_expiry_close_the_course_and_codes_extend_it(): void
    {
        $this->enroll();
        $this->asTeacher();
        $enrollment = $this->getJson("/api/v1/enrollments?course_id={$this->course}")->assertOk()->json('data.0');
        $this->assertSame(['يوسف أحمد', 'فيزياء', 'manual', true], [$enrollment['learner_name'], $enrollment['course_title'], $enrollment['source'], $enrollment['active']]);

        // A code (later) extends the same enrollment from its end, not from today.
        app(CurrentTenant::class)->set($this->teacher->tenant_id);
        $extended = app(Enrollments::class)->grant($this->learnerId, $this->course, 'code', 10);
        $this->assertSame($enrollment['id'], $extended['id']);
        $this->assertSame(40, (int) round(now()->diffInDays($extended['ends_at'])));
        app(CurrentTenant::class)->set(null);

        $this->postJson("/api/v1/enrollments/{$enrollment['id']}/revoke", ['reason' => 'سرّب الحصة'])->assertOk()->assertJsonPath('data.active', false);
        $this->postJson("/api/v1/enrollments/{$enrollment['id']}/revoke", ['reason' => 'تاني'])->assertUnprocessable()->assertJsonPath('code', 'enrollment_revoked');
        $this->assertSame('not_enrolled', $this->outline()['one']['locked']);

        $this->enroll();
        $this->travel(31)->days();
        $outline = $this->outline();
        $this->assertSame(['not_enrolled', 'not_enrolled'], [$outline['one']['locked'], $outline['later']['locked']], 'the dated lesson opened meanwhile, the enrollment ended');
    }

    public function test_other_accounts_never_see_the_students_or_enrollments(): void
    {
        Sanctum::actingAs($this->registerShop());
        $this->postJson('/api/v1/enrollments', ['learner_id' => $this->learnerId, 'course_id' => $this->course])->assertNotFound()->assertJsonPath('code', 'learner_unknown');
        $this->getJson("/api/v1/enrollments?course_id={$this->course}")->assertOk()->assertJsonPath('data', []);
    }
}
