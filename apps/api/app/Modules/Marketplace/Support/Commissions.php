<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Support;

use App\Modules\Marketplace\Models\MarketCommission;
use Illuminate\Support\Facades\DB;

/**
 * The platform's commission on courses bought by students who came from the market (a sale that
 * came through the teacher's own link pays none). The teacher collects the money (wallet top-ups go
 * to them), so commissions are a bill the platform settles with the teacher; one left unpaid past
 * `services.market.pay_within_days` takes the account's courses out of the market until settled.
 */
final class Commissions
{
    public static function rate(): int
    {
        return max(0, min(50, (int) config('services.market.commission_percent', 15)));
    }

    /** A purchase from the wallet: a row when the student came from the market and the course is listed. */
    public function record(string $tenantId, string $learnerId, string $courseId, int $amount, string $reference, ?string $via): ?MarketCommission
    {
        if ($via !== 'market' || $amount <= 0 || self::rate() === 0) {
            return null;
        }
        $course = DB::table('market_courses')->where('course_id', $courseId)->where('tenant_id', $tenantId)->first(['title']);
        if ($course === null) {
            return null; // not in the market: the link didn't come from it
        }
        $existing = MarketCommission::withoutTenancy()->where('reference', $reference)->first();
        if ($existing !== null) {
            return $existing;
        }

        return MarketCommission::withoutTenancy()->create([
            'tenant_id' => $tenantId, 'course_id' => $courseId, 'course_title' => $course->title, 'learner_id' => $learnerId,
            'reference' => $reference, 'amount' => $amount, 'rate_percent' => self::rate(),
            'commission' => intdiv($amount * self::rate() + 50, 100), 'created_at' => now(),
        ]);
    }

    public function overdue(string $tenantId): bool
    {
        return MarketCommission::withoutTenancy()->where('tenant_id', $tenantId)->due()
            ->where('created_at', '<', now()->subDays((int) config('services.market.pay_within_days', 30)))->exists();
    }

    /** @return array<string, mixed> what the account owes and since when */
    public function summary(string $tenantId): array
    {
        $due = MarketCommission::withoutTenancy()->where('tenant_id', $tenantId)->due();
        $oldest = (clone $due)->min('created_at');
        $days = (int) config('services.market.pay_within_days', 30);

        return [
            'rate_percent' => self::rate(),
            'due' => (int) (clone $due)->sum('commission'),
            'due_count' => (clone $due)->count(),
            'oldest_due_at' => $oldest === null ? null : now()->parse($oldest)->toIso8601String(),
            'pay_by' => $oldest === null ? null : now()->parse($oldest)->addDays($days)->toIso8601String(),
            'overdue' => $this->overdue($tenantId),
            'settled' => (int) MarketCommission::withoutTenancy()->where('tenant_id', $tenantId)->whereNotNull('settled_at')->whereNull('voided_at')->sum('commission'),
            'sales' => (int) MarketCommission::withoutTenancy()->where('tenant_id', $tenantId)->whereNull('voided_at')->sum('amount'),
            'pay_within_days' => $days,
            'instapay' => array_filter((array) config('billing.instapay')) ?: null,
        ];
    }

    /** @return array{count: int, amount: int} every due commission of the account, settled with one payment */
    public function settle(string $tenantId, string $reference): array
    {
        return DB::transaction(function () use ($tenantId, $reference): array {
            $rows = MarketCommission::withoutTenancy()->where('tenant_id', $tenantId)->due()->lockForUpdate()->get();
            MarketCommission::withoutTenancy()->whereIn('id', $rows->pluck('id'))->update(['settled_at' => now(), 'settled_reference' => $reference]);

            return ['count' => $rows->count(), 'amount' => (int) $rows->sum('commission')];
        });
    }

    public function void(string $id, string $reason): MarketCommission
    {
        $row = MarketCommission::withoutTenancy()->findOrFail($id);
        if ($row->state() === 'due') {
            $row->update(['voided_at' => now(), 'void_reason' => $reason]);
        }

        return $row;
    }
}
