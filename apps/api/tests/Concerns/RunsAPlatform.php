<?php

namespace Tests\Concerns;

use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * A teacher with a platform (mr-mohamed), a published course with one video lesson, and a
 * registered student. Use with CreatesShops.
 */
trait RunsAPlatform
{
    protected User $teacher;

    protected string $courseId;

    protected string $lessonId;

    protected string $learnerId;

    protected string $studentToken;

    protected function setUpPlatform(int $price = 30000, ?int $accessDays = 30): void
    {
        $this->teacher = $this->actingAsOwnerOf();
        $this->putJson('/api/v1/site', ['slug' => 'mr-mohamed'])->assertOk();

        $subject = DB::table('curriculum_nodes')->where('kind', 'subject')->value('id');
        $course = $this->postJson('/api/v1/courses', ['curriculum_node_id' => $subject, 'title' => 'فيزياء', 'price' => $price, 'access_days' => $accessDays])->assertCreated()->json('data');
        $this->courseId = $course['id'];
        $video = $this->postJson('/api/v1/media/videos', ['title' => 'الحصة 1'])->json('data.id');
        $this->postJson("/api/v1/media/{$video}/check")->assertOk();
        $detail = $this->postJson("/api/v1/courses/{$this->courseId}/lessons", ['chapter_id' => $course['chapters'][0]['id'], 'kind' => 'video', 'title' => 'الحصة 1', 'media_id' => $video])->json('data');
        $this->lessonId = $detail['chapters'][0]['lessons'][0]['id'];
        $this->patchJson("/api/v1/courses/{$this->courseId}", ['status' => 'published'])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->studentToken = $this->registerStudent('01012345487');
        $this->learnerId = DB::table('learners')->where('phone', '+201012345487')->value('id');
    }

    protected function registerStudent(string $phone, string $name = 'يوسف أحمد'): string
    {
        $this->app['auth']->forgetGuards();
        $token = $this->withoutToken()->postJson('/api/v1/public/sites/mr-mohamed/register', [
            'name' => $name, 'phone' => $phone, 'password' => 'secret12', 'password_confirmation' => 'secret12',
            'grade_id' => DB::table('curriculum_nodes')->where('name', 'الصف التالت الثانوي')->value('id'),
            'consent' => true, 'device_key' => 'device-key-'.$phone, 'device_name' => 'Chrome',
        ])->assertCreated()->json('token');
        $this->app['auth']->forgetGuards();

        return $token;
    }

    protected function asStudent(?string $token = null): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->studentToken);
    }

    protected function asTeacher(?User $user = null): static
    {
        $this->app['auth']->forgetGuards();
        $this->withoutToken();
        Sanctum::actingAs($user ?? $this->teacher);

        return $this;
    }
}
