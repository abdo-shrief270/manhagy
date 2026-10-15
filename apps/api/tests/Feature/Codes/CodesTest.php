<?php

namespace Tests\Feature\Codes;

use App\Modules\Codes\Support\CodeFormat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesShops;
use Tests\Concerns\RunsAPlatform;
use Tests\TestCase;

/** Code batches: made once, used once, a check character, guessing limited, distributors' accounts. */
class CodesTest extends TestCase
{
    use CreatesShops, RefreshDatabase, RunsAPlatform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlatform();
    }

    /** @return array<string, mixed> */
    private function batch(array $data): array
    {
        return $this->asTeacher()->postJson('/api/v1/codes/batches', $data)->assertCreated()->json('data');
    }

    private function redeem(string $code, ?string $token = null)
    {
        return $this->asStudent($token)->postJson('/api/v1/learner/codes/redeem', ['code' => $code]);
    }

    public function test_the_code_format_catches_typos(): void
    {
        $code = CodeFormat::generate();
        $this->assertTrue(CodeFormat::valid($code));
        $this->assertSame(12, strlen($code));
        $this->assertDoesNotMatchRegularExpression('/[01OIL]/', $code);
        $typo = substr($code, 0, 5).($code[5] === 'A' ? 'B' : 'A').substr($code, 6);
        $this->assertFalse(CodeFormat::valid($typo));
        $swapped = $code[1].$code[0].substr($code, 2);
        $this->assertTrue($code[0] === $code[1] || ! CodeFormat::valid($swapped), 'neighbours swapped are caught');
        $this->assertSame($code, CodeFormat::normalize(strtolower(CodeFormat::pretty($code))));
    }

    public function test_a_course_code_opens_the_course_once(): void
    {
        $batch = $this->batch(['kind' => 'course', 'course_id' => $this->courseId, 'count' => 5, 'days' => 7, 'label' => 'مكتبة النور']);
        $this->assertSame(['CB-00001', 5, 30000, ['unused' => 5, 'used' => 0, 'cancelled' => 0]], [$batch['number'], count($batch['codes']), $batch['unit_price'], $batch['stats']]);
        $this->assertSame(5, count(array_unique(array_column($batch['codes'], 'code'))));
        // The codes themselves are never stored nor shown again.
        $this->assertArrayNotHasKey('codes', $this->getJson("/api/v1/codes/batches/{$batch['id']}")->json('data'));
        $plain = str_replace('-', '', $batch['codes'][0]['code']);
        $this->assertSame(0, DB::table('codes')->where('hash', $plain)->count());

        $done = $this->redeem(strtolower($batch['codes'][0]['code']))->assertOk()->json('data');
        $this->assertSame(['course', 'فيزياء', 'code'], [$done['kind'], $done['course']['title'], $done['enrollment']['source']]);
        $this->assertSame(7, (int) round(now()->diffInDays($done['enrollment']['ends_at'])));
        $this->redeem($batch['codes'][0]['code'])->assertUnprocessable()->assertJsonPath('code', 'code_used')->assertJsonPath('message', 'انت استخدمت الكود ده قبل كده.');

        $other = $this->registerStudent('01112345678', 'منة');
        $this->redeem($batch['codes'][0]['code'], $other)->assertUnprocessable()->assertJsonPath('message', 'الكود ده اتستخدم قبل كده.');

        $detail = $this->asTeacher()->getJson("/api/v1/codes/batches/{$batch['id']}")->assertOk()->json('data');
        $this->assertSame([1, 'يوسف أحمد'], [$detail['used'][0]['serial'], $detail['used'][0]['learner_name']]);
        $lookup = $this->getJson('/api/v1/codes/lookup?code='.urlencode($batch['codes'][0]['code']))->assertOk()->json('data');
        $this->assertSame(['CB-00001', 1, 'used', 'يوسف أحمد'], [$lookup['batch'], $lookup['serial'], $lookup['status'], $lookup['learner_name']]);

        // Another platform's student can't use it (the code is keyed by the account).
        Sanctum::actingAs($this->registerShop());
        $this->getJson('/api/v1/codes/lookup?code='.urlencode($batch['codes'][1]['code']))->assertOk()->assertJsonPath('data.found', false);
    }

    public function test_a_balance_code_fills_the_wallet(): void
    {
        $batch = $this->batch(['kind' => 'balance', 'value' => 10000, 'count' => 2, 'unit_price' => 9000]);
        $this->redeem($batch['codes'][0]['code'])->assertOk()->assertJsonPath('data.balance', 10000);
        $this->redeem($batch['codes'][1]['code'])->assertOk()->assertJsonPath('data.balance', 20000);
        $tx = $this->asStudent()->getJson('/api/v1/learner/wallet')->json('data.transactions');
        $this->assertSame(['code', 'code'], array_column($tx, 'type'));
    }

    public function test_cancelled_expired_and_guessing(): void
    {
        $batch = $this->batch(['kind' => 'course', 'course_id' => $this->courseId, 'count' => 4, 'expires_at' => now()->addDays(10)->toIso8601String()]);
        $this->asTeacher()->postJson("/api/v1/codes/batches/{$batch['id']}/cancel", ['reason' => 'الكروت ضاعت', 'serials' => [2]])->assertOk()->assertJsonPath('data.just_cancelled', 1);
        $this->redeem($batch['codes'][1]['code'])->assertUnprocessable()->assertJsonPath('code', 'code_cancelled');

        $this->travel(11)->days();
        $this->redeem($batch['codes'][2]['code'])->assertUnprocessable()->assertJsonPath('code', 'code_expired');
        $this->travelBack();

        $this->asTeacher()->postJson("/api/v1/codes/batches/{$batch['id']}/cancel", ['reason' => 'اتسرقت'])->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertDatabaseHas('audit_log', ['action' => 'codes.cancelled']);
        $this->redeem($batch['codes'][3]['code'])->assertUnprocessable()->assertJsonPath('code', 'code_cancelled');

        // Guessing: typos and unknown codes count as wrong tries too, then the student waits.
        $guesser = $this->registerStudent('01112345678', 'منة');
        $this->redeem('AAAA-AAAA-AAAA', $guesser)->assertUnprocessable()->assertJsonPath('code', 'code_mistyped');
        $unknown = CodeFormat::generate();
        for ($i = 0; $i < 4; $i++) {
            $this->redeem($unknown, $guesser)->assertNotFound()->assertJsonPath('code', 'code_invalid');
        }
        $this->redeem($unknown, $guesser)->assertStatus(429)->assertJsonPath('code', 'code_attempts');
        $this->travel(16)->minutes();
        $this->redeem($unknown, $guesser)->assertNotFound();
    }

    public function test_a_distributor_account(): void
    {
        $this->asTeacher();
        $this->postJson('/api/v1/codes/batches', ['kind' => 'course', 'course_id' => $this->courseId, 'count' => 2, 'distributor_id' => (string) str()->uuid()])
            ->assertUnprocessable()->assertJsonPath('code', 'distributor_unknown');
        $library = $this->postJson('/api/v1/codes/distributors', ['name' => 'مكتبة النور', 'phone' => '01012345678'])->assertCreated()->json('data.id');
        $batch = $this->batch(['kind' => 'course', 'course_id' => $this->courseId, 'count' => 10, 'distributor_id' => $library, 'unit_price' => 25000]);
        foreach ([0, 1, 2] as $i) {
            $this->redeem($batch['codes'][$i]['code'], $i === 0 ? null : $this->registerStudent('0101234500'.$i, "طالب {$i}"))->assertOk();
        }

        $this->asTeacher();
        $payment = $this->postJson("/api/v1/codes/distributors/{$library}/payments", ['amount' => 50000, 'note' => 'كاش'])->assertCreated()->json('data.id');
        $stats = $this->getJson('/api/v1/codes/distributors')->assertOk()->json('data.0.stats');
        $this->assertSame(['given' => 10, 'used' => 3, 'unused' => 7, 'cancelled' => 0, 'sold' => 75000, 'collected' => 50000, 'due' => 25000], $stats);

        $this->postJson("/api/v1/codes/distributors/{$library}/payments/{$payment}/reverse", ['note' => 'اتكتب غلط'])->assertCreated()->assertJsonPath('data.amount', -50000);
        $this->postJson("/api/v1/codes/distributors/{$library}/payments/{$payment}/reverse", ['note' => 'تاني'])->assertUnprocessable()->assertJsonPath('code', 'payment_reversed');
        $statement = $this->getJson("/api/v1/codes/distributors/{$library}")->assertOk()->json('data');
        $this->assertSame([75000, 2, 1], [$statement['stats']['due'], count($statement['payments']), count($statement['batches'])]);
    }
}
