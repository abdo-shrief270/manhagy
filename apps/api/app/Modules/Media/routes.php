<?php

use App\Modules\Media\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant', 'can:media.upload'])->prefix('media')->controller(MediaController::class)->group(function (): void {
    Route::get('/', 'index');
    Route::post('videos', 'createVideo')->middleware('throttle:60,1');
    Route::post('files', 'uploadFile')->middleware('throttle:60,1');
    Route::post('{asset}/check', 'check')->whereUuid('asset');
});

Route::get('media/files/{tenant}/{asset}', [MediaController::class, 'file'])
    ->middleware(['signed', 'throttle:120,1'])->whereUuid(['tenant', 'asset'])->name('media.file');
