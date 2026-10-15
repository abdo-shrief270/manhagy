<?php

declare(strict_types=1);

namespace App\Modules\Codes\Http\Controllers;

use App\Modules\Codes\Models\Code;
use App\Modules\Codes\Models\CodeBatch;
use App\Modules\Codes\Models\Distributor;
use App\Modules\Codes\Support\CodeFormat;
use App\Modules\Codes\Support\Stats;
use App\Modules\Courses\Contracts\CourseCatalog;
use App\Modules\Learners\Contracts\LearnerDirectory;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Numbering\DocumentNumbers;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Code batches: made (the codes shown this once), followed, cancelled; and looking a code up. */
final class BatchController
{
    public const MAX_COUNT = 2000;

    public function __construct(
        private readonly CurrentTenant $tenant,
        private readonly CourseCatalog $courses,
        private readonly LearnerDirectory $learners,
        private readonly Auditor $auditor,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['distributor_id' => ['nullable', 'uuid'], 'course_id' => ['nullable', 'uuid']]);
        $batches = CodeBatch::query()
            ->when($data['distributor_id'] ?? null, fn ($q, $id) => $q->where('distributor_id', $id))
            ->when($data['course_id'] ?? null, fn ($q, $id) => $q->where('course_id', $id))
            ->orderByDesc('number')->limit(300)->get();

        return response()->json(['data' => $this->summaries($batches->all())]);
    }

    public function store(Request $request, DocumentNumbers $numbers): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['course', 'balance'])],
            'course_id' => ['required_if:kind,course', 'nullable', 'uuid'],
            'days' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'value' => ['required_if:kind,balance', 'nullable', 'integer', 'min:100', 'max:10000000'],
            'unit_price' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'count' => ['required', 'integer', 'min:1', 'max:'.self::MAX_COUNT],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'distributor_id' => ['nullable', 'uuid'],
            'label' => ['nullable', 'string', 'max:120'],
        ], [], ['count' => 'عدد الأكواد', 'value' => 'قيمة الكود']);

        $course = null;
        if ($data['kind'] === 'course') {
            $course = $this->courses->course($data['course_id'], evenUnpublished: true);
            if ($course === null || $course['status'] === 'archived') {
                throw new DomainRuleException('اختار كورس مفتوح.', 'course_closed');
            }
        }
        if (isset($data['distributor_id']) && ! Distributor::query()->whereKey($data['distributor_id'])->where('active', true)->exists()) {
            throw new DomainRuleException('الموزّع ده مش موجود أو موقوف.', 'distributor_unknown');
        }

        [$batch, $codes] = DB::transaction(function () use ($data, $course, $numbers, $request): array {
            $tenantId = $this->tenant->idOrFail();
            $batch = CodeBatch::query()->create([
                'number' => $numbers->next($tenantId, 'code_batch'), 'label' => $data['label'] ?? null, 'kind' => $data['kind'],
                'course_id' => $course['id'] ?? null, 'days' => $data['kind'] === 'course' ? ($data['days'] ?? null) : null,
                'value' => $data['kind'] === 'balance' ? $data['value'] : 0,
                'unit_price' => $data['unit_price'] ?? ($data['kind'] === 'balance' ? $data['value'] : $course['price']),
                'count' => $data['count'], 'expires_at' => $data['expires_at'] ?? null, 'distributor_id' => $data['distributor_id'] ?? null,
                'created_by_name' => $request->user()?->name,
            ]);

            $codes = [];
            $rows = [];
            $seen = [];
            for ($serial = 1; $serial <= $data['count']; $serial++) {
                do {
                    $code = CodeFormat::generate();
                } while (isset($seen[$code]));
                $seen[$code] = true;
                $codes[] = ['serial' => $serial, 'code' => CodeFormat::pretty($code)];
                $rows[] = ['id' => (string) Str::uuid7(), 'tenant_id' => $tenantId, 'batch_id' => $batch->id, 'serial' => $serial, 'hash' => CodeFormat::hash($tenantId, $code), 'status' => 'unused'];
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                Code::query()->insert($chunk);
            }
            $what = $course !== null ? "«{$course['title']}»" : 'رصيد '.($data['value'] / 100).' ج';
            $this->auditor->record('codes.batch_created', "دفعة أكواد {$batch->code()}: {$data['count']} كود لـ {$what}", $batch);

            return [$batch, $codes];
        });

        return response()->json(['data' => [...$this->summaries([$batch])[0], 'codes' => $codes]], 201);
    }

    public function show(string $batch): JsonResponse
    {
        $found = CodeBatch::query()->findOrFail($batch);
        $used = Code::query()->where('batch_id', $found->id)->where('status', 'used')->orderByDesc('used_at')->limit(1000)->get();
        $names = [];
        foreach ($used->pluck('used_by')->filter()->unique() as $id) {
            $names[$id] = $this->learners->find($id)['name'] ?? null;
        }

        return response()->json(['data' => [
            ...$this->summaries([$found])[0],
            'used' => $used->map(fn (Code $c) => ['serial' => $c->serial, 'learner_id' => $c->used_by, 'learner_name' => $names[$c->used_by] ?? null, 'used_at' => $c->used_at?->toIso8601String()])->all(),
            'cancelled_serials' => Code::query()->where('batch_id', $found->id)->where('status', 'cancelled')->orderBy('serial')->pluck('serial')->all(),
        ]]);
    }

    /** Every unused code of the batch, or only the cards given by number (a lost / stolen stack). */
    public function cancel(Request $request, string $batch): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:2', 'max:200'],
            'serials' => ['nullable', 'array', 'max:'.self::MAX_COUNT],
            'serials.*' => ['integer', 'min:1'],
        ], [], ['reason' => 'السبب']);
        $found = CodeBatch::query()->findOrFail($batch);
        if ($found->status === 'cancelled') {
            throw new DomainRuleException('الدفعة دي اتلغت قبل كده.', 'batch_cancelled');
        }

        $count = DB::transaction(function () use ($found, $data): int {
            $codes = Code::query()->where('batch_id', $found->id)->where('status', 'unused')
                ->when(isset($data['serials']), fn ($q) => $q->whereIn('serial', $data['serials']));
            $count = $codes->update(['status' => 'cancelled']);
            if (! isset($data['serials'])) {
                $found->update(['status' => 'cancelled', 'cancelled_reason' => $data['reason']]);
            }
            $this->auditor->record('codes.cancelled', "إلغاء {$count} كود من {$found->code()}: {$data['reason']}", $found, ['serials' => $data['serials'] ?? 'all']);

            return $count;
        });

        return response()->json(['data' => [...$this->summaries([$found->refresh()])[0], 'just_cancelled' => $count]]);
    }

    /** A student says a code doesn't work: what happened to it (never which code a card has). */
    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:40']]);
        $code = CodeFormat::normalize($data['code']);
        if (! CodeFormat::valid($code)) {
            return response()->json(['data' => ['valid' => false]]);
        }
        $row = Code::query()->with('batch')->where('hash', CodeFormat::hash($this->tenant->idOrFail(), $code))->first();

        return response()->json(['data' => $row === null ? ['valid' => true, 'found' => false] : [
            'valid' => true, 'found' => true, 'batch' => $row->batch->code(), 'batch_id' => $row->batch_id, 'serial' => $row->serial,
            'status' => $row->batch->status === 'cancelled' ? 'cancelled' : $row->status,
            'expired' => $row->batch->expires_at?->isPast() ?? false,
            'used_at' => $row->used_at?->toIso8601String(),
            'learner_name' => $row->used_by !== null ? ($this->learners->find($row->used_by)['name'] ?? null) : null,
        ]]);
    }

    /**
     * @param  list<CodeBatch>  $batches
     * @return list<array<string, mixed>>
     */
    private function summaries(array $batches): array
    {
        $stats = Stats::batches(array_map(fn (CodeBatch $b) => $b->id, $batches));
        $distributors = Distributor::query()->whereIn('id', array_filter(array_map(fn (CodeBatch $b) => $b->distributor_id, $batches)))->pluck('name', 'id');
        $titles = [];

        return array_map(function (CodeBatch $b) use ($stats, $distributors, &$titles): array {
            if ($b->course_id !== null) {
                $titles[$b->course_id] ??= $this->courses->course($b->course_id, evenUnpublished: true)['title'] ?? null;
            }

            return [
                'id' => $b->id, 'number' => $b->code(), 'label' => $b->label, 'kind' => $b->kind,
                'course_id' => $b->course_id, 'course_title' => $b->course_id !== null ? $titles[$b->course_id] : null,
                'days' => $b->days, 'value' => $b->value, 'unit_price' => $b->unit_price, 'count' => $b->count,
                'expires_at' => $b->expires_at?->toIso8601String(), 'distributor_id' => $b->distributor_id,
                'distributor_name' => $b->distributor_id !== null ? ($distributors[$b->distributor_id] ?? null) : null,
                'status' => $b->status, 'cancelled_reason' => $b->cancelled_reason, 'created_by_name' => $b->created_by_name,
                'created_at' => $b->created_at->toIso8601String(), 'stats' => $stats[$b->id],
            ];
        }, $batches);
    }
}
