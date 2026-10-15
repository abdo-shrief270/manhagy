<?php

namespace Tests\Feature\Modules;

use App\Support\Audit\AuditEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

/** The owner's small switches inside the modules (the «المميزات» page), on the test-only demo module. */
class FeatureSwitchesTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->openShopWithStock(); // a teacher: the demo module is on trial
    }

    private function switch(string $key, bool $on): void
    {
        $this->putJson("/api/v1/features/{$key}", ['enabled' => $on])->assertOk();
    }

    public function test_the_owner_sees_the_switches_of_the_modules_in_use_with_their_defaults(): void
    {
        $groups = collect($this->getJson('/api/v1/features')->assertOk()->json('data'))->keyBy('module');
        $demo = collect($groups['demo']['features'])->keyBy('key');
        $this->assertTrue($demo['demo.public']['enabled']);
        $this->assertFalse($demo['demo.strict']['enabled']);

        $this->switch('demo.strict', true);
        $this->assertTrue($this->getJson('/api/v1/auth/me')->json('data.features')['demo.strict']);
        $this->assertTrue(AuditEntry::query()->where('action', 'features.enabled')->exists());

        $this->putJson('/api/v1/features/nope.nope', ['enabled' => true])->assertNotFound();
        Sanctum::actingAs($this->staff('manager'));
        $this->getJson('/api/v1/features')->assertForbidden();
        $this->putJson('/api/v1/features/demo.public', ['enabled' => false])->assertForbidden();
    }

    public function test_a_closed_switch_closes_its_routes_for_everyone(): void
    {
        $this->getJson('/api/v1/demo/public')->assertOk();
        $this->switch('demo.public', false);
        $this->getJson('/api/v1/demo/public')->assertForbidden()->assertJsonPath('code', 'feature_disabled');
        $this->getJson('/api/v1/demo/notes')->assertOk();

        $this->deleteJson('/api/v1/features/demo.public')->assertOk();
        $this->getJson('/api/v1/demo/public')->assertOk();
    }
}
