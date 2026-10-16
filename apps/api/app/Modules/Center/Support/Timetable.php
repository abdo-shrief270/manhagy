<?php

declare(strict_types=1);

namespace App\Modules\Center\Support;

use App\Modules\Center\Models\Group;
use App\Modules\Center\Models\Session;
use App\Support\Time\ShopDay;
use Illuminate\Support\Carbon;

/** A group's weekly times (Cairo) become its sessions: the next days ahead, and one opened by hand. */
final class Timetable
{
    public const DAYS_AHEAD = 7;

    public const DAY_NAMES = ['الأحد', 'الاتنين', 'التلات', 'الأربع', 'الخميس', 'الجمعة', 'السبت'];

    /** @return int sessions created */
    public static function ensureAhead(Group $group, int $days = self::DAYS_AHEAD): int
    {
        if (! $group->active || $group->schedule === []) {
            return 0;
        }
        $created = 0;
        $today = Carbon::now(ShopDay::TZ)->startOfDay();
        for ($i = 0; $i <= $days; $i++) {
            $date = $today->copy()->addDays($i);
            foreach ($group->schedule as $slot) {
                if ((int) $slot['day'] !== $date->dayOfWeek) {
                    continue;
                }
                [$h, $m] = array_map('intval', explode(':', (string) $slot['time']));
                $starts = $date->copy()->setTime($h, $m)->utc();
                $session = Session::query()->firstOrCreate(
                    ['group_id' => $group->id, 'starts_at' => $starts],
                    ['minutes' => (int) ($slot['minutes'] ?? 90)],
                );
                $created += $session->wasRecentlyCreated ? 1 : 0;
            }
        }

        return $created;
    }

    /** The session the door should use now: one that is running or starts within the hour, else a new one now. */
    public static function current(Group $group): Session
    {
        $now = now();
        $found = Session::query()->where('group_id', $group->id)->where('status', 'scheduled')
            ->whereBetween('starts_at', [$now->copy()->subHours(3), $now->copy()->addHour()])
            ->orderByRaw('abs(extract(epoch from (starts_at - ?)))', [$now])->first();

        return $found ?? Session::query()->create(['group_id' => $group->id, 'starts_at' => $now->copy()->startOfMinute(), 'minutes' => (int) ($group->schedule[0]['minutes'] ?? 90)]);
    }

    /** «السبت والتلات 4:00 م» */
    public static function label(array $schedule): string
    {
        return collect($schedule)->map(function (array $slot): string {
            [$h, $m] = array_map('intval', explode(':', (string) $slot['time']));
            $time = Carbon::createFromTime($h, $m)->locale('ar')->translatedFormat('g:i a');

            return self::DAY_NAMES[(int) $slot['day']].' '.$time;
        })->implode('، ');
    }
}
