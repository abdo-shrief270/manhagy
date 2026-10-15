<?php

declare(strict_types=1);

namespace App\Modules\Codes\Http\Controllers;

use App\Modules\Codes\Support\Redeemer;
use App\Modules\Learners\Contracts\LearnerDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RedeemController
{
    public function __invoke(Request $request, Redeemer $redeemer, LearnerDirectory $learners): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:40']], [], ['code' => 'الكود']);
        $learner = $learners->current() ?? abort(401);

        return response()->json(['data' => $redeemer->redeem($learner['learner_id'], $data['code'], $request->ip())]);
    }
}
