<?php

use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Modules\Demo\DemoNote;

Route::middleware(['auth:sanctum', 'tenant', 'module:demo'])->prefix('demo')->group(function (): void {
    Route::get('notes', fn () => ['data' => DemoNote::query()->orderBy('id')->get(['id', 'name'])])->middleware('can:demo.view');
    Route::get('public', fn () => ['data' => 'ok'])->middleware(['can:demo.view', 'feature:demo.public']);
    Route::post('notes', fn () => response()->json(['data' => DemoNote::create(['name' => request('name', 'جديد')])], 201))->middleware('can:demo.manage');
});
