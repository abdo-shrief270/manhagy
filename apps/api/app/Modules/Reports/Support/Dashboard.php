<?php

declare(strict_types=1);

namespace App\Modules\Reports\Support;

use App\Modules\Learners\Contracts\LearnerDirectory;
use App\Support\Time\ShopDay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The teacher's home screen. Read-only SQL over the other modules' tables (never their models),
 * always filtered by tenant_id; days and months are Cairo's. Cached 30 s as an array.
 */
final class Dashboard
{
    public function __construct(private readonly LearnerDirectory $learners) {}

    /** @return array<string, mixed> */
    public function for(string $tenantId, bool $money, bool $transfers): array
    {
        $data = Cache::remember("dashboard:{$tenantId}", 30, fn () => $this->build($tenantId));
        if (! $money) {
            unset($data['money']);
        }
        if (! $transfers) {
            unset($data['waiting']['transfers']);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function build(string $t): array
    {
        $today = Carbon::now(ShopDay::TZ)->startOfDay()->utc();
        $month = Carbon::now(ShopDay::TZ)->startOfMonth()->utc();
        $week = now()->subDays(7);

        $codes = fn (Carbon $from) => DB::table('codes as c')->join('code_batches as b', 'b.id', '=', 'c.batch_id')
            ->where('c.tenant_id', $t)->where('c.status', 'used')->where('c.used_at', '>=', $from)
            ->selectRaw('count(*) as n, coalesce(sum(b.unit_price), 0) as value')->first();
        $transfers = fn (Carbon $from) => (int) DB::table('wallet_topups')->where('tenant_id', $t)->where('status', 'approved')->where('decided_at', '>=', $from)->sum('approved_amount');
        $money = [];
        foreach (['today' => $today, 'month' => $month] as $key => $from) {
            $c = $codes($from);
            $money[$key] = ['codes' => (int) $c->value, 'codes_count' => (int) $c->n, 'transfers' => $transfers($from), 'total' => (int) $c->value + $transfers($from)];
        }

        $profiles = DB::table('learner_profiles')->where('tenant_id', $t);
        $sessions = fn () => DB::table('playback_sessions')->where('tenant_id', $t);
        $attempts = DB::table('exam_attempts')->where('tenant_id', $t)->where('status', 'submitted')->where('submitted_at', '>=', $week);

        return [
            'money' => $money,
            'students' => [
                'total' => (clone $profiles)->count(),
                'new_today' => (clone $profiles)->where('created_at', '>=', $today)->count(),
                'new_month' => (clone $profiles)->where('created_at', '>=', $month)->count(),
                'active_week' => (clone $profiles)->where('last_seen_at', '>=', $week)->count(),
                'enrolled' => DB::table('enrollments')->where('tenant_id', $t)->whereNull('revoked_at')
                    ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->distinct()->count('learner_id'),
            ],
            'watching' => [
                'views_today' => $sessions()->where('counted', true)->where('started_at', '>=', $today)->count(),
                'views_month' => $sessions()->where('counted', true)->where('started_at', '>=', $month)->count(),
                'hours_month' => round((int) $sessions()->where('started_at', '>=', $month)->sum('watched_seconds') / 3600, 1),
                'watching_now' => $sessions()->whereNull('ended_at')->where('last_beat_at', '>=', now()->subSeconds(150))->count(),
                'top_lessons' => DB::table('playback_sessions as s')->join('course_lessons as l', 'l.id', '=', 's.lesson_id')
                    ->join('courses as c', 'c.id', '=', 'l.course_id')
                    ->where('s.tenant_id', $t)->where('s.counted', true)->where('s.started_at', '>=', $month)
                    ->groupBy('l.id', 'l.title', 'c.title')->orderByDesc(DB::raw('count(*)'))->limit(5)
                    ->get(['l.id', 'l.title', 'c.title as course', DB::raw('count(*) as views')])
                    ->map(fn ($r) => ['lesson_id' => $r->id, 'title' => $r->title, 'course' => $r->course, 'views' => (int) $r->views])->all(),
            ],
            'exams' => [
                'attempts_week' => (clone $attempts)->count(),
                'average_percent_week' => ($avg = (clone $attempts)->avg('percent')) === null ? null : (int) round((float) $avg),
                'in_progress' => DB::table('exam_attempts')->where('tenant_id', $t)->where('status', 'in_progress')->where('ends_at', '>', now())->count(),
            ],
            'waiting' => [
                'transfers' => DB::table('wallet_topups')->where('tenant_id', $t)->where('status', 'pending')->count(),
            ],
            'sharing' => $this->sharing($t),
        ];
    }

    /**
     * Accounts that look shared: today's playback came from 2+ networks or 2+ devices.
     *
     * @return list<array<string, mixed>>
     */
    private function sharing(string $t): array
    {
        $rows = DB::table('playback_sessions')->where('tenant_id', $t)->where('started_at', '>=', now()->subDay())
            ->groupBy('learner_id')->havingRaw('count(distinct ip) > 1 or count(distinct device_id) > 1')
            ->orderByDesc(DB::raw('count(distinct ip)'))->limit(10)
            ->get(['learner_id', DB::raw('count(distinct ip) as networks'), DB::raw('count(distinct device_id) as devices'), DB::raw('count(*) as sessions')]);

        return $rows->map(fn ($r) => [
            'learner_id' => $r->learner_id, 'name' => $this->learners->find($r->learner_id)['name'] ?? null,
            'networks' => (int) $r->networks, 'devices' => (int) $r->devices, 'sessions' => (int) $r->sessions,
        ])->all();
    }
}
