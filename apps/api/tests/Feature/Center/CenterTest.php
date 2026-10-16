<?php

namespace Tests\Feature\Center;

use App\Modules\Center\Support\Cards;
use App\Modules\Identity\Enums\AccountKind;
use App\Modules\Identity\Models\User;
use App\Support\Time\ShopDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesShops;
use Tests\TestCase;

/** A center: groups and their sessions, the door (QR cards, offline batches, money), drawers, teachers' shares. */
class CenterTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    private User $owner;

    /** @var array<string, string> learner ids by name */
    private array $students = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-14 12:00', ShopDay::TZ)); // sessions «in 10 minutes» stay on the same Cairo day
        $this->owner = $this->actingAsOwnerOf(AccountKind::Center);
        $this->putJson('/api/v1/site', ['slug' => 'el-nour'])->assertOk();
        $this->app['auth']->forgetGuards();
        foreach (['يوسف أحمد' => '01012345487', 'منة الله' => '01112345678', 'عمر طارق' => '01212345670'] as $name => $phone) {
            $this->postJson('/api/v1/public/sites/el-nour/register', [
                'name' => $name, 'phone' => $phone, 'password' => 'secret12', 'password_confirmation' => 'secret12',
                'parent_phone' => '0155'.substr($phone, 4), 'consent' => true, 'device_key' => "device-key-{$phone}", 'device_name' => 'Chrome',
            ])->assertCreated();
            $this->app['auth']->forgetGuards();
            $this->students[$name] = DB::table('learners')->where('phone', '+2'.$phone)->value('id');
        }
        Sanctum::actingAs($this->owner);
    }

    private function group(array $overrides = []): array
    {
        $now = Carbon::now(ShopDay::TZ);

        return $this->postJson('/api/v1/center/groups', [
            'name' => 'فيزياء — السبت والتلات', 'room' => 'قاعة 2', 'session_fee' => 6000, 'monthly_fee' => 20000,
            'schedule' => [['day' => $now->dayOfWeek, 'time' => $now->copy()->addMinutes(10)->format('H:i'), 'minutes' => 90]],
            ...$overrides,
        ])->assertCreated()->json('data');
    }

    public function test_only_accounts_with_the_center_module(): void
    {
        Sanctum::actingAs($this->actingAsOwnerOf(AccountKind::Teacher));
        $this->getJson('/api/v1/center/groups')->assertForbidden()->assertJsonPath('code', 'module_not_enabled');
    }

    public function test_a_group_its_week_and_the_door(): void
    {
        $teacher = $this->postJson('/api/v1/center/teachers', ['name' => 'أ. محمد سامي', 'subject' => 'فيزياء', 'share_kind' => 'percent', 'share_value' => 70])->assertCreated()->json('data.id');
        $group = $this->group(['teacher_id' => $teacher, 'capacity' => 2]);
        $this->assertSame(['أ. محمد سامي', 'قاعة 2'], [$group['teacher'], $group['room']]);
        $this->assertNotEmpty($group['sessions'], 'the coming week is scheduled at once');

        $this->postJson("/api/v1/center/groups/{$group['id']}/members", ['learner_id' => $this->students['يوسف أحمد']])->assertCreated();
        $this->postJson("/api/v1/center/groups/{$group['id']}/members", ['learner_id' => $this->students['منة الله'], 'plan' => 'session'])->assertCreated();
        $this->postJson("/api/v1/center/groups/{$group['id']}/members", ['learner_id' => $this->students['عمر طارق']])->assertUnprocessable()->assertJsonPath('code', 'group_full');
        $this->postJson("/api/v1/center/groups/{$group['id']}/members", ['learner_id' => $this->students['يوسف أحمد']])->assertUnprocessable()->assertJsonPath('code', 'already_member');

        $cards = collect($this->getJson("/api/v1/center/cards?group_id={$group['id']}")->assertOk()->json('data'))->keyBy('name');
        $session = $this->postJson("/api/v1/center/groups/{$group['id']}/open")->assertOk()->json('data');
        $this->assertSame($group['sessions'][0]['id'], $session['id'], 'the scheduled session starting in 10 minutes');
        $this->assertCount(1, $this->getJson('/api/v1/center/sessions/today')->json('data'));

        // A batch from the door (one of them sent twice after the internet came back), a forged card, a name picked by hand.
        $yousef = ['id' => (string) Str::uuid(), 'session_id' => $session['id'], 'token' => $cards['يوسف أحمد']['token'], 'pay' => ['id' => (string) Str::uuid(), 'kind' => 'month', 'amount' => 20000]];
        $forged = ['id' => (string) Str::uuid(), 'session_id' => $session['id'], 'token' => str_replace('-', '', $this->students['عمر طارق']).'.AAAAAAAAAAAAAAAA'];
        $mennah = ['id' => (string) Str::uuid(), 'session_id' => $session['id'], 'learner_id' => $this->students['منة الله']];
        $results = $this->postJson('/api/v1/center/scans', ['scans' => [$yousef, $forged, $mennah]])->assertOk()->json('data');
        $this->assertSame(['ok', 'unknown_card', 'ok'], array_column($results, 'status'));
        $this->assertSame([0, 6000], [$results[0]['owes'], $results[2]['owes']], 'a paid month covers the session (on any plan); the other still owes the session');
        $again = $this->postJson('/api/v1/center/scans', ['scans' => [$yousef]])->assertOk()->json('data.0');
        $this->assertSame('ok', $again['status']);
        $this->assertSame(1, DB::table('center_payments')->count(), 'resending a batch adds nothing');
        $this->postJson('/api/v1/center/scans', ['scans' => [[...$mennah, 'id' => (string) Str::uuid()]]])->assertOk()->assertJsonPath('data.0.status', 'duplicate');

        // A card from another center doesn't open this door.
        $otherCard = Cards::token((string) Str::uuid(), $this->students['عمر طارق']);
        $this->postJson('/api/v1/center/scans', ['scans' => [['id' => (string) Str::uuid(), 'session_id' => $session['id'], 'token' => $otherCard]]])->assertOk()->assertJsonPath('data.0.status', 'unknown_card');

        $roster = collect($this->getJson("/api/v1/center/sessions/{$session['id']}")->assertOk()->json('data.students'))->keyBy('name');
        $this->assertSame([true, true], [$roster['يوسف أحمد']['present'], $roster['منة الله']['present']]);
        $this->assertSame([0, 6000], [$roster['يوسف أحمد']['owes'], $roster['منة الله']['owes']]);

        // Paying later at the door for the session.
        $this->postJson('/api/v1/center/scans', ['scans' => [[...$mennah, 'pay' => ['id' => (string) Str::uuid(), 'kind' => 'session', 'amount' => 6000]]]])->assertOk()->assertJsonPath('data.0.owes', 0);

        // The drawer, handed over 10 ج short.
        $this->assertSame(26000, $this->getJson('/api/v1/center/cash')->assertOk()->json('data.in_hand'));
        $drawers = $this->getJson('/api/v1/center/cash/all')->assertOk()->json('data.drawers');
        $handover = $this->postJson('/api/v1/center/handovers', ['user_id' => $drawers[0]['user_id'], 'counted' => 25000, 'note' => 'ناقص عشرة'])->assertCreated()->json('data');
        $this->assertSame([26000, -1000], [$handover['expected'], $handover['difference']]);
        $this->assertSame(0, $this->getJson('/api/v1/center/cash')->json('data.in_hand'));
        $this->assertDatabaseHas('audit_log', ['action' => 'center.handover']);

        // The teacher's 70% of what was collected this month, then settled.
        $statement = collect($this->getJson('/api/v1/center/teachers')->assertOk()->json('data'))->firstWhere('id', $teacher)['statement'];
        $this->assertSame([2, 26000, 18200, 0], [$statement['attendances'], $statement['collected'], $statement['due'], $statement['paid']]);
        $this->postJson("/api/v1/center/teachers/{$teacher}/settlements", ['from' => $statement['from'], 'to' => $statement['to'], 'paid' => 18200])
            ->assertCreated()->assertJsonPath('data.statement.remaining', 0);
    }

    public function test_the_student_has_the_card_on_their_phone_and_the_schedule_runs_daily(): void
    {
        $group = $this->group();
        $this->postJson("/api/v1/center/groups/{$group['id']}/members", ['learner_id' => $this->students['يوسف أحمد']])->assertCreated();

        $this->app['auth']->forgetGuards();
        $token = $this->postJson('/api/v1/public/sites/el-nour/login', ['phone' => '01012345487', 'password' => 'secret12', 'device_key' => 'device-key-01012345487', 'device_name' => 'Chrome'])->json('token');
        $card = $this->withToken($token)->getJson('/api/v1/learner/center/card')->assertOk()->json('data');
        $this->assertSame([1, 'فيزياء — السبت والتلات'], [count($card['groups']), $card['groups'][0]['name']]);
        $this->assertSame($this->students['يوسف أحمد'], Cards::read($this->owner->tenant_id, $card['token']));

        $this->travel(8)->days();
        $this->artisan('center:schedule')->assertSuccessful();
        $this->assertGreaterThan(count($group['sessions']), DB::table('center_sessions')->count());
    }
}
