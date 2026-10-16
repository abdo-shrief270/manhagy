<?php

namespace Tests\Feature\Reports;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesShops;
use Tests\Concerns\RunsAPlatform;
use Tests\TestCase;

/** The parent's weekly report: the WhatsApp variables for the teacher, a signed page for the parent. */
class ParentReportTest extends TestCase
{
    use CreatesShops, RefreshDatabase, RunsAPlatform;

    public function test_the_parent_gets_a_report_and_a_signed_page(): void
    {
        $this->setUpPlatform();
        DB::table('learner_profiles')->update(['parent_phone' => '+201112345678']);
        $this->asTeacher()->postJson('/api/v1/enrollments', ['learner_id' => $this->learnerId, 'course_id' => $this->courseId])->assertCreated();

        $session = $this->asStudent()->postJson("/api/v1/learner/lessons/{$this->lessonId}/open")->json('data.session_id');
        $this->travel(80)->seconds();
        $this->asStudent()->postJson("/api/v1/learner/playback/{$session}/beat", ['position' => 80, 'playing' => true])->assertOk();

        $list = $this->asTeacher()->getJson('/api/v1/parent-reports?days=7')->assertOk()->json('data');
        $this->assertCount(1, $list);
        $message = $list[0]['message'];
        $this->assertSame(['يوسف أحمد', '+201112345678', 'فيزياء', '1 درس (1 دقيقة)'], [$message['student'], $list[0]['parent_phone'], $message['subject'], $message['watched']]);
        $this->assertSame('الحصة 1', $message['missing'], 'watched 80 s of 600: not completed yet');
        $this->assertStringStartsWith('https://mr-mohamed.manhagy.online/p/', $message['link']);

        // The parent's page: the link's path is the student site's; the API checks the same signature.
        $query = parse_url($message['link'], PHP_URL_QUERY);
        $path = str_replace('/p/', '/api/v1/public/parent-report/', parse_url($message['link'], PHP_URL_PATH));
        $this->app['auth']->forgetGuards();
        $page = $this->withoutToken()->getJson("{$path}?{$query}")->assertOk()->json('data');
        $this->assertSame(['يوسف أحمد', 'الحصة 1', 1], [$page['student'], $page['watched'][0]['title'], $page['watched_minutes']]);
        $this->assertArrayNotHasKey('message', $page);
        $this->getJson("{$path}?".str_replace('days=7', 'days=30', $query))->assertForbidden();
        $this->travel(15)->days();
        $this->getJson("{$path}?{$query}")->assertForbidden();
    }
}
