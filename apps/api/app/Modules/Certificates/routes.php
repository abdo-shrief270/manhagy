<?php

use App\Modules\Certificates\Http\Controllers\CertificateController;
use Illuminate\Support\Facades\Route;

Route::get('public/certificates/{code}', [CertificateController::class, 'verify'])
    ->where('code', '[A-Za-z0-9-]{10,14}')->middleware('throttle:60,1');

Route::middleware(['auth:sanctum', 'learner'])->prefix('learner')->controller(CertificateController::class)->group(function (): void {
    Route::get('certificates', 'mine');
    Route::get('courses/{course}/certificate', 'status')->whereUuid('course');
    Route::post('courses/{course}/certificate', 'claim')->whereUuid('course')->middleware('throttle:20,1');
});

Route::middleware(['auth:sanctum', 'tenant', 'can:certificates.manage'])->prefix('certificates')->controller(CertificateController::class)->group(function (): void {
    Route::get('/', 'index');
    Route::get('courses/{course}', 'settings')->whereUuid('course');
    Route::put('courses/{course}', 'updateSettings')->whereUuid('course');
    Route::post('{certificate}/revoke', 'revoke')->whereUuid('certificate');
});
