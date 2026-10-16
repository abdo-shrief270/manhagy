<?php

use App\Modules\Reports\Http\Controllers\DashboardController;
use App\Modules\Reports\Http\Controllers\ParentReportController;
use Illuminate\Support\Facades\Route;

Route::get('dashboard', DashboardController::class)->middleware(['auth:sanctum', 'tenant', 'can:reports.view']);

Route::middleware(['auth:sanctum', 'tenant', 'can:reports.parents'])->controller(ParentReportController::class)->group(function (): void {
    Route::get('parent-reports', 'index');
    Route::get('parent-reports/{learner}', 'show')->whereUuid('learner');
});
Route::get('public/parent-report/{tenant}/{learner}', [ParentReportController::class, 'public'])
    ->middleware(['signed:relative', 'throttle:60,1'])->whereUuid(['tenant', 'learner'])->name('parent.report');
