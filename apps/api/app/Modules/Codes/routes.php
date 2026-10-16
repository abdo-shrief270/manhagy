<?php

use App\Modules\Codes\Http\Controllers\BatchController;
use App\Modules\Codes\Http\Controllers\DistributorController;
use App\Modules\Codes\Http\Controllers\RedeemController;
use Illuminate\Support\Facades\Route;

Route::post('learner/codes/redeem', RedeemController::class)->middleware(['auth:sanctum', 'learner', 'learner.verified', 'throttle:30,1']);

Route::middleware(['auth:sanctum', 'tenant'])->prefix('codes')->group(function (): void {
    Route::pattern('batch', '[0-9a-fA-F-]{36}');
    Route::pattern('distributor', '[0-9a-fA-F-]{36}');
    Route::pattern('payment', '[0-9a-fA-F-]{36}');

    Route::controller(BatchController::class)->group(function (): void {
        Route::get('batches', 'index')->middleware('can:codes.view');
        Route::get('batches/{batch}', 'show')->middleware('can:codes.view');
        Route::get('lookup', 'lookup')->middleware(['can:codes.view', 'throttle:30,1']);
        Route::post('batches', 'store')->middleware(['can:codes.manage', 'throttle:20,1']);
        Route::post('batches/{batch}/cancel', 'cancel')->middleware('can:codes.manage');
    });

    Route::controller(DistributorController::class)->middleware('can:codes.manage')->group(function (): void {
        Route::get('distributors', 'index');
        Route::post('distributors', 'store');
        Route::get('distributors/{distributor}', 'show');
        Route::patch('distributors/{distributor}', 'update');
        Route::post('distributors/{distributor}/payments', 'pay');
        Route::post('distributors/{distributor}/payments/{payment}/reverse', 'reverse');
    });
});
