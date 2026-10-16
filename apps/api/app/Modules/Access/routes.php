<?php

use App\Modules\Access\Http\Controllers\EnrollmentController;
use App\Modules\Access\Http\Controllers\LearnerCourseController;
use Illuminate\Support\Facades\Route;

// The student (the learner middleware: their token, site and device; RouteAuthorizationTest lets these through).
Route::middleware(['auth:sanctum', 'learner'])->prefix('learner')->controller(LearnerCourseController::class)->group(function (): void {
    Route::get('courses', 'index');
    Route::get('courses/{course}', 'show')->whereUuid('course');
    Route::post('lessons/{lesson}/open', 'open')->whereUuid('lesson')->middleware(['learner.verified', 'throttle:30,1']);
    Route::post('playback/{session}/beat', 'beat')->whereUuid('session')->middleware('throttle:20,1');
    Route::post('playback/{session}/stop', 'stop')->whereUuid('session');
});

Route::middleware(['auth:sanctum', 'tenant'])->controller(EnrollmentController::class)->group(function (): void {
    Route::get('enrollments', 'index')->middleware('can:courses.view');
    Route::post('enrollments', 'store')->middleware('can:access.enroll');
    Route::post('enrollments/{enrollment}/revoke', 'revoke')->whereUuid('enrollment')->middleware('can:access.enroll');
    Route::post('access/extra-views', 'extraViews')->middleware('can:access.enroll');
    Route::get('access/progress', 'progress')->middleware('can:learners.view');
});
