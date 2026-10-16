<?php

declare(strict_types=1);

namespace App\Modules\Center\Http\Controllers;

use App\Modules\Center\Support\Cards;
use App\Modules\Center\Support\Door;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The door's scans, one or a batch saved while offline. */
final class DoorController
{
    public const MAX_BATCH = 300;

    public function scans(Request $request, Door $door, CurrentTenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'scans' => ['required', 'array', 'min:1', 'max:'.self::MAX_BATCH],
            'scans.*.id' => ['required', 'uuid'],
            'scans.*.session_id' => ['required', 'uuid'],
            'scans.*.token' => ['required_without:scans.*.learner_id', 'nullable', 'string', 'max:80'],
            'scans.*.learner_id' => ['required_without:scans.*.token', 'nullable', 'uuid'],
            'scans.*.scanned_at' => ['nullable', 'date'],
            'scans.*.pay' => ['nullable', 'array'],
            'scans.*.pay.id' => ['required_with:scans.*.pay', 'uuid'],
            'scans.*.pay.kind' => ['required_with:scans.*.pay', 'in:session,month'],
            'scans.*.pay.amount' => ['required_with:scans.*.pay', 'integer', 'min:0', 'max:100000000'],
        ]);
        $tenantId = $tenant->idOrFail();
        $user = $request->user();

        $results = array_map(function (array $scan) use ($door, $tenantId, $user): array {
            $learnerId = $scan['learner_id'] ?? Cards::read($tenantId, (string) $scan['token']);
            if ($learnerId === null) {
                return ['id' => $scan['id'], 'status' => 'unknown_card'];
            }

            return $door->scan([...$scan, 'learner_id' => $learnerId, 'pay' => isset($scan['pay']) ? [...$scan['pay'], 'amount' => (int) $scan['pay']['amount']] : null], $tenantId, $user?->getAuthIdentifier(), $user?->name);
        }, $data['scans']);

        return response()->json(['data' => $results]);
    }
}
