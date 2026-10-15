<?php

declare(strict_types=1);

namespace App\Modules\Reports\Http\Controllers;

use App\Modules\Reports\Support\Dashboard;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DashboardController
{
    public function __invoke(Request $request, Dashboard $dashboard, CurrentTenant $tenant): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => $dashboard->for($tenant->idOrFail(), (bool) $user?->can('reports.money'), (bool) $user?->can('wallet.confirm'))]);
    }
}
