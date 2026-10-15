<?php

namespace Tests\Feature\Modules;

use App\Modules\Billing\Models\PlatformAdmin;
use App\Modules\Identity\Enums\AccountKind;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesShops;
use Tests\TestCase;

/** The platform admin shapes modules and feature switches for every account, and one account's modules. */
class PlatformModulesTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    private PlatformAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = PlatformAdmin::create(['name' => 'الإدارة', 'email' => 'admin@manhagy.test', 'password' => 'secret-password']);
    }

    private function asAdmin(): static
    {
        Sanctum::actingAs($this->admin, ['admin']);

        return $this;
    }

    private function asOwner(User $owner): static
    {
        Sanctum::actingAs($owner);

        return $this;
    }

    /** @return array<string, array<string, mixed>> */
    private function me(User $owner): array
    {
        $me = $this->asOwner($owner)->getJson('/api/v1/auth/me')->assertOk();

        return ['modules' => collect($me->json('data.modules'))->keyBy('key')->all(), 'features' => $me->json('data.features')];
    }

    public function test_the_catalog_and_the_shop_side_follow_the_admins_status_and_trial(): void
    {
        $catalog = $this->asAdmin()->getJson('/api/v1/admin/modules')->assertOk();
        $used = collect($catalog->json('data.modules'))->firstWhere('key', 'demo');
        $this->assertSame(['live', true, true, 14], [$used['status'], $used['trial_allowed'], $used['auto_trial'], $used['trial_days']]);
        $this->assertArrayHasKey('teacher', $catalog->json('data.shop_types'));

        // No trials for used devices, reports_plus not started on trial at registration, 30-day trials for services.
        $this->asAdmin()->patchJson('/api/v1/admin/modules/demo', ['trial_allowed' => false])->assertOk();
        $this->asAdmin()->patchJson('/api/v1/admin/modules/reports_plus', ['auto_trial' => false, 'name' => 'تقارير وتحليلات'])->assertOk();
        $this->asAdmin()->patchJson('/api/v1/admin/modules/extra', ['trial_days' => 30])->assertOk();
        $this->asAdmin()->patchJson('/api/v1/admin/modules/messaging', ['status' => 'hidden'])->assertUnprocessable()->assertJsonPath('code', 'module_not_optional');

        $account = $this->registerShop(AccountKind::Teacher);
        $me = $this->me($account);
        $this->assertFalse($me['modules']['demo']['usable']);
        $this->assertFalse($me['modules']['reports_plus']['usable']);
        $this->asOwner($account)->postJson('/api/v1/modules/demo/trial')->assertStatus(409)->assertJsonPath('code', 'module_trial_closed');
        $modules = collect($this->asOwner($account)->getJson('/api/v1/modules')->json('data'))->keyBy('key');
        $this->assertSame('تقارير وتحليلات', $modules['reports_plus']['name']);
        $this->assertFalse($modules['demo']['trial_available']);
        $this->asOwner($account)->postJson('/api/v1/modules/extra/trial')->assertOk();
        $this->assertSame(30, (int) round(now()->diffInDays($this->asOwner($account)->getJson('/api/v1/modules')->collect('data')->firstWhere('key', 'extra')['trial_ends_at'])));

        // Back to what the module declares.
        $this->asAdmin()->patchJson('/api/v1/admin/modules/demo', ['trial_allowed' => null])->assertOk();
        $this->asOwner($account)->postJson('/api/v1/modules/demo/trial')->assertOk();
    }

    public function test_free_hidden_and_coming_soon_modules(): void
    {
        $shop = $this->registerShop(AccountKind::Teacher);

        // Free for every account: usable with no subscription, the owner may still hide it.
        $this->asAdmin()->patchJson('/api/v1/admin/modules/reports_plus', ['status' => 'free'])->assertOk();
        $this->assertTrue($this->me($shop)['modules']['reports_plus']['usable']);
        $this->asOwner($shop)->getJson('/api/v1/reports-plus/ping')->assertOk();
        $this->asOwner($shop)->postJson('/api/v1/modules/reports_plus/disable')->assertOk();
        $this->assertFalse($this->me($shop)['modules']['reports_plus']['usable']);
        $this->asOwner($shop)->postJson('/api/v1/modules/reports_plus/enable')->assertOk();
        $this->assertTrue($this->me($shop)['modules']['reports_plus']['usable']);
        $this->assertTrue(collect($this->getJson('/api/v1/public/plans')->json('data.modules'))->firstWhere('key', 'reports_plus')['free'] ?? true);

        // Coming soon: listed as «قريباً», off. Hidden: not listed at all.
        $this->asAdmin()->patchJson('/api/v1/admin/modules/reports_plus', ['status' => 'coming_soon'])->assertOk();
        $this->assertFalse($this->me($shop)['modules']['reports_plus']['usable']);
        $this->assertFalse($this->asOwner($shop)->getJson('/api/v1/modules')->collect('data')->firstWhere('key', 'reports_plus')['available']);
        $this->asAdmin()->patchJson('/api/v1/admin/modules/reports_plus', ['status' => 'hidden'])->assertOk();
        $this->assertNull($this->asOwner($shop)->getJson('/api/v1/modules')->collect('data')->firstWhere('key', 'reports_plus'));
        $this->assertNull(collect($this->getJson('/api/v1/public/plans')->json('data.modules'))->firstWhere('key', 'reports_plus'));
        $this->asOwner($shop)->getJson('/api/v1/reports-plus/ping')->assertForbidden();
    }

    public function test_feature_switches_forced_or_with_a_new_default(): void
    {
        $shop = $this->registerShop();
        $this->asOwner($shop)->putJson('/api/v1/features/demo.public', ['enabled' => false])->assertOk();
        $this->assertFalse($this->me($shop)['features']['demo.public']);

        // On for every account: the owner's choice no longer counts and can't be changed.
        $this->asAdmin()->patchJson('/api/v1/admin/features/demo.public', ['mode' => 'on'])->assertOk();
        $this->assertTrue($this->me($shop)['features']['demo.public']);
        $this->asOwner($shop)->putJson('/api/v1/features/demo.public', ['enabled' => false])->assertStatus(409)->assertJsonPath('code', 'feature_locked');
        $labels = collect($this->asOwner($shop)->getJson('/api/v1/features')->json('data'))->firstWhere('module', 'demo')['features'];
        $this->assertTrue(collect($labels)->firstWhere('key', 'demo.public')['locked']);

        // Off for every account: gone from the page and refused.
        $this->asAdmin()->patchJson('/api/v1/admin/features/demo.third', ['mode' => 'off'])->assertOk();
        $this->assertFalse($this->me($shop)['features']['demo.third']);
        $catalog = collect(collect($this->asOwner($shop)->getJson('/api/v1/features')->json('data'))->firstWhere('module', 'demo')['features']);
        $this->assertNull($catalog->firstWhere('key', 'demo.third'));
        $this->asOwner($shop)->putJson('/api/v1/features/demo.third', ['enabled' => true])->assertNotFound();

        // A new default: shops that never chose follow it.
        $this->asAdmin()->patchJson('/api/v1/admin/features/demo.fourth', ['default' => false])->assertOk();
        $this->assertFalse($this->me($shop)['features']['demo.fourth']);
        $feature = collect(collect($this->asAdmin()->getJson('/api/v1/admin/modules')->json('data.modules'))->firstWhere('key', 'demo')['features'])->keyBy('key');
        $this->assertSame(['on', 'off', 'shop', false, true], [$feature['demo.public']['mode'], $feature['demo.third']['mode'], $feature['demo.fourth']['mode'], $feature['demo.fourth']['default'], $feature['demo.fourth']['declared_default']]);

        // Back to the shop's own choice.
        $this->asAdmin()->patchJson('/api/v1/admin/features/demo.public', ['mode' => 'shop'])->assertOk();
        $this->assertFalse($this->me($shop)['features']['demo.public']);
    }

    public function test_one_accounts_modules(): void
    {
        $shop = $this->registerShop(AccountKind::Center); // reports_plus is not on trial for centers
        $url = "/api/v1/admin/shops/{$shop->tenant_id}/modules";
        $this->assertFalse(collect($this->asAdmin()->getJson($url)->assertOk()->json('data'))->firstWhere('key', 'reports_plus')['usable']);

        $this->asAdmin()->postJson("{$url}/reports_plus", ['action' => 'open'])->assertOk();
        $this->assertTrue($this->me($shop)['modules']['reports_plus']['usable']);
        $this->asAdmin()->postJson("{$url}/reports_plus", ['action' => 'close'])->assertOk();
        $this->assertFalse($this->me($shop)['modules']['reports_plus']['usable']);

        // A fresh trial even after the shop used its own.
        $this->asOwner($shop)->postJson('/api/v1/modules/extra/trial')->assertOk();
        $this->asAdmin()->postJson("{$url}/extra", ['action' => 'close'])->assertOk();
        $this->assertFalse($this->me($shop)['modules']['extra']['usable']);
        $this->asOwner($shop)->postJson('/api/v1/modules/extra/trial')->assertUnprocessable()->assertJsonPath('code', 'module_trial_used');
        $this->asAdmin()->postJson("{$url}/extra", ['action' => 'trial'])->assertOk();
        $this->assertTrue($this->me($shop)['modules']['extra']['usable']);

        $this->asAdmin()->postJson("{$url}/messaging", ['action' => 'open'])->assertUnprocessable()->assertJsonPath('code', 'module_not_optional');
    }
}
