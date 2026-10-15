<?php

namespace Tests\Concerns;

use App\Modules\Identity\Enums\AccountKind;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Support\Events\EventRelay;
use App\Support\Tenancy\CurrentTenant;
use Laravel\Sanctum\Sanctum;

/** A registered account with its owner signed in (the shop-selling helpers went with the shop modules). */
trait SellsInAShop
{
    use CreatesShops;

    private User $owner;

    private string $branchId;

    protected function openShopWithStock(): void
    {
        $this->owner = $this->registerShop(AccountKind::Teacher);
        app(EventRelay::class)->publishPending();
        $this->branchId = $this->inShop(fn () => Branch::query()->value('id'));
        Sanctum::actingAs($this->owner);
    }

    private function inShop(callable $callback): mixed
    {
        return app(CurrentTenant::class)->runAs($this->owner->tenant_id, $callback);
    }

    private function staff(string $role): User
    {
        $id = $this->postJson('/api/v1/users', [
            'name' => $role,
            'phone' => '011'.random_int(10000000, 99999999),
            'password' => 'password',
            'role_id' => $this->inShop(fn () => Role::query()->where('key', $role)->value('id')),
            'branch_ids' => [$this->branchId],
        ])->assertCreated()->json('data.id');

        return User::query()->findOrFail($id);
    }
}
