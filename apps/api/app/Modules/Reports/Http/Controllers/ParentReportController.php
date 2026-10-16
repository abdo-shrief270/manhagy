<?php

declare(strict_types=1);

namespace App\Modules\Reports\Http\Controllers;

use App\Modules\Reports\Support\ParentReport;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Weekly reports for parents: the list to send on WhatsApp, one student's report, the parent's page. */
final class ParentReportController
{
    public function __construct(private readonly ParentReport $reports, private readonly CurrentTenant $tenant) {}

    /** Every enrolled student with a parent's phone: the template's variables + the link, ready to send. */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['days' => ['nullable', 'integer', 'min:1', 'max:60']]);
        $days = (int) ($data['days'] ?? 7);
        $t = $this->tenant->idOrFail();
        $students = DB::table('learner_profiles as p')->join('learners as l', 'l.id', '=', 'p.learner_id')
            ->where('p.tenant_id', $t)->whereNotNull('p.parent_phone')->whereNull('p.blocked_at')
            ->whereExists(fn ($q) => $q->from('enrollments as e')->whereColumn('e.learner_id', 'p.learner_id')->where('e.tenant_id', $t)->whereNull('e.revoked_at')
                ->where(fn ($q) => $q->whereNull('e.ends_at')->orWhere('e.ends_at', '>', now())))
            ->orderBy('l.name')->limit(500)->get(['p.learner_id', 'p.parent_phone']);

        return response()->json(['data' => $students->map(function ($s) use ($t, $days): array {
            $report = $this->reports->build($t, $s->learner_id, $days);
            $link = $this->reports->link($t, $s->learner_id, $days);

            return [
                'learner_id' => $s->learner_id, 'parent_phone' => $s->parent_phone, 'student' => $report['student'],
                'watched_minutes' => $report['watched_minutes'], 'missing' => count($report['missing']),
                'message' => [...$report['message'], 'link' => $link],
            ];
        })->all(), 'meta' => ['days' => $days]]);
    }

    public function show(Request $request, string $learner): JsonResponse
    {
        $data = $request->validate(['days' => ['nullable', 'integer', 'min:1', 'max:60']]);
        $days = (int) ($data['days'] ?? 7);
        $report = $this->reports->build($this->tenant->idOrFail(), $learner, $days) ?? abort(404);
        $link = $this->reports->link($this->tenant->idOrFail(), $learner, $days);

        return response()->json(['data' => [...$report, 'link' => $link, 'message' => [...$report['message'], 'link' => $link]]]);
    }

    /** The parent's page (no account): the signature is the permission. */
    public function public(Request $request, string $tenant, string $learner): JsonResponse
    {
        $days = max(1, min(60, (int) $request->query('days', 7)));
        $report = $this->tenant->runAs($tenant, fn () => $this->reports->build($tenant, $learner, $days)) ?? abort(404);
        unset($report['message']);

        return response()->json(['data' => $report])->header('Cache-Control', 'private, no-store');
    }
}
