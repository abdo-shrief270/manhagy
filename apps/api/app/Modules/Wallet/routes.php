<?php

use App\Modules\Wallet\Http\Controllers\LearnerWalletController;
use App\Modules\Wallet\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'learner'])->prefix('learner')->controller(LearnerWalletController::class)->group(function (): void {
    Route::get('wallet', 'show');
    Route::post('wallet/topups', 'requestTopup')->middleware('throttle:10,60');
    Route::post('courses/{course}/buy', 'buy')->whereUuid('course')->middleware('throttle:20,1');
});

Route::middleware(['auth:sanctum', 'tenant'])->prefix('wallet')->controller(WalletController::class)->group(function (): void {
    Route::pattern('topup', '[0-9a-fA-F-]{36}');
    Route::get('topups', 'topups')->middleware('can:wallet.confirm');
    Route::get('topups/{topup}/proof', 'proof')->middleware('can:wallet.confirm');
    Route::post('topups/{topup}/approve', 'approve')->middleware('can:wallet.confirm');
    Route::post('topups/{topup}/reject', 'reject')->middleware('can:wallet.confirm');
    Route::get('statement', 'statement')->middleware('can:wallet.view');
    Route::post('adjust', 'adjust')->middleware('can:wallet.adjust');
    Route::get('settings', 'settings')->middleware('can:wallet.settings');
    Route::put('settings', 'updateSettings')->middleware('can:wallet.settings');
});
