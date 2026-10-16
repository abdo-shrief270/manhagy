<?php

use App\Modules\Sites\Http\Controllers\PublicSiteController;
use App\Modules\Sites\Http\Controllers\SiteController;
use App\Modules\Sites\Http\Controllers\SiteTlsController;
use Illuminate\Support\Facades\Route;

Route::get('public/sites/{slug}', PublicSiteController::class)->middleware('throttle:120,1')->where('slug', '[A-Za-z0-9-]{3,30}');

// The edge (Caddy) asks before getting a certificate for a platform's subdomain.
Route::get('public/sites-tls', SiteTlsController::class)->middleware('throttle:120,1');

Route::middleware(['auth:sanctum', 'tenant', 'can:site.manage'])->group(function (): void {
    Route::get('site', [SiteController::class, 'show']);
    Route::put('site', [SiteController::class, 'update']);
});
