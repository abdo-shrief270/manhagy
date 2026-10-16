<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\Admin;

use App\Modules\Billing\Support\AdminLog;
use App\Modules\Identity\Contracts\ShopDirectory;
use App\Modules\Marketplace\Contracts\MarketAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** «سوق منهجي» for the platform admins: the commissions accounts owe, and moderating reviews. */
final class AdminMarketController
{
    public function __construct(private readonly MarketAdmin $market, private readonly AdminLog $log) {}

    public function owing(ShopDirectory $shops): JsonResponse
    {
        $rows = $this->market->owing();
        $names = $shops->findMany(array_column($rows, 'tenant_id'));

        return response()->json(['data' => array_map(fn (array $r) => [
            ...$r, 'name' => $names[$r['tenant_id']]->name ?? '—', 'code' => $names[$r['tenant_id']]->code ?? null,
        ], $rows)]);
    }

    public function commissions(string $tenant, ShopDirectory $shops): JsonResponse
    {
        $shop = $shops->find($tenant) ?? abort(404);

        return response()->json(['data' => [...$this->market->commissions($tenant), 'name' => $shop->name]]);
    }

    public function settle(Request $request, string $tenant, ShopDirectory $shops): JsonResponse
    {
        $shops->find($tenant) ?? abort(404);
        $data = $request->validate(['reference' => ['required', 'string', 'max:80']], [], ['reference' => 'رقم التحويل']);
        $result = $this->market->settle($tenant, $data['reference']);
        $this->log->record('market_commissions_settled', $tenant, null, [...$result, 'reference' => $data['reference']]);

        return response()->json(['data' => $result]);
    }

    public function void(Request $request, string $commission): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']], [], ['reason' => 'السبب']);
        $row = $this->market->void($commission, $data['reason']);
        $this->log->record('market_commission_voided', $row['tenant_id'], $commission, ['reason' => $data['reason'], 'commission' => $row['commission']]);

        return response()->json(['data' => $row]);
    }

    public function reviews(Request $request): JsonResponse
    {
        $data = $request->validate([
            'hidden' => ['nullable', 'boolean'],
            'max_rating' => ['nullable', 'integer', 'between:1,5'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $result = $this->market->reviews(isset($data['hidden']) ? (bool) $data['hidden'] : null, isset($data['max_rating']) ? (int) $data['max_rating'] : null, (int) ($data['page'] ?? 1));

        return response()->json(['data' => $result['data'], 'meta' => ['total' => $result['total']]]);
    }

    public function updateReview(Request $request, string $review): JsonResponse
    {
        $data = $request->validate(['hidden' => ['required', 'boolean']]);
        $row = $this->market->setReviewHidden($review, (bool) $data['hidden']) ?? abort(404);
        $this->log->record($data['hidden'] ? 'market_review_hidden' : 'market_review_shown', null, $review, ['rating' => $row['rating']]);

        return response()->json(['data' => $row]);
    }
}
