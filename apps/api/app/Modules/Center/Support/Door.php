<?php

declare(strict_types=1);

namespace App\Modules\Center\Support;

use App\Modules\Center\Models\Attendance;
use App\Modules\Center\Models\Group;
use App\Modules\Center\Models\Member;
use App\Modules\Center\Models\Payment;
use App\Modules\Center\Models\Session;
use App\Modules\Learners\Contracts\LearnerDirectory;
use App\Support\Time\ShopDay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * At the door: a scan (or a name picked from the list) marks the student present, and money may be
 * taken with it. Every item carries the device's own ids, so a batch sent again after the internet
 * came back changes nothing.
 */
final class Door
{
    /** Late = scanned more than this after the start. */
    public const LATE_MINUTES = 15;

    public function __construct(private readonly LearnerDirectory $learners) {}

    /**
     * @param  array{id: string, session_id: string, learner_id: string, scanned_at?: string|null, pay?: array{id: string, kind: string, amount: int}|null}  $item
     * @return array<string, mixed>
     */
    public function scan(array $item, string $tenantId, ?string $userId, ?string $byName): array
    {
        $session = Session::query()->find($item['session_id']);
        if ($session === null) {
            return ['id' => $item['id'], 'status' => 'unknown_session'];
        }
        $group = Group::query()->findOrFail($session->group_id);
        $learner = $this->learners->find($item['learner_id']);
        if ($learner === null) {
            return ['id' => $item['id'], 'status' => 'unknown_card'];
        }
        $member = Member::query()->where('group_id', $group->id)->where('learner_id', $learner['learner_id'])->whereNull('left_at')->first();
        $base = ['id' => $item['id'], 'learner_id' => $learner['learner_id'], 'name' => $learner['name'], 'member' => $member !== null, 'blocked' => $learner['blocked']];

        $scannedAt = isset($item['scanned_at']) ? Carbon::parse($item['scanned_at']) : now();
        if ($scannedAt->isFuture()) {
            $scannedAt = now();
        }

        return DB::transaction(function () use ($item, $session, $group, $member, $learner, $scannedAt, $base, $userId, $byName): array {
            $existing = Attendance::query()->where('session_id', $session->id)->where('learner_id', $learner['learner_id'])->first();
            $status = 'ok';
            if ($existing !== null) {
                $status = $existing->id === $item['id'] ? 'ok' : 'duplicate';
            } else {
                Attendance::query()->create([
                    'id' => $item['id'], 'session_id' => $session->id, 'learner_id' => $learner['learner_id'],
                    'late' => $scannedAt->gt($session->starts_at->copy()->addMinutes(self::LATE_MINUTES)),
                    'scanned_at' => $scannedAt, 'by_name' => $byName, 'created_at' => now(),
                ]);
            }

            if (! empty($item['pay']) && (int) $item['pay']['amount'] > 0 && Payment::query()->whereKey($item['pay']['id'])->doesntExist()) {
                $kind = $item['pay']['kind'] === 'month' ? 'month' : 'session';
                Payment::query()->create([
                    'id' => $item['pay']['id'], 'learner_id' => $learner['learner_id'], 'group_id' => $group->id,
                    'session_id' => $kind === 'session' ? $session->id : null, 'kind' => $kind,
                    'period' => $kind === 'month' ? $session->starts_at->copy()->setTimezone(ShopDay::TZ)->format('Y-m') : null,
                    'amount' => (int) $item['pay']['amount'], 'user_id' => $userId, 'by_name' => $byName, 'created_at' => now(),
                ]);
            }

            return [...$base, 'status' => $status, 'owes' => $this->owes($group, $session, $member, $learner['learner_id'])];
        });
    }

    /** What the student still owes for this session, or for this month on the monthly plan (a paid month covers its sessions on any plan). */
    public function owes(Group $group, Session $session, ?Member $member, string $learnerId): int
    {
        if ($group->monthly_fee) {
            $period = $session->starts_at->copy()->setTimezone(ShopDay::TZ)->format('Y-m');
            $paidMonth = (int) Payment::query()->where('group_id', $group->id)->where('learner_id', $learnerId)->where('kind', 'month')->where('period', $period)->sum('amount');
            if ($paidMonth >= $group->monthly_fee) {
                return 0;
            }
            if ($member !== null && $member->plan === 'monthly') {
                return $group->monthly_fee - $paidMonth;
            }
        }
        $paid = (int) Payment::query()->where('session_id', $session->id)->where('learner_id', $learnerId)->sum('amount');

        return max(0, $group->session_fee - $paid);
    }
}
