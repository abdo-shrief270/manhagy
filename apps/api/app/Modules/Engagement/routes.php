<?php

use App\Modules\Engagement\Http\Controllers\LearnerQuestionController;
use App\Modules\Engagement\Http\Controllers\QuestionInboxController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'learner'])->prefix('learner/lessons/{lesson}/questions')->whereUuid('lesson')->controller(LearnerQuestionController::class)->group(function (): void {
    Route::get('/', 'index');
    Route::post('/', 'store')->middleware(['learner.verified', 'throttle:10,10']);
});

Route::middleware(['auth:sanctum', 'tenant', 'can:engagement.answer'])->prefix('engagement/questions')->controller(QuestionInboxController::class)->group(function (): void {
    Route::get('/', 'index');
    Route::post('{question}/answer', 'answer')->whereUuid('question');
    Route::post('{question}/hide', 'hide')->whereUuid('question');
});
