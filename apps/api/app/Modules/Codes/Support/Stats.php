<?php

declare(strict_types=1);

namespace App\Modules\Codes\Support;

use App\Modules\Codes\Models\Code;
use App\Modules\Codes\Models\DistributorPayment;

/** Counts per batch and a distributor's account: cards given, used, cancelled, what they owe. */
final class Stats
{
    /**
     * @param  list<string>  $batchIds
     * @return array<string, array{unused: int, used: int, cancelled: int}>
     */
    public static function batches(array $batchIds): array
    {
        $out = array_fill_keys($batchIds, ['unused' => 0, 'used' => 0, 'cancelled' => 0]);
        foreach (Code::query()->whereIn('batch_id', $batchIds)->selectRaw('batch_id, status, count(*) as n')->groupBy('batch_id', 'status')->get() as $row) {
            $out[$row->batch_id][$row->status] = (int) $row->n;
        }

        return $out;
    }

    /**
     * Sold = used cards × the batch's card price; due = sold − collected (> 0 = they owe the teacher).
     *
     * @param  list<string>  $distributorIds
     * @return array<string, array<string, int>>
     */
    public static function distributors(array $distributorIds): array
    {
        $out = array_fill_keys($distributorIds, ['given' => 0, 'used' => 0, 'unused' => 0, 'cancelled' => 0, 'sold' => 0, 'collected' => 0, 'due' => 0]);
        $rows = Code::query()->join('code_batches as b', 'b.id', '=', 'codes.batch_id')->whereIn('b.distributor_id', $distributorIds)
            ->selectRaw('b.distributor_id, codes.status, count(*) as n, sum(b.unit_price) as value')->groupBy('b.distributor_id', 'codes.status')->get();
        foreach ($rows as $row) {
            $out[$row->distributor_id]['given'] += (int) $row->n;
            $out[$row->distributor_id][$row->status] += (int) $row->n;
            if ($row->status === 'used') {
                $out[$row->distributor_id]['sold'] += (int) $row->value;
            }
        }
        foreach (DistributorPayment::query()->whereIn('distributor_id', $distributorIds)->selectRaw('distributor_id, sum(amount) as total')->groupBy('distributor_id')->get() as $row) {
            $out[$row->distributor_id]['collected'] = (int) $row->total;
        }
        foreach ($out as $id => $s) {
            $out[$id]['due'] = $s['sold'] - $s['collected'];
        }

        return $out;
    }
}
