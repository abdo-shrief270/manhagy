<?php

namespace Tests\Feature\Certificates;

use App\Modules\Access\Contracts\LessonAccess;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesShops;
use Tests\Concerns\RunsAPlatform;
use Tests\TestCase;

/** A completion certificate: earned by finishing the course, checked by its code, revoked by the teacher. */
class CertificatesTest extends TestCase
{
    use CreatesShops, RefreshDatabase, RunsAPlatform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlatform(price: 0);
    }

    private function finishLesson(): void
    {
        app(CurrentTenant::class)->runAs($this->teacher->tenant_id, fn () => app(LessonAccess::class)->complete($this->learnerId, $this->lessonId));
    }

    public function test_a_student_earns_the_certificate_and_anyone_checks_it(): void
    {
        $this->asStudent()->postJson("/api/v1/learner/courses/{$this->courseId}/buy")->assertCreated();
        $this->asStudent()->postJson("/api/v1/learner/courses/{$this->courseId}/certificate")->assertUnprocessable()->assertJsonPath('code', 'certificate_not_offered');

        $this->asTeacher()->putJson("/api/v1/certificates/courses/{$this->courseId}", ['enabled' => true, 'title' => 'شهادة إتمام', 'signer_name' => 'أ. محمد'])->assertOk();
        $status = $this->asStudent()->getJson("/api/v1/learner/courses/{$this->courseId}/certificate")->assertOk()->json('data');
        $this->assertSame([0, 1, false], [$status['completed'], $status['total'], $status['eligible']]);
        $this->asStudent()->postJson("/api/v1/learner/courses/{$this->courseId}/certificate")->assertUnprocessable()->assertJsonPath('code', 'certificate_not_earned');

        $this->finishLesson();
        $issued = $this->asStudent()->postJson("/api/v1/learner/courses/{$this->courseId}/certificate")->assertCreated()->json('data.certificate');
        $this->assertSame('يوسف أحمد', $issued['learner_name']);
        $this->assertSame(10, strlen($issued['code']));
        $this->assertStringContainsString('/certificates/'.$issued['code'], $issued['url']);
        // Asking again gives the same one.
        $this->asStudent()->postJson("/api/v1/learner/courses/{$this->courseId}/certificate")->assertCreated()->assertJsonPath('data.certificate.code', $issued['code']);
        $this->assertCount(1, $this->asStudent()->getJson('/api/v1/learner/certificates')->json('data'));

        // Anyone checks it, with or without the dash, any case.
        $public = $this->withoutToken()->getJson('/api/v1/public/certificates/'.strtolower($issued['display_code']))->assertOk()->json('data');
        $this->assertSame('فيزياء', $public['course_title']);
        $this->assertSame('أ. محمد', $public['signer_name']);
        $this->assertFalse($public['revoked']);
        $this->getJson('/api/v1/public/certificates/AAAAAAAAAA')->assertNotFound();

        // The teacher revokes it: the page says so.
        $id = $this->asTeacher()->getJson('/api/v1/certificates')->assertOk()->json('data.0.id');
        $this->postJson("/api/v1/certificates/{$id}/revoke", ['reason' => 'غش في الامتحان'])->assertOk();
        $this->assertTrue($this->withoutToken()->getJson("/api/v1/public/certificates/{$issued['code']}")->json('data.revoked'));
        $this->assertDatabaseHas('audit_log', ['action' => 'certificates.revoked']);
    }

    public function test_a_minimum_exam_score_is_required_when_set(): void
    {
        $this->asTeacher()->putJson("/api/v1/certificates/courses/{$this->courseId}", ['enabled' => true, 'title' => 'شهادة', 'min_exam_percent' => 60])->assertOk();
        $this->asStudent()->postJson("/api/v1/learner/courses/{$this->courseId}/buy")->assertCreated();
        $this->finishLesson();
        // No exam lesson in this course: finishing it is enough.
        $this->asStudent()->postJson("/api/v1/learner/courses/{$this->courseId}/certificate")->assertCreated();
    }
}
