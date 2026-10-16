<?php

namespace Tests\Feature\Identity;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Teacher / center: what an account is decides which optional modules it sees and starts on trial (test-only modules). */
class AccountKindsTest extends TestCase
{
    use RefreshDatabase;

    private function register(array $overrides = [])
    {
        return $this->postJson('/api/v1/auth/register', [
            'shop_name' => 'أ. محمد سامي',
            'owner_name' => 'أحمد',
            'phone' => '010'.random_int(10000000, 99999999),
            'password' => 'password123',
            'password_confirmation' => 'password123',
            ...$overrides,
        ]);
    }

    private function signIn(array $types): string
    {
        $token = $this->register(['shop_types' => $types])->assertCreated()->json('token');
        $this->withToken($token);

        return $token;
    }

    /** @return array<string, array<string, mixed>> key => module */
    private function modules(): array
    {
        return collect($this->getJson('/api/v1/modules')->assertOk()->json('data'))->keyBy('key')->all();
    }

    public function test_the_registration_form_lists_the_kinds_with_their_modules(): void
    {
        $types = collect($this->getJson('/api/v1/shop-types')->assertOk()->json('data'))->keyBy('value');

        $this->assertSame(['teacher', 'center', 'trainer'], $types->keys()->all(), 'the institution kinds are not open yet');
        $demo = fn (string $type) => collect($types[$type]['modules'])->firstWhere('key', 'demo');
        $this->assertTrue($demo('teacher')['trial']);
        $this->assertFalse($demo('center')['trial'], 'centers see it but don\'t start on it');
        $this->assertNull(collect($types['teacher']['modules'])->firstWhere('key', 'center_tools'));
    }

    public function test_an_account_of_both_kinds_sees_and_tries_the_modules_made_for_them(): void
    {
        $this->signIn(['teacher', 'center']);

        $me = $this->getJson('/api/v1/auth/me')->assertOk()->json('data.tenant');
        $this->assertSame([['teacher', 'center'], 'مدرس + سنتر'], [$me['shop_types'], $me['shop_type_label']]);

        $modules = $this->modules();
        $this->assertSame('trial', $modules['demo']['state']);
        $this->assertSame('trial', $modules['center_tools']['state']);
        $this->assertArrayHasKey('messaging', $modules, 'core modules always');
        $this->assertArrayHasKey('extra', $modules, 'modules for every kind');
    }

    public function test_a_teacher_does_not_see_center_modules_until_it_says_it_runs_a_center(): void
    {
        $this->signIn(['teacher']);
        $this->assertArrayNotHasKey('center_tools', $this->modules());

        $this->putJson('/api/v1/shop/types', ['shop_types' => ['teacher', 'center']])
            ->assertOk()->assertJsonPath('data.shop_types', ['teacher', 'center']);

        $tools = $this->modules()['center_tools'];
        $this->assertSame([false, true], [$tools['usable'], $tools['trial_available']], 'shown, to try when the owner wants');

        // Dropping the kind again keeps a module the account already uses.
        $this->postJson('/api/v1/modules/center_tools/trial')->assertOk();
        $this->putJson('/api/v1/shop/types', ['shop_types' => ['teacher']])->assertOk();
        $this->assertArrayHasKey('center_tools', $this->modules());
    }

    public function test_a_center_sees_the_teachers_module_without_starting_its_trial(): void
    {
        $this->signIn(['center']);
        $demo = $this->modules()['demo'];
        $this->assertSame([false, true], [$demo['usable'], $demo['trial_available']]);
    }

    public function test_kinds_are_validated_and_the_single_kind_field_still_works(): void
    {
        $this->register(['shop_types' => []])->assertUnprocessable()->assertJsonValidationErrors('shop_types');
        $this->register(['shop_types' => ['school']])->assertUnprocessable();
        $this->register(['shop_types' => ['teacher', 'teacher']])->assertUnprocessable();

        $this->withToken($this->register(['shop_type' => 'center'])->assertCreated()->json('token'));
        $this->assertSame(['center'], $this->getJson('/api/v1/auth/me')->json('data.tenant.shop_types'));

        $this->putJson('/api/v1/shop/types', ['shop_types' => []])->assertUnprocessable();
    }
}
