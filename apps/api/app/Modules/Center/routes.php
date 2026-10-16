<?php

use App\Modules\Center\Http\Controllers\CashController;
use App\Modules\Center\Http\Controllers\DoorController;
use App\Modules\Center\Http\Controllers\GroupController;
use App\Modules\Center\Http\Controllers\LearnerCardController;
use App\Modules\Center\Http\Controllers\TeacherController;
use Illuminate\Support\Facades\Route;

Route::get('learner/center/card', LearnerCardController::class)->middleware(['auth:sanctum', 'learner', 'module:center']);

Route::middleware(['auth:sanctum', 'tenant', 'module:center'])->prefix('center')->group(function (): void {
    Route::pattern('group', '[0-9a-fA-F-]{36}');
    Route::pattern('member', '[0-9a-fA-F-]{36}');
    Route::pattern('session', '[0-9a-fA-F-]{36}');
    Route::pattern('teacher', '[0-9a-fA-F-]{36}');

    Route::controller(GroupController::class)->group(function (): void {
        Route::get('groups', 'index')->middleware('can:center.door');
        Route::get('groups/{group}', 'show')->middleware('can:center.door');
        Route::post('groups', 'store')->middleware('can:center.manage');
        Route::patch('groups/{group}', 'update')->middleware('can:center.manage');
        Route::post('groups/{group}/members', 'addMember')->middleware('can:center.manage');
        Route::delete('groups/{group}/members/{member}', 'removeMember')->middleware('can:center.manage');
        Route::post('groups/{group}/open', 'openSession')->middleware('can:center.door');
        Route::post('sessions/{session}/cancel', 'cancelSession')->middleware('can:center.manage');
        Route::get('sessions/today', 'today')->middleware('can:center.door');
        Route::get('sessions/{session}', 'session')->middleware('can:center.door');
        Route::get('cards', 'cards')->middleware('can:center.manage');
    });
    Route::post('scans', [DoorController::class, 'scans'])->middleware(['can:center.door', 'throttle:120,1']);

    Route::controller(CashController::class)->group(function (): void {
        Route::get('cash', 'mine')->middleware('can:center.door');
        Route::get('cash/all', 'all')->middleware('can:center.cash');
        Route::post('handovers', 'handover')->middleware('can:center.cash');
    });

    Route::controller(TeacherController::class)->middleware('can:center.cash')->group(function (): void {
        Route::get('teachers', 'index');
        Route::post('teachers', 'store');
        Route::get('teachers/{teacher}', 'show');
        Route::patch('teachers/{teacher}', 'update');
        Route::post('teachers/{teacher}/settlements', 'settle');
    });
});
