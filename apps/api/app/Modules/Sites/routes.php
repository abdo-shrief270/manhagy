<?php

use App\Modules\Sites\Http\Controllers\PublicSiteController;
use App\Modules\Sites\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::get('public/sites/{slug}', PublicSiteController::class)->middleware('throttle:120,1')->where('slug', '[A-Za-z0-9-]{3,30}');

Route::middleware(['auth:sanctum', 'tenant', 'can:site.manage'])->group(function (): void {
    Route::get('site', [SiteController::class, 'show']);
    Route::put('site', [SiteController::class, 'update']);
});
