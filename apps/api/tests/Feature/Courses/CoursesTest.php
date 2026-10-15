<?php

namespace Tests\Feature\Courses;

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesShops;
use Tests\TestCase;

/** A teacher builds a course: on a curriculum subject, chapters, video / PDF / exam lessons, order, publishing. */
class CoursesTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teacher = $this->actingAsOwnerOf();
    }

    private function subject(): string
    {
        $grade = DB::table('curriculum_nodes')->where('name', 'الصف التالت الثانوي')->value('id');

        return DB::table('curriculum_nodes')->where('kind', 'subject')->where('name', 'like', '%فيزياء%')->value('id')
            ?? DB::table('curriculum_nodes')->where('kind', 'subject')->value('id') ?? $grade;
    }

    private function video(string $title = 'شرح'): string
    {
        $id = $this->postJson('/api/v1/media/videos', ['title' => $title])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/media/{$id}/check")->assertOk();

        return $id;
    }

    public function test_a_teacher_builds_and_publishes_a_course(): void
    {
        $grade = DB::table('curriculum_nodes')->where('name', 'الصف التالت الثانوي')->value('id');
        $this->postJson('/api/v1/courses', ['curriculum_node_id' => $grade, 'title' => 'فيزياء'])->assertUnprocessable()->assertJsonPath('code', 'curriculum_not_teachable');

        $course = $this->postJson('/api/v1/courses', ['curriculum_node_id' => $this->subject(), 'title' => 'فيزياء تالتة ثانوي — الترم الأول', 'price' => 30000, 'access_days' => 30])
            ->assertCreated()->json('data');
        $this->assertSame(['draft', 1], [$course['status'], count($course['chapters'])]);
        $this->assertNotEmpty($course['grade']);
        $chapter = $course['chapters'][0]['id'];

        $this->patchJson("/api/v1/courses/{$course['id']}", ['status' => 'published'])->assertUnprocessable()->assertJsonPath('code', 'course_empty');

        // A PDF id can't be a video lesson, and the reverse.
        $this->postJson("/api/v1/courses/{$course['id']}/lessons", ['chapter_id' => $chapter, 'kind' => 'pdf', 'title' => 'ملزمة', 'media_id' => $this->video()])
            ->assertUnprocessable()->assertJsonPath('code', 'lesson_media_invalid');
        $video = $this->video('الحصة 1');
        $detail = $this->postJson("/api/v1/courses/{$course['id']}/lessons", ['chapter_id' => $chapter, 'kind' => 'video', 'title' => 'الحصة 1', 'media_id' => $video, 'free_preview' => true])
            ->assertCreated()->json('data');
        $this->assertSame(600, $detail['chapters'][0]['lessons'][0]['duration_seconds']);

        $second = $this->postJson("/api/v1/courses/{$course['id']}/chapters", ['title' => 'الفصل التاني'])->assertCreated()->json('data.chapters.1.id');
        $detail = $this->postJson("/api/v1/courses/{$course['id']}/lessons", ['chapter_id' => $second, 'kind' => 'exam', 'title' => 'امتحان الفصل'])->assertCreated()->json('data');
        $this->assertSame(2, $detail['lessons_count']);
        $this->assertSame(1, $detail['free_previews']);

        // Reorder: the exam goes first in chapter one, the empty chapter can then go.
        $exam = $detail['chapters'][1]['lessons'][0]['id'];
        $lesson = $detail['chapters'][0]['lessons'][0]['id'];
        $this->putJson("/api/v1/courses/{$course['id']}/order", ['chapters' => [['id' => $chapter, 'lessons' => [$lesson]]]])->assertUnprocessable()->assertJsonPath('code', 'order_mismatch');
        $this->deleteJson("/api/v1/courses/{$course['id']}/chapters/{$second}")->assertUnprocessable()->assertJsonPath('code', 'chapter_not_empty');
        $detail = $this->putJson("/api/v1/courses/{$course['id']}/order", ['chapters' => [['id' => $chapter, 'lessons' => [$exam, $lesson]], ['id' => $second, 'lessons' => []]]])->assertOk()->json('data');
        $this->assertSame([$exam, $lesson], array_column($detail['chapters'][0]['lessons'], 'id'));
        $this->deleteJson("/api/v1/courses/{$course['id']}/chapters/{$second}")->assertOk();

        $this->patchJson("/api/v1/courses/{$course['id']}", ['status' => 'published'])->assertOk()->assertJsonPath('data.status', 'published');
        $this->assertDatabaseHas('audit_log', ['action' => 'courses.published']);
        $this->deleteJson("/api/v1/courses/{$course['id']}")->assertUnprocessable()->assertJsonPath('code', 'course_was_published');

        // The platform's public face lists it.
        $slug = $this->getJson('/api/v1/site')->json('data.slug');
        $public = $this->getJson("/api/v1/public/sites/{$slug}/courses")->assertOk()->json('data');
        $this->assertSame([['فيزياء تالتة ثانوي — الترم الأول', 30000, 2]], array_map(fn ($c) => [$c['title'], $c['price'], $c['lessons_count']], $public));
        $this->assertArrayNotHasKey('status', $public[0]);

        // Another account sees nothing of it.
        Sanctum::actingAs($this->registerShop());
        $this->getJson("/api/v1/courses/{$course['id']}")->assertNotFound();
        $this->assertSame([], $this->getJson('/api/v1/courses')->json('data'));
    }

    public function test_a_draft_that_was_never_published_can_be_deleted(): void
    {
        $id = $this->postJson('/api/v1/courses', ['curriculum_node_id' => $this->subject(), 'title' => 'تجربة'])->json('data.id');
        $this->deleteJson("/api/v1/courses/{$id}")->assertNoContent();
        $this->assertDatabaseCount('courses', 0);
    }
}
