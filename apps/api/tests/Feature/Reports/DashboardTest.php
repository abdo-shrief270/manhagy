<?php

namespace Tests\Feature\Reports;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesShops;
use Tests\Concerns\RunsAPlatform;
use Tests\TestCase;

/** The teacher's home screen: money in, students, watching, exams, accounts that look shared. */
class DashboardTest extends TestCase
{
    use CreatesShops, RefreshDatabase, RunsAPlatform;

    public function test_the_dashboard_adds_up_the_day(): void
    {
        $this->setUpPlatform();
        $batch = $this->asTeacher()->postJson('/api/v1/codes/batches', ['kind' => 'course', 'course_id' => $this->courseId, 'count' => 3, 'unit_price' => 25000])->json('data');
        $this->asStudent()->postJson('/api/v1/learner/codes/redeem', ['code' => $batch['codes'][0]['code']])->assertOk();

        // Two sessions from two networks: one counted view, and a shared-looking account.
        $first = $this->asStudent()->withServerVariables(['REMOTE_ADDR' => '41.1.1.1'])->postJson("/api/v1/learner/lessons/{$this->lessonId}/open")->json('data.session_id');
        $this->travel(90)->seconds();
        $this->asStudent()->postJson("/api/v1/learner/playback/{$first}/beat", ['position' => 90, 'playing' => true])->assertOk();
        DB::table('playback_sessions')->where('id', $first)->update(['counted' => true]);
        $this->asStudent()->withServerVariables(['REMOTE_ADDR' => '156.2.2.2'])->postJson("/api/v1/learner/lessons/{$this->lessonId}/open")->assertOk();

        Cache::flush();
        $data = $this->asTeacher()->getJson('/api/v1/dashboard')->assertOk()->json('data');
        $this->assertSame(['codes' => 25000, 'codes_count' => 1, 'transfers' => 0, 'total' => 25000], $data['money']['today']);
        $this->assertSame([1, 1, 1], [$data['students']['total'], $data['students']['new_today'], $data['students']['enrolled']]);
        $this->assertSame([1, 1], [$data['watching']['views_today'], $data['watching']['watching_now']]);
        $this->assertSame('الحصة 1', $data['watching']['top_lessons'][0]['title']);
        $this->assertSame(['يوسف أحمد', 2], [$data['sharing'][0]['name'], $data['sharing'][0]['networks']]);

        // An assistant sees the dashboard but not the money.
        $assistant = User::query()->create(['tenant_id' => $this->teacher->tenant_id, 'name' => 'مساعد', 'phone' => '+201000000009', 'password' => 'x', 'role_id' => Role::query()->where('tenant_id', $this->teacher->tenant_id)->where('key', 'assistant')->value('id')]);
        Cache::flush();
        $data = $this->asTeacher($assistant)->getJson('/api/v1/dashboard')->assertOk()->json('data');
        $this->assertArrayNotHasKey('money', $data);
        $this->assertArrayHasKey('students', $data);
    }
}
