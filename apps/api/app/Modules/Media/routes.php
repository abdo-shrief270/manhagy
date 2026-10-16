<?php

use App\Modules\Media\Http\Controllers\HlsController;
use App\Modules\Media\Http\Controllers\MediaController;
use App\Modules\Media\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant', 'can:media.upload'])->prefix('media')->group(function (): void {
    Route::controller(MediaController::class)->group(function (): void {
        Route::get('/', 'index');
        Route::post('videos', 'createVideo')->middleware('throttle:60,1');
        Route::post('files', 'uploadFile')->middleware('throttle:60,1');
        Route::post('{asset}/check', 'check')->whereUuid('asset');
    });
    // A self-hosted video's original, in chunks.
    Route::controller(UploadController::class)->prefix('uploads/{video}')->whereUuid('video')->group(function (): void {
        Route::get('/', 'show');
        Route::put('/', 'append')->middleware('throttle:600,1');
    });
});

Route::get('media/files/{tenant}/{asset}', [MediaController::class, 'file'])
    ->middleware(['signed', 'throttle:120,1'])->whereUuid(['tenant', 'asset'])->name('media.file');

// Self-hosted videos (HLS): the token in the address is the permission.
Route::prefix('public/hls/{video}')->whereUuid('video')->controller(HlsController::class)->group(function (): void {
    Route::get('master.m3u8', 'master')->middleware('throttle:120,1');
    Route::get('{height}p.m3u8', 'rendition')->where('height', '[0-9]{3,4}')->middleware('throttle:240,1');
    Route::get('key', 'key')->middleware('throttle:120,1');
    Route::get('{height}p/{segment}.ts', 'segment')->where(['height' => '[0-9]{3,4}', 'segment' => '[0-9]{4,6}'])->middleware('throttle:1200,1');
    Route::get('poster.jpg', 'poster')->middleware('throttle:240,1');
});
