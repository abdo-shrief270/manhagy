<?php

use App\Modules\Reports\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('dashboard', DashboardController::class)->middleware(['auth:sanctum', 'tenant', 'can:reports.view']);
