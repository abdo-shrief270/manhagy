<?php

use App\Modules\Live\Http\Controllers\HookController;
use App\Modules\Live\Http\Controllers\LearnerLiveController;
use App\Modules\Live\Http\Controllers\StudioController;
use Illuminate\Support\Facades\Route;

// The live server's authentication hook: the secret in the address is the permission.
Route::post('public/live/hook/{secret}', HookController::class)->where('secret', '[A-Za-z0-9_-]{16,128}');

Route::middleware(['auth:sanctum', 'learner'])->prefix('learner/live')->controller(LearnerLiveController::class)->group(function (): void {
    Route::pattern('lesson', '[0-9a-fA-F-]{36}');
    Route::get('{lesson}', 'show');
    Route::post('{lesson}/join', 'join')->middleware(['learner.verified', 'throttle:20,1']);
    Route::get('{lesson}/feed', 'feed')->middleware('throttle:60,1');
    Route::post('{lesson}/messages', 'post')->middleware('throttle:10,1');
    Route::post('attendances/{attendance}/beat', 'beat')->whereUuid('attendance');
    Route::post('attendances/{attendance}/leave', 'leave')->whereUuid('attendance');
    Route::post('polls/{poll}/vote', 'vote')->whereUuid('poll');
});

Route::middleware(['auth:sanctum', 'tenant'])->prefix('live')->controller(StudioController::class)->group(function (): void {
    Route::pattern('lesson', '[0-9a-fA-F-]{36}');
    Route::middleware('can:live.moderate')->group(function (): void {
        Route::get('/', 'index');
        Route::get('{lesson}', 'show');
        Route::patch('{lesson}', 'update');
        Route::get('{lesson}/feed', 'feed');
        Route::post('{lesson}/messages', 'post')->middleware('throttle:60,1');
        Route::patch('messages/{message}', 'moderate')->whereUuid('message');
        Route::post('{lesson}/polls', 'poll');
        Route::post('polls/{poll}/close', 'closePoll')->whereUuid('poll');
        Route::get('{lesson}/attendance', 'attendance');
    });
    Route::middleware('can:live.host')->group(function (): void {
        Route::post('{lesson}/end', 'end');
        Route::post('{lesson}/key', 'rotateKey');
    });
});
