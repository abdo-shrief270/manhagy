<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\Admin;

use App\Modules\Billing\Support\AdminLog;
use App\Modules\Marketplace\Contracts\MarketAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** «سوق منهجي» for the platform admins: moderating reviews. */
final class AdminMarketController
{
    public function __construct(private readonly MarketAdmin $market, private readonly AdminLog $log) {}

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
