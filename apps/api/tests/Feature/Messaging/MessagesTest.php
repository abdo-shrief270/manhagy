<?php

namespace Tests\Feature\Messaging;

use App\Modules\Identity\Models\User;
use App\Modules\Messaging\Models\MessageLog;
use App\Support\Audit\AuditEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

class MessagesTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->openShopWithStock();
    }

    private function template(string $key): array
    {
        return collect($this->getJson('/api/v1/messages/templates')->assertOk()->json('data'))->firstWhere('key', $key);
    }

    public function test_the_account_rewords_a_template_and_can_go_back_to_the_built_in_one(): void
    {
        $report = $this->template('parent_report');
        $this->assertFalse($report['customized']);
        $this->assertStringContainsString('{student}', $report['body']);
        $this->assertContains('exam', array_column($report['variables'], 'name'));

        $this->putJson('/api/v1/messages/templates/parent_report', ['body' => "تقرير {student} 📊\n{link}"])->assertOk();
        $report = $this->template('parent_report');
        $this->assertSame([true, "تقرير {student} 📊\n{link}"], [$report['customized'], $report['body']]);
        $this->assertTrue(AuditEntry::query()->where('action', 'messages.template_updated')->exists());

        $this->deleteJson('/api/v1/messages/templates/parent_report')->assertOk();
        $this->assertSame($report['default_body'], $this->template('parent_report')['body']);

        $this->putJson('/api/v1/messages/templates/nope_nope', ['body' => 'x'])->assertNotFound();
        $this->putJson('/api/v1/messages/templates/parent_report', ['body' => ''])->assertUnprocessable();
    }

    public function test_every_send_is_logged_for_its_subject_and_the_log_is_append_only(): void
    {
        $student = (string) str()->uuid();
        $this->postJson('/api/v1/messages/log', ['template' => 'student_welcome', 'subject_type' => 'learner', 'subject_id' => $student, 'phone' => '+201234567890'])->assertCreated();
        $this->postJson('/api/v1/messages/log', ['template' => 'parent_report', 'subject_type' => 'learner', 'subject_id' => $student, 'phone' => '+201234567891'])->assertCreated();

        $history = $this->getJson('/api/v1/messages/log?'.http_build_query(['subject_type' => 'learner', 'subject_id' => $student]))->assertOk()->json('data');
        $this->assertSame(['تقرير الأسبوع لولي الأمر', 'ترحيب بطالب جديد'], array_column($history, 'label'));

        $this->expectException(LogicException::class);
        $this->inShop(fn () => MessageLog::query()->first()->delete());
    }

    public function test_who_may_do_what(): void
    {
        $this->postJson('/api/v1/messages/log', ['template' => 'nope'])->assertUnprocessable();

        // An assistant sends messages and reads the wording, but doesn't edit it.
        Sanctum::actingAs($assistant = $this->staff('assistant'));
        $this->getJson('/api/v1/messages/templates')->assertOk();
        $this->postJson('/api/v1/messages/log', ['template' => 'renewal_reminder'])->assertCreated();
        $this->putJson('/api/v1/messages/templates/renewal_reminder', ['body' => 'x'])->assertForbidden();
        $this->assertInstanceOf(User::class, $assistant);
    }
}
