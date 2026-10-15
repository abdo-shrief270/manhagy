<?php

namespace Tests\Feature\Learners;

use App\Modules\Identity\Models\User;
use App\Modules\Learners\Models\Learner;
use App\Support\Audit\AuditEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesShops;
use Tests\TestCase;

/** A teacher's platform: its address, students registering / signing in, the device limit, the teacher's list. */
class LearnersTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teacher = $this->actingAsOwnerOf();
        $this->putJson('/api/v1/site', ['slug' => 'Mr-Mohamed', 'title' => 'أ. محمد سامي — فيزياء', 'device_limit' => 2])->assertOk();
        $this->app['auth']->forgetGuards();
    }

    private function grade(): string
    {
        return DB::table('curriculum_nodes')->where('name', 'الصف التالت الثانوي')->value('id');
    }

    private function register(array $overrides = [], string $slug = 'mr-mohamed')
    {
        return $this->postJson("/api/v1/public/sites/{$slug}/register", [
            'name' => 'يوسف أحمد', 'phone' => '01012345487', 'password' => 'secret12', 'password_confirmation' => 'secret12',
            'grade_id' => $this->grade(), 'parent_phone' => '01112345678', 'consent' => true,
            'device_key' => 'device-key-0000000001', 'device_name' => 'Chrome · Android',
            ...$overrides,
        ]);
    }

    private function login(string $deviceKey, string $slug = 'mr-mohamed')
    {
        return $this->postJson("/api/v1/public/sites/{$slug}/login", ['phone' => '01012345487', 'password' => 'secret12', 'device_key' => $deviceKey, 'device_name' => 'iPhone']);
    }

    public function test_the_site_has_an_address_and_a_public_face(): void
    {
        Sanctum::actingAs($this->teacher);
        $site = $this->getJson('/api/v1/site')->assertOk()->json('data');
        $this->assertSame(['mr-mohamed', 'https://mr-mohamed.manhagy.online'], [$site['slug'], $site['url']]);
        $this->putJson('/api/v1/site', ['slug' => 'admin'])->assertUnprocessable()->assertJsonPath('code', 'site_slug_invalid');
        $this->putJson('/api/v1/site', ['slug' => 'مستر'])->assertUnprocessable();

        $other = $this->registerShop();
        Sanctum::actingAs($other);
        $this->assertStringStartsWith('t-', $this->getJson('/api/v1/site')->json('data.slug'), 'a default address until the owner picks one');
        $this->putJson('/api/v1/site', ['slug' => 'mr-mohamed'])->assertUnprocessable()->assertJsonValidationErrors('slug');

        $public = $this->getJson('/api/v1/public/sites/MR-MOHAMED')->assertOk()->json('data');
        $this->assertSame(['أ. محمد سامي — فيزياء', 'محل 1', true], [$public['title'], $public['teacher'], $public['registration_open']]);
        $this->getJson('/api/v1/public/sites/nobody-here')->assertNotFound();
    }

    public function test_a_student_registers_and_uses_their_session(): void
    {
        $this->register(['consent' => false])->assertUnprocessable()->assertJsonValidationErrors('consent');
        $token = $this->register()->assertCreated()->json('token');

        $me = $this->withToken($token)->getJson('/api/v1/learner/me')->assertOk()->json('data');
        $this->assertSame(['يوسف أحمد', '+201012345487', '+201112345678'], [$me['name'], $me['phone'], $me['parent_phone']]);
        $this->assertStringEndsWith('الصف التالت الثانوي', $me['grade']);
        $this->assertTrue($me['devices'][0]['current']);

        // Same phone again on the same platform: sign in instead.
        $this->register()->assertUnprocessable()->assertJsonPath('code', 'phone_taken');

        // A student's token is not an account user's.
        $this->withToken($token)->getJson('/api/v1/users')->assertForbidden();
        $this->withToken($token)->postJson('/api/v1/learner/logout')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/learner/me')->assertUnauthorized();
    }

    public function test_the_device_limit_and_an_assistant_freeing_a_device(): void
    {
        $this->register()->assertCreated();
        $this->login('device-key-0000000002')->assertOk();
        $this->login('device-key-0000000001')->assertOk(); // a known device signs in again
        $this->login('device-key-0000000003')->assertStatus(409)->assertJsonPath('code', 'device_limit')->assertJsonPath('limit', 2);

        Sanctum::actingAs($this->teacher);
        $student = $this->getJson('/api/v1/learners')->assertOk()->json('data.0');
        $this->assertSame(['يوسف أحمد', 2, false], [$student['name'], $student['devices'], $student['blocked']]);
        $detail = $this->getJson("/api/v1/learners/{$student['id']}")->assertOk()->json('data');
        $this->deleteJson("/api/v1/learners/{$student['id']}/devices/{$detail['devices'][0]['id']}")->assertOk()->assertJsonCount(1, 'data.devices');
        $this->assertTrue(AuditEntry::query()->where('action', 'learners.device_removed')->exists());

        $this->app['auth']->forgetGuards();
        $this->login('device-key-0000000003')->assertOk();
    }

    public function test_a_removed_device_and_a_blocked_student_lose_access(): void
    {
        $token = $this->register()->assertCreated()->json('token');

        Sanctum::actingAs($this->teacher);
        $student = $this->getJson('/api/v1/learners')->json('data.0');
        $this->postJson("/api/v1/learners/{$student['id']}/block", ['reason' => 'شارك الحساب'])->assertOk()->assertJsonPath('data.blocked', true);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/learner/me')->assertForbidden()->assertJsonPath('code', 'learner_blocked');
        $this->login('device-key-0000000001')->assertForbidden()->assertJsonPath('code', 'learner_blocked');

        Sanctum::actingAs($this->teacher);
        $this->postJson("/api/v1/learners/{$student['id']}/unblock")->assertOk();
        $device = $this->getJson("/api/v1/learners/{$student['id']}")->json('data.devices.0.id');
        $this->deleteJson("/api/v1/learners/{$student['id']}/devices/{$device}")->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/learner/me')->assertUnauthorized();
    }

    public function test_one_account_for_every_teacher_and_each_teacher_sees_only_theirs(): void
    {
        $this->register()->assertCreated();

        $other = $this->registerShop();
        Sanctum::actingAs($other);
        $this->putJson('/api/v1/site', ['slug' => 'miss-heba'])->assertOk();
        $this->assertSame([], $this->getJson('/api/v1/learners')->json('data'));

        // The same student joins the second platform with the same phone and password.
        $this->app['auth']->forgetGuards();
        $this->login('device-key-0000000001', 'miss-heba')->assertOk();
        $this->assertSame(1, Learner::query()->count());

        Sanctum::actingAs($other);
        $mine = $this->getJson('/api/v1/learners')->json('data');
        $this->assertSame(['يوسف أحمد'], array_column($mine, 'name'));
        $this->assertNull($mine[0]['parent_phone'], 'what the student told the first teacher stays there');

        // Closed registration: a new student can't join, nor can an existing one start here.
        $this->putJson('/api/v1/site', ['registration_open' => false])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->register(['phone' => '01099998888'], 'miss-heba')->assertForbidden()->assertJsonPath('code', 'registration_closed');
    }

    public function test_wrong_passwords_lock_the_sign_in_for_a_while(): void
    {
        $this->register()->assertCreated();
        foreach (range(1, 5) as $_) {
            $this->postJson('/api/v1/public/sites/mr-mohamed/login', ['phone' => '01012345487', 'password' => 'wrong', 'device_key' => 'device-key-0000000001', 'device_name' => 'x'])
                ->assertUnprocessable();
        }
        $this->login('device-key-0000000001')->assertStatus(429)->assertJsonPath('code', 'login_locked');
    }

    public function test_who_may_see_and_manage_students(): void
    {
        $this->register()->assertCreated();
        Sanctum::actingAs($this->teacher);
        $student = $this->getJson('/api/v1/learners?q='.urlencode('يوسف'))->assertOk()->json('data.0');
        $this->assertSame('يوسف أحمد', $student['name']);
        $this->assertSame(1, count($this->getJson('/api/v1/learners?q=12345487')->json('data')), 'by phone digits');
        $this->assertSame([], $this->getJson('/api/v1/learners?q='.urlencode('مريم'))->json('data'));

        $assistant = User::factory()->create(['tenant_id' => $this->teacher->tenant_id]);
        Sanctum::actingAs($assistant);
        $this->getJson('/api/v1/learners')->assertForbidden();
    }
}
