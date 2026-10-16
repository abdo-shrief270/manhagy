<?php

use App\Modules\Marketplace\Http\Controllers\LearnerReviewController;
use App\Modules\Marketplace\Http\Controllers\MarketController;
use App\Modules\Marketplace\Http\Controllers\PublicMarketController;
use Illuminate\Support\Facades\Route;

// «سوق منهجي»: read-only, no sign-in.
Route::prefix('public/market')->middleware('throttle:120,1')->controller(PublicMarketController::class)->group(function (): void {
    Route::get('/', 'home');
    Route::get('courses', 'courses');
    Route::get('courses/{course}', 'course')->whereUuid('course');
    Route::get('teachers/{slug}', 'teacher')->where('slug', '[A-Za-z0-9-]{3,30}');
    Route::get('nodes', 'nodes');
});

Route::middleware(['auth:sanctum', 'learner'])->prefix('learner/courses/{course}/review')->whereUuid('course')
    ->controller(LearnerReviewController::class)->group(function (): void {
        Route::get('/', 'show');
        Route::put('/', 'save')->middleware('throttle:20,1');
    });

Route::middleware(['auth:sanctum', 'tenant', 'can:marketplace.manage'])->prefix('marketplace')->controller(MarketController::class)->group(function (): void {
    Route::get('/', 'show');
    Route::put('/', 'update');
    Route::get('reviews', 'reviews');
    Route::get('commissions', 'commissions');
});
