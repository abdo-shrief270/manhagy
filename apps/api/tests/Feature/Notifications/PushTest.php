<?php

namespace Tests\Feature\Notifications;

use App\Modules\Notifications\Contracts\Notifications;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Models\PushSubscription;
use App\Modules\Notifications\Support\PushSender;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

/** Web Push: devices, who gets a push, muted categories, quiet hours and gone devices. */
class PushTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    /** @var list<array{to: list<string>, payload: array<string, mixed>}> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        $sent = &$this->sent;
        $this->app->instance(PushSender::class, new class($sent) implements PushSender
        {
            public function __construct(private array &$sent) {}

            public function configured(): bool
            {
                return true;
            }

            public function send(array $subscriptions, array $payload): array
            {
                $this->sent[] = ['to' => array_column($subscriptions, 'endpoint'), 'payload' => $payload];

                return array_values(array_filter(array_column($subscriptions, 'endpoint'), fn ($e) => str_contains($e, 'gone')));
            }
        });
        $this->openShopWithStock();
    }

    private function subscribe(string $endpoint = 'https://push.example.com/owner')
    {
        return $this->postJson('/api/v1/push/subscriptions', ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'key', 'auth' => 'secret'], 'device' => 'Chrome']);
    }

    /** A renewal reminder (category «billing», owners only). */
    private function remind(bool $urgent = false): void
    {
        $this->inShop(fn () => app(Notifications::class)->notify('billing.reminder', 'الاشتراك بيخلص بعد 3 أيام', null, 'i-lucide-credit-card', '/settings/billing', 'owner', $urgent));
    }

    public function test_a_device_subscribes_and_gets_a_test_push(): void
    {
        $this->getJson('/api/v1/push/key')->assertOk()->assertJsonPath('data.configured', true);
        $this->subscribe()->assertCreated();
        $this->subscribe()->assertCreated(); // the same browser again: still one device
        $this->assertSame(1, PushSubscription::withoutTenancy()->count());

        $this->postJson('/api/v1/push/test')->assertOk()->assertJsonPath('data.devices', 1);
        $this->assertSame(['https://push.example.com/owner'], $this->sent[0]['to']);

        $this->deleteJson('/api/v1/push/subscriptions', ['endpoint' => 'https://push.example.com/owner'])->assertNoContent();
        $this->assertSame(0, PushSubscription::withoutTenancy()->count());
    }

    public function test_only_users_allowed_to_see_it_get_the_push(): void
    {
        $assistant = $this->staff('assistant');
        Sanctum::actingAs($assistant);
        $this->subscribe('https://push.example.com/assistant')->assertCreated();
        Sanctum::actingAs($this->owner);
        $this->subscribe()->assertCreated();

        $this->remind();
        $this->assertSame([['https://push.example.com/owner']], array_column($this->sent, 'to'));
        $this->assertSame('/settings/billing', $this->sent[0]['payload']['url']);
    }

    public function test_muted_categories_and_quiet_hours_stop_pushes_but_not_the_bell(): void
    {
        $this->subscribe()->assertCreated();
        $prefs = $this->getJson('/api/v1/notifications/preferences')->assertOk()->json('data');
        $this->assertContains('billing', array_column($prefs['categories'], 'key'));

        $this->putJson('/api/v1/notifications/preferences', ['muted' => ['billing'], 'quiet_from' => null, 'quiet_to' => null])->assertOk();
        $this->remind();
        $this->assertSame([], $this->sent);
        $this->assertSame(1, Notification::withoutTenancy()->count(), 'the bell still has it');

        // Quiet hours (Cairo time): nothing, unless it's urgent.
        $this->travelTo(CarbonImmutable::parse('2026-10-15 23:30', 'Africa/Cairo'));
        $this->putJson('/api/v1/notifications/preferences', ['muted' => [], 'quiet_from' => '22:00', 'quiet_to' => '08:00'])->assertOk();
        $this->remind();
        $this->assertSame([], $this->sent);
        $this->remind(urgent: true);
        $this->assertCount(1, $this->sent);

        $this->putJson('/api/v1/notifications/preferences', ['muted' => ['nope'], 'quiet_from' => null, 'quiet_to' => null])->assertUnprocessable();
        $this->putJson('/api/v1/notifications/preferences', ['muted' => [], 'quiet_from' => '23:00', 'quiet_to' => null])->assertUnprocessable();
    }

    public function test_gone_devices_are_dropped(): void
    {
        $this->subscribe('https://push.example.com/gone-device')->assertCreated();
        $this->subscribe()->assertCreated();

        $this->remind();
        $this->assertSame(['https://push.example.com/owner'], PushSubscription::withoutTenancy()->pluck('endpoint')->all());
    }
}
