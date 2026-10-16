<?php

namespace Tests\Feature\Learners;

use App\Modules\Learners\Jobs\SendWhatsappReply;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesShops;
use Tests\Concerns\RunsAPlatform;
use Tests\TestCase;

/** A student proves their phone by sending a WhatsApp message to the platform; the same for a forgotten password. */
class PhoneVerificationTest extends TestCase
{
    use CreatesShops, RefreshDatabase, RunsAPlatform;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.verify.whatsapp' => '201555440882', 'services.verify.app_secret' => 'app-secret', 'services.verify.verify_token' => 'hello']);
        Bus::fake([SendWhatsappReply::class]);
    }

    /** What Meta posts when a message arrives. */
    private function inbound(string $from, string $text, ?string $secret = 'app-secret')
    {
        $body = json_encode(['object' => 'whatsapp_business_account', 'entry' => [['changes' => [['value' => ['messages' => [
            ['from' => $from, 'type' => 'text', 'text' => ['body' => $text]],
        ]]]]]]]);

        return $this->call('POST', '/api/v1/public/whatsapp/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, (string) $secret),
        ], $body);
    }

    public function test_the_student_confirms_by_messaging_the_platform(): void
    {
        $this->setUpPlatform();
        $check = DB::table('learner_phone_checks')->first();
        $this->assertSame('+201012345487', $check->phone);

        $me = $this->asStudent()->getJson('/api/v1/learner/me')->json('data');
        $this->assertFalse($me['phone_verified']);
        $this->asStudent()->postJson("/api/v1/learner/lessons/{$this->lessonId}/open")->assertForbidden()->assertJsonPath('code', 'phone_unverified');

        $start = $this->asStudent()->postJson('/api/v1/learner/phone/verify')->assertOk()->json('data');
        $this->assertStringStartsWith('https://wa.me/201555440882?text=', $start['link']);
        $this->assertStringContainsString($start['code'], $start['text']);

        // A message from another phone, a forged signature or a wrong code changes nothing.
        $this->inbound('201099999999', $start['text'])->assertOk();
        $this->inbound('201012345487', $start['text'], 'wrong')->assertForbidden();
        $this->inbound('201012345487', 'تأكيد رقمي: 000000')->assertOk();
        $this->assertFalse($this->asStudent()->getJson('/api/v1/learner/phone/verify')->json('data.verified'));

        // Arabic digits are fine.
        $arabic = strtr($start['text'], ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);
        $this->inbound('201012345487', $arabic)->assertOk();
        $this->assertTrue($this->asStudent()->getJson('/api/v1/learner/phone/verify')->json('data.verified'));
        Bus::assertDispatched(SendWhatsappReply::class, fn ($job) => $job->phone === '+201012345487');

        $this->asStudent()->postJson("/api/v1/learner/lessons/{$this->lessonId}/open")->assertForbidden()->assertJsonPath('code', 'not_enrolled');
        $this->asStudent()->postJson('/api/v1/learner/phone/verify')->assertStatus(409)->assertJsonPath('code', 'phone_verified');
    }

    public function test_a_forgotten_password(): void
    {
        $this->setUpPlatform();
        $this->app['auth']->forgetGuards();
        $reset = $this->withoutToken()->postJson('/api/v1/public/sites/mr-mohamed/password/forgot', ['phone' => '01012345487'])->assertOk()->json('data');
        $payload = ['phone' => '01012345487', 'code' => $reset['code'], 'password' => 'newpass1', 'password_confirmation' => 'newpass1'];
        $this->postJson('/api/v1/public/sites/mr-mohamed/password/reset', $payload)->assertStatus(409)->assertJsonPath('code', 'reset_not_confirmed');

        $this->inbound('201012345487', $reset['text'])->assertOk();
        $this->postJson('/api/v1/public/sites/mr-mohamed/password/reset', $payload)->assertOk();
        $this->postJson('/api/v1/public/sites/mr-mohamed/password/reset', $payload)->assertStatus(409);

        // Old devices are signed out; the new password works (and the phone counts as confirmed).
        $this->asStudent()->getJson('/api/v1/learner/me')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withoutToken()->postJson('/api/v1/public/sites/mr-mohamed/login', ['phone' => '01012345487', 'password' => 'newpass1', 'device_key' => 'device-key-01012345487', 'device_name' => 'Chrome'])->assertOk();
        $this->assertNotNull(DB::table('learners')->value('phone_verified_at'));
    }

    public function test_the_webhook_handshake(): void
    {
        $this->get('/api/v1/public/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=hello&hub.challenge=123')->assertOk()->assertSeeText('123');
        $this->get('/api/v1/public/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=nope&hub.challenge=123')->assertForbidden();
    }
}
