<?php

declare(strict_types=1);

namespace App\Modules\Live\Jobs;

use App\Modules\Live\Models\LiveSession;
use App\Modules\Live\Support\Studio;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** The lesson just went live: ask Egress to record the room (it may take a while to answer). */
final class StartRecording implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public readonly string $tenantId, public readonly string $sessionId) {}

    public function handle(Studio $studio, CurrentTenant $tenant): void
    {
        $tenant->runAs($this->tenantId, function () use ($studio): void {
            $session = LiveSession::query()->find($this->sessionId);
            if ($session !== null) {
                $studio->record($session);
            }
        });
    }
}
