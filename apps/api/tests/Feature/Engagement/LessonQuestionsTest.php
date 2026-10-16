<?php

namespace Tests\Feature\Engagement;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesShops;
use Tests\Concerns\RunsAPlatform;
use Tests\TestCase;

/** Students ask under a lesson; the teacher answers, privately or for everyone. */
class LessonQuestionsTest extends TestCase
{
    use CreatesShops, RefreshDatabase, RunsAPlatform;

    public function test_a_question_and_its_answer(): void
    {
        $this->setUpPlatform();
        $url = "/api/v1/learner/lessons/{$this->lessonId}/questions";
        $this->asStudent()->postJson($url, ['body' => 'ليه الشغل كمية قياسية؟'])->assertForbidden()->assertJsonPath('code', 'not_enrolled');

        $this->asTeacher()->postJson('/api/v1/enrollments', ['learner_id' => $this->learnerId, 'course_id' => $this->courseId])->assertCreated();
        $asked = $this->asStudent()->postJson($url, ['body' => 'ليه الشغل كمية قياسية؟', 'position' => 312])->assertCreated()->json('data');
        $this->assertDatabaseHas('notifications', ['type' => 'engagement.question', 'permission' => 'engagement.answer']);

        $other = $this->registerStudent('01112345678', 'منة الله');
        $this->asTeacher()->postJson('/api/v1/enrollments', ['learner_id' => DB::table('learners')->where('phone', '+201112345678')->value('id'), 'course_id' => $this->courseId])->assertCreated();
        $this->assertSame([], $this->asStudent($other)->getJson($url)->json('data'), 'an open question is the asker\'s only');

        $inbox = $this->asTeacher()->getJson('/api/v1/engagement/questions')->assertOk()->json();
        $this->assertSame([1, 'يوسف أحمد', 'الحصة 1', 312], [$inbox['meta']['open'], $inbox['data'][0]['learner_name'], $inbox['data'][0]['lesson_title'], $inbox['data'][0]['position']]);
        $this->postJson("/api/v1/engagement/questions/{$asked['id']}/answer", ['answer' => 'لأن ليه مقدار بس من غير اتجاه.', 'public' => true])->assertOk()->assertJsonPath('data.status', 'answered');

        $seen = $this->asStudent($other)->getJson($url)->json('data.0');
        $this->assertSame(['لأن ليه مقدار بس من غير اتجاه.', false, 'يوسف'], [$seen['answer'], $seen['mine'], $seen['asked_by']]);
        $this->assertTrue($this->asStudent()->getJson($url)->json('data.0.mine'));

        $this->asTeacher()->postJson("/api/v1/engagement/questions/{$asked['id']}/hide")->assertOk();
        $this->assertSame([], $this->asStudent($other)->getJson($url)->json('data'));
    }
}
