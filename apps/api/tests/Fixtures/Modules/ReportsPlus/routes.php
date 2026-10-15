<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant', 'module:reports_plus'])
    ->get('reports-plus/ping', fn () => ['data' => 'ok'])->middleware('can:reports_plus.view');
