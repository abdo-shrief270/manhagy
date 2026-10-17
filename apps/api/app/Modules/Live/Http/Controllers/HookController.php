<?php

declare(strict_types=1);

namespace App\Modules\Live\Http\Controllers;

use App\Modules\Live\Support\Hook;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** MediaMTX's authentication hook (authHTTPAddress = …/public/live/hook/{LIVE_SECRET}). */
final class HookController
{
    public function __invoke(Request $request, string $secret, Hook $hook): Response
    {
        $expected = (string) config('services.live.secret');
        abort_if($expected === '' || ! hash_equals($expected, $secret), 404);

        return $hook->allows($request->json()->all()) ? response('', 200) : response('', 401);
    }
}
