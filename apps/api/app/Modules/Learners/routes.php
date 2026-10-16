<?php

use App\Modules\Learners\Http\Controllers\LearnerAuthController;
use App\Modules\Learners\Http\Controllers\LearnerController;
use App\Modules\Learners\Http\Controllers\WhatsappWebhookController;
use Illuminate\Support\Facades\Route;

// On a teacher's platform, before signing in.
Route::prefix('public/sites/{slug}')->where(['slug' => '[A-Za-z0-9-]{3,30}'])->controller(LearnerAuthController::class)->group(function (): void {
    Route::post('register', 'register')->middleware('throttle:10,1');
    Route::post('login', 'login')->middleware('throttle:20,1');
    Route::post('password/forgot', 'forgotPassword')->middleware('throttle:5,10');
    Route::post('password/reset', 'resetPassword')->middleware('throttle:10,10');
});

// WhatsApp Cloud API webhook: students confirming their phone by messaging the platform.
Route::controller(WhatsappWebhookController::class)->prefix('public/whatsapp/webhook')->group(function (): void {
    Route::get('/', 'handshake');
    Route::post('/', 'receive')->middleware('throttle:600,1');
});

// The student's own session (listed open in RouteAuthorizationTest).
Route::middleware(['auth:sanctum', 'learner'])->prefix('learner')->controller(LearnerAuthController::class)->group(function (): void {
    Route::get('me', 'me');
    Route::put('me', 'update');
    Route::post('logout', 'logout');
    Route::post('phone/verify', 'startVerification')->middleware('throttle:10,10');
    Route::get('phone/verify', 'verificationStatus');
});

// The account's side.
Route::middleware(['auth:sanctum', 'tenant'])->prefix('learners')->controller(LearnerController::class)->group(function (): void {
    Route::pattern('profile', '[0-9a-fA-F-]{36}');
    Route::pattern('device', '[0-9a-fA-F-]{36}');
    Route::get('/', 'index')->middleware('can:learners.view');
    Route::get('{profile}', 'show')->middleware('can:learners.view');
    Route::patch('{profile}', 'update')->middleware('can:learners.manage');
    Route::post('{profile}/block', 'block')->middleware('can:learners.manage');
    Route::post('{profile}/unblock', 'unblock')->middleware('can:learners.manage');
    Route::delete('{profile}/devices/{device}', 'removeDevice')->middleware('can:learners.devices');
});
