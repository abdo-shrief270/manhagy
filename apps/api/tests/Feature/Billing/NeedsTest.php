<?php

namespace Tests\Feature\Billing;

use App\Modules\Billing\Support\Subscriptions;
use App\Modules\Identity\Models\User;
use App\Modules\ModuleManager\Models\TenantModule;
use App\Support\Events\EventRelay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** «بتعمل إيه في المحل؟»: the needs ticked at sign-up start their modules' trials and pick the suggested plan. */
class NeedsTest extends TestCase
{
    use RefreshDatabase;

    private function recommend(array $needs): ?array
    {
        return $this->getJson('/api/v1/public/plans/recommend?'.http_build_query(['needs' => $needs]))->assertOk()->json('data');
    }

    public function test_the_cheapest_plan_and_extras_that_cover_the_needs(): void
    {
        $keys = array_column($this->getJson('/api/v1/public/plans')->assertOk()->json('data.needs'), 'key');
        $this->assertContains('demo', $keys);

        $basic = $this->recommend([]);
        $this->assertSame(['basic', [], 34900, 600000, 150000], [$basic['plan'], $basic['modules'], $basic['monthly'], $basic['lifetime'], $basic['maintenance']]);

        // Repairs alone: the repair plan beats accessories + the repairs module (349 + 199).
        $this->assertSame(['plus', 49900], [$this->recommend(['demo'])['plan'], $this->recommend(['demo'])['monthly']]);

        $r = $this->recommend(['demo', 'reports_plus']);
        $this->assertSame(['plus', ['reports_plus'], 49900 + 7900], [$r['plan'], array_column($r['modules'], 'key'), $r['monthly']]);

        // Three extras on repair would cost more than pro.
        $this->assertSame(['pro', [], 64900], [$this->recommend(['demo', 'reports_plus', 'center_tools'])['plan'], array_column($this->recommend(['demo', 'reports_plus', 'center_tools'])['modules'], 'key'), $this->recommend(['demo', 'reports_plus', 'center_tools'])['monthly']]);

        // Branches: accessories + multi branch (349 + 299) is cheaper than business; lifetime adds the module's.
        $b = $this->recommend(['branches']);
        $this->assertSame(['basic', ['branches'], 64800, 600000 + 510000, 150000 + 120000], [$b['plan'], array_column($b['modules'], 'key'), $b['monthly'], $b['lifetime'], $b['maintenance']]);

        $this->getJson('/api/v1/public/plans/recommend?needs[]=nonsense')->assertUnprocessable();
    }

    public function test_sign_up_with_needs_starts_their_trials_and_the_billing_page_suggests(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'shop_name' => 'محل الأمانة', 'shop_types' => ['teacher'], 'owner_name' => 'سامي', 'phone' => '01099990000',
            'password' => 'password123', 'password_confirmation' => 'password123', 'needs' => ['demo', 'web_page'],
        ])->assertCreated();
        app(EventRelay::class)->publishPending();
        $owner = User::query()->where('phone', '+201099990000')->firstOrFail();

        // Exactly what it said it does (not the accessories shop's usual trials).
        $trials = TenantModule::withoutTenancy()->where('tenant_id', $owner->tenant_id)->pluck('module_key')->sort()->values()->all();
        $this->assertEqualsCanonicalizing(['web_page', 'demo'], $trials);
        $this->assertSame(['demo', 'web_page'], app(Subscriptions::class)->for($owner->tenant_id)->needs);

        Sanctum::actingAs($owner);
        $billing = $this->getJson('/api/v1/billing')->assertOk()->json('data');
        $this->assertSame(['demo', 'web_page'], $billing['needs']);
        $this->assertSame(['plus', ['web_page']], [$billing['recommended']['plan'], array_column($billing['recommended']['modules'], 'key')]);

        $this->postJson('/api/v1/auth/register', [
            'shop_name' => 'x', 'shop_types' => ['teacher'], 'owner_name' => 'x', 'phone' => '01099990001',
            'password' => 'password123', 'password_confirmation' => 'password123', 'needs' => ['flying'],
        ])->assertUnprocessable()->assertJsonValidationErrors('needs.0');
    }
}
