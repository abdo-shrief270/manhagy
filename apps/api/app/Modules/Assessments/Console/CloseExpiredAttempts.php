<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Console;

use App\Modules\Assessments\Models\ExamAttempt;
use App\Modules\Assessments\Support\Sitting;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Console\Command;

/** Submits attempts whose time ran out (the student closed the page), so results and «completed» don't wait for them. */
final class CloseExpiredAttempts extends Command
{
    protected $signature = 'assessments:close-expired';

    protected $description = 'Submit exam attempts whose time is up';

    public function handle(Sitting $sitting, CurrentTenant $tenant): int
    {
        $count = 0;
        ExamAttempt::withoutTenancy()->where('status', 'in_progress')->where('ends_at', '<', now()->subSeconds(Sitting::GRACE_SECONDS))
            ->orderBy('ends_at')->limit(5000)->get(['id', 'tenant_id'])
            ->each(function (ExamAttempt $row) use ($sitting, $tenant, &$count): void {
                $tenant->runAs($row->tenant_id, fn () => $sitting->submit(ExamAttempt::query()->findOrFail($row->id), 'timer'));
                $count++;
            });
        $this->info("{$count} attempt(s) submitted.");

        return self::SUCCESS;
    }
}
