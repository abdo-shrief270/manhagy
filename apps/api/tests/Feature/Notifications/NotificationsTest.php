<?php

namespace Tests\Feature\Notifications;

use App\Modules\Identity\Enums\AccountKind;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Contracts\Notifications;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesShops;
use Tests\TestCase;

/** The bell: one row per account, read marks per user, filtered by permission. */
class NotificationsTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    private User $owner;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->registerShop(AccountKind::Teacher);
        $this->other = $this->registerShop(AccountKind::Teacher);
    }

    private function as(User $user): static
    {
        Sanctum::actingAs($user);

        return $this;
    }

    private function notify(User $owner, string $title, ?string $permission = null): void
    {
        $this->travel(1)->seconds();
        app(CurrentTenant::class)->runAs($owner->tenant_id, fn () => app(Notifications::class)->notify('demo.note', $title, 'التفاصيل', 'i-lucide-bell', '/demo', $permission));
    }

    private function bell(User $user): array
    {
        return $this->as($user)->getJson('/api/v1/notifications')->assertOk()->json();
    }

    private function unread(User $user): int
    {
        return $this->as($user)->getJson('/api/v1/notifications/unread')->assertOk()->json('data.count');
    }

    private function staff(User $owner, array $permissions): User
    {
        $this->as($owner);
        $roleId = $this->postJson('/api/v1/roles', ['name' => 'دور '.implode(' ', $permissions), 'permissions' => $permissions])->assertCreated()->json('data.id');
        $branchId = app(CurrentTenant::class)->runAs($owner->tenant_id, fn () => Branch::query()->value('id'));
        $id = $this->postJson('/api/v1/users', [
            'name' => 'مساعد', 'phone' => '011'.random_int(10000000, 99999999), 'password' => 'password', 'role_id' => $roleId, 'branch_ids' => [$branchId],
        ])->assertCreated()->json('data.id');

        return User::query()->findOrFail($id);
    }

    public function test_a_notification_belongs_to_its_account_only(): void
    {
        $this->notify($this->owner, 'ملاحظة جديدة');

        [$note] = $this->bell($this->owner)['data'];
        $this->assertSame(['ملاحظة جديدة', 'التفاصيل', '/demo', false], [$note['title'], $note['body'], $note['to'], $note['read']]);
        $this->assertSame([], $this->bell($this->other)['data']);
        $this->as($this->other)->postJson("/api/v1/notifications/{$note['id']}/read")->assertNotFound();
    }

    public function test_users_see_only_what_their_permissions_allow(): void
    {
        $this->notify($this->owner, 'للي يشوف التجربة', 'demo.view');
        $this->notify($this->owner, 'للكل');

        $reader = $this->staff($this->owner, ['demo.view']);
        $sender = $this->staff($this->owner, ['messages.send']);

        $this->assertSame(['للكل', 'للي يشوف التجربة'], array_column($this->bell($reader)['data'], 'title'));
        $this->assertSame(['للكل'], array_column($this->bell($sender)['data'], 'title'));
        $this->assertSame(2, $this->unread($this->owner), 'the owner sees both');

        // Can't mark what one can't see.
        $hidden = collect($this->bell($this->owner)['data'])->firstWhere('title', 'للي يشوف التجربة');
        $this->as($sender)->postJson("/api/v1/notifications/{$hidden['id']}/read")->assertNotFound();
        $this->assertSame(2, $this->unread($this->owner));
    }

    public function test_read_state_is_per_user_and_unread_come_first(): void
    {
        $this->notify($this->owner, 'الأقدم');
        $this->notify($this->owner, 'الأحدث');
        $assistant = $this->staff($this->owner, ['demo.view']);

        $bell = $this->bell($this->owner);
        $this->assertSame([2, 2], [$bell['meta']['total'], $bell['meta']['unread']]);
        [$newest, $older] = $bell['data'];
        $this->assertSame('الأحدث', $newest['title'], 'newest first');

        $this->as($this->owner)->postJson("/api/v1/notifications/{$newest['id']}/read")->assertOk()->assertJsonPath('data.count', 1);
        $bell = $this->bell($this->owner);
        $this->assertSame([[$older['id'], false], [$newest['id'], true]], array_map(fn ($n) => [$n['id'], $n['read']], $bell['data']), 'unread first');
        $this->assertSame(2, $this->unread($assistant), 'someone else\'s reading changes nothing for you');

        $this->as($assistant)->postJson('/api/v1/notifications/read-all')->assertOk();
        $this->assertSame([0, 1], [$this->unread($assistant), $this->unread($this->owner)]);
    }
}
