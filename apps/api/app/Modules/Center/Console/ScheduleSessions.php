<?php

declare(strict_types=1);

namespace App\Modules\Center\Console;

use App\Modules\Center\Models\Group;
use App\Modules\Center\Support\Timetable;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Console\Command;

/** Every active group's sessions for the coming week, from its weekly times. */
final class ScheduleSessions extends Command
{
    protected $signature = 'center:schedule';

    protected $description = 'Create the coming week of center sessions from the groups\' times';

    public function handle(CurrentTenant $tenant): int
    {
        $created = 0;
        Group::withoutTenancy()->where('active', true)->get(['id', 'tenant_id'])->each(function (Group $row) use ($tenant, &$created): void {
            $created += $tenant->runAs($row->tenant_id, fn () => Timetable::ensureAhead(Group::query()->findOrFail($row->id)));
        });
        $this->info("{$created} session(s) created.");

        return self::SUCCESS;
    }
}
