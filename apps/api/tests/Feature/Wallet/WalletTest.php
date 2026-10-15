<?php

namespace Tests\Feature\Wallet;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesShops;
use Tests\Concerns\RunsAPlatform;
use Tests\TestCase;

/** The student's wallet: a transfer the teacher confirms, buying a course, adjustments. */
class WalletTest extends TestCase
{
    use CreatesShops, RefreshDatabase, RunsAPlatform;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->setUpPlatform();
    }

    private function sendTransfer(int $amount = 50000)
    {
        return $this->asStudent()->post('/api/v1/learner/wallet/topups', [
            'amount' => $amount, 'method' => 'instapay', 'reference' => 'yousef@instapay', 'proof' => UploadedFile::fake()->image('proof.jpg', 800, 1400),
        ], ['Accept' => 'application/json']);
    }

    public function test_a_transfer_is_confirmed_then_buys_a_course(): void
    {
        $this->sendTransfer()->assertUnprocessable()->assertJsonPath('code', 'transfers_closed');

        $this->asTeacher()->putJson('/api/v1/wallet/settings', ['transfers_enabled' => true])->assertUnprocessable()->assertJsonPath('code', 'transfer_details_missing');
        $this->asTeacher()->putJson('/api/v1/wallet/settings', ['transfers_enabled' => true, 'instapay' => 'mr.mohamed@instapay', 'min_topup' => 5000])->assertOk();

        $wallet = $this->asStudent()->getJson('/api/v1/learner/wallet')->assertOk()->json('data');
        $this->assertSame([0, 'mr.mohamed@instapay'], [$wallet['balance'], $wallet['transfer']['instapay']]);

        $this->sendTransfer(1000)->assertUnprocessable()->assertJsonValidationErrors('amount');
        $topup = $this->sendTransfer()->assertCreated()->json('data');
        $this->assertSame('pending', $topup['status']);
        $this->assertDatabaseHas('notifications', ['type' => 'wallet.topup_requested', 'permission' => 'wallet.confirm']);

        // Buying with nothing in the wallet.
        $this->asStudent()->postJson("/api/v1/learner/courses/{$this->courseId}/buy")->assertUnprocessable()->assertJsonPath('code', 'wallet_insufficient');

        $this->asTeacher();
        $list = $this->getJson('/api/v1/wallet/topups')->assertOk()->json();
        $this->assertSame([1, 'يوسف أحمد'], [$list['meta']['pending'], $list['data'][0]['learner_name']]);
        $this->get("/api/v1/wallet/topups/{$topup['id']}/proof")->assertOk()->assertHeader('Content-Type', 'image/webp');
        // Less arrived than claimed: the teacher confirms what came.
        $this->postJson("/api/v1/wallet/topups/{$topup['id']}/approve", ['amount' => 40000])->assertOk()->assertJsonPath('data.approved_amount', 40000);
        $this->postJson("/api/v1/wallet/topups/{$topup['id']}/approve")->assertUnprocessable()->assertJsonPath('code', 'topup_decided');

        $bought = $this->asStudent()->postJson("/api/v1/learner/courses/{$this->courseId}/buy")->assertCreated()->json('data');
        $this->assertSame([10000, 'wallet', true], [$bought['balance'], $bought['enrollment']['source'], $bought['enrollment']['active']]);
        $this->assertNull($this->asStudent()->getJson("/api/v1/learner/courses/{$this->courseId}")->json('data.chapters.0.lessons.0.locked'));

        $statement = $this->asTeacher()->getJson("/api/v1/wallet/statement?learner_id={$this->learnerId}")->assertOk()->json('data');
        $this->assertSame([10000, ['purchase', 'transfer']], [$statement['balance'], array_column($statement['transactions'], 'type')]);
        $this->assertSame([-30000, 40000], array_column($statement['transactions'], 'amount'));
    }

    public function test_a_rejected_transfer_adds_nothing_and_adjustments_are_audited(): void
    {
        $this->asTeacher()->putJson('/api/v1/wallet/settings', ['transfers_enabled' => true, 'wallet_number' => '01012345678'])->assertOk();
        $topup = $this->sendTransfer()->json('data');
        $this->asTeacher()->postJson("/api/v1/wallet/topups/{$topup['id']}/reject", ['reason' => 'الصورة مش واضحة'])->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->assertSame(0, $this->asStudent()->getJson('/api/v1/learner/wallet')->json('data.balance'));

        $this->asTeacher()->postJson('/api/v1/wallet/adjust', ['learner_id' => $this->learnerId, 'amount' => -100, 'note' => 'غلط'])->assertUnprocessable()->assertJsonPath('code', 'wallet_insufficient');
        $this->asTeacher()->postJson('/api/v1/wallet/adjust', ['learner_id' => $this->learnerId, 'amount' => 2500, 'note' => 'هدية للأوائل'])->assertCreated();
        $this->assertDatabaseHas('audit_log', ['action' => 'wallet.adjusted']);
        $this->assertSame(2500, $this->asStudent()->getJson('/api/v1/learner/wallet')->json('data.balance'));
    }

    public function test_a_free_course_just_opens(): void
    {
        $this->asTeacher()->patchJson("/api/v1/courses/{$this->courseId}", ['price' => 0, 'access_days' => null])->assertOk();
        $this->asStudent()->postJson("/api/v1/learner/courses/{$this->courseId}/buy")->assertCreated()->assertJsonPath('data.enrollment.source', 'free');
        $this->asStudent()->postJson("/api/v1/learner/courses/{$this->courseId}/buy")->assertUnprocessable()->assertJsonPath('code', 'already_enrolled');
    }
}
