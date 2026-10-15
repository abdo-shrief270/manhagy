<?php

use App\Modules\Courses\Http\Controllers\CourseController;
use App\Modules\Courses\Http\Controllers\PublicCourseController;
use Illuminate\Support\Facades\Route;

Route::get('public/sites/{slug}/courses', [PublicCourseController::class, 'index'])
    ->where('slug', '[A-Za-z0-9-]{3,30}')->middleware('throttle:60,1');

Route::middleware(['auth:sanctum', 'tenant'])->prefix('courses')->controller(CourseController::class)->group(function (): void {
    Route::pattern('course', '[0-9a-fA-F-]{36}');
    Route::pattern('chapter', '[0-9a-fA-F-]{36}');
    Route::pattern('lesson', '[0-9a-fA-F-]{36}');
    Route::get('/', 'index')->middleware('can:courses.view');
    Route::get('{course}', 'show')->middleware('can:courses.view');
    Route::middleware('can:courses.manage')->group(function (): void {
        Route::post('/', 'store');
        Route::patch('{course}', 'update');
        Route::delete('{course}', 'destroy');
        Route::put('{course}/order', 'order');
        Route::post('{course}/chapters', 'storeChapter');
        Route::patch('{course}/chapters/{chapter}', 'updateChapter');
        Route::delete('{course}/chapters/{chapter}', 'destroyChapter');
        Route::post('{course}/lessons', 'storeLesson');
        Route::patch('{course}/lessons/{lesson}', 'updateLesson');
        Route::delete('{course}/lessons/{lesson}', 'destroyLesson');
    });
});
