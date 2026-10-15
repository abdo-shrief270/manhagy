<?php

use App\Modules\Assessments\Http\Controllers\ExamController;
use App\Modules\Assessments\Http\Controllers\LearnerExamController;
use App\Modules\Assessments\Http\Controllers\QuestionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'learner'])->prefix('learner')->controller(LearnerExamController::class)->group(function (): void {
    Route::pattern('lesson', '[0-9a-fA-F-]{36}');
    Route::pattern('attempt', '[0-9a-fA-F-]{36}');
    Route::pattern('question', '[0-9a-fA-F-]{36}');
    Route::get('exams/{lesson}', 'show');
    Route::post('exams/{lesson}/start', 'start')->middleware('throttle:20,1');
    Route::get('attempts/{attempt}', 'paper');
    Route::put('attempts/{attempt}/answers/{question}', 'answer')->middleware('throttle:240,1');
    Route::post('attempts/{attempt}/leave', 'leave')->middleware('throttle:60,1');
    Route::post('attempts/{attempt}/submit', 'submit');
    Route::get('attempts/{attempt}/result', 'result');
});

Route::middleware(['auth:sanctum', 'tenant'])->prefix('assessments')->group(function (): void {
    Route::pattern('question', '[0-9a-fA-F-]{36}');
    Route::pattern('lesson', '[0-9a-fA-F-]{36}');
    Route::controller(QuestionController::class)->middleware('can:assessments.manage')->group(function (): void {
        Route::get('questions', 'index');
        Route::post('questions', 'store');
        Route::patch('questions/{question}', 'update');
        Route::delete('questions/{question}', 'destroy');
    });
    Route::controller(ExamController::class)->group(function (): void {
        Route::get('exams/{lesson}', 'show')->middleware('can:assessments.manage');
        Route::put('exams/{lesson}', 'save')->middleware('can:assessments.manage');
        Route::get('exams/{lesson}/results', 'results')->middleware('can:assessments.view');
    });
});
