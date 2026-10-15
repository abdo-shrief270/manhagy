<?php

namespace Tests\Feature\Tenancy;

use App\Modules\Identity\Enums\AccountKind;
use App\Modules\Identity\Models\Branch;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\MissingTenantException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesShops;
use Tests\Fixtures\Modules\Demo\DemoNote;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    public function test_an_account_never_sees_another_accounts_data(): void
    {
        $shopA = $this->registerShop(AccountKind::Teacher);
        $shopB = $this->registerShop(AccountKind::Teacher);

        app(CurrentTenant::class)->runAs($shopB->tenant_id, fn () => DemoNote::create(['name' => 'ملاحظة خاصة بحساب ب']));

        Sanctum::actingAs($shopA);
        $names = array_column($this->getJson('/api/v1/demo/notes')->assertOk()->json('data'), 'name');

        $this->assertCount(3, $names);
        $this->assertNotContains('ملاحظة خاصة بحساب ب', $names);

        $branchTenants = app(CurrentTenant::class)->runAs($shopA->tenant_id, fn () => Branch::query()->pluck('tenant_id')->unique()->all());
        $this->assertSame([$shopA->tenant_id], $branchTenants);
    }

    public function test_tenant_scoped_queries_return_nothing_without_a_tenant(): void
    {
        $this->registerShop(AccountKind::Teacher);

        $this->assertSame(0, DemoNote::query()->count());
        $this->assertGreaterThan(0, DemoNote::withoutTenancy()->count());
    }

    public function test_creating_a_tenant_scoped_model_without_a_tenant_fails(): void
    {
        $this->expectException(MissingTenantException::class);

        DemoNote::create(['name' => 'بدون حساب']);
    }
}
