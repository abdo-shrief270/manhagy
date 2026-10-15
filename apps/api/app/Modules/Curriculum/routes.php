<?php

use App\Modules\Curriculum\Http\Controllers\PublicCurriculumController;
use App\Modules\Curriculum\Http\Controllers\SuggestionController;
use Illuminate\Support\Facades\Route;

// The published tree: no sign-in.
Route::prefix('public/curriculum')->middleware('throttle:120,1')->controller(PublicCurriculumController::class)->group(function (): void {
    Route::get('/', 'index');
    Route::get('kinds', 'kinds');
    Route::get('search', 'search');
    Route::get('{node}', 'show')->whereUuid('node');
});

Route::middleware(['auth:sanctum', 'tenant', 'can:curriculum.suggest', 'throttle:30,1'])
    ->post('curriculum/suggestions', SuggestionController::class);
