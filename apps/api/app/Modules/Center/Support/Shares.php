<?php

declare(strict_types=1);

namespace App\Modules\Center\Support;

use App\Modules\Center\Models\Settlement;
use App\Modules\Center\Models\Teacher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A teacher's share for a period: a percent of what was collected in their groups, or a fixed
 * amount per attendance; minus what was already settled for that period.
 */
final class Shares
{
    /** @return array<string, int|string> */
    public static function statement(Teacher $teacher, Carbon $from, Carbon $to): array
    {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();
        $groups = DB::table('center_groups')->where('teacher_id', $teacher->id)->pluck('id');
        $attendances = DB::table('center_attendances as a')->join('center_sessions as s', 's.id', '=', 'a.session_id')
            ->whereIn('s.group_id', $groups)->whereBetween('s.starts_at', [$start, $end])->count();
        $collected = (int) DB::table('center_payments')->whereIn('group_id', $groups)->whereBetween('created_at', [$start, $end])->sum('amount');
        $due = $teacher->share_kind === 'per_student'
            ? $attendances * $teacher->share_value
            : intdiv($collected * $teacher->share_value, 100);
        $paid = (int) Settlement::query()->where('teacher_id', $teacher->id)
            ->where('period_from', '>=', $start->toDateString())->where('period_to', '<=', $end->toDateString())->sum('paid');

        return [
            'from' => $start->toDateString(), 'to' => $end->toDateString(),
            'attendances' => $attendances, 'collected' => $collected, 'due' => $due, 'paid' => $paid, 'remaining' => $due - $paid,
        ];
    }
}
