<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\ServerInfoController;
use Illuminate\Support\Facades\Route;

// Health check — used by Nginx to detect server status
// (registered without middleware in bootstrap/app.php)

// Server info — returns which server handled the request
Route::get('/server-info', [ServerInfoController::class, 'info'])->name('server.info');

// System Dashboard
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');
Route::post('/dashboard/reset', [DashboardController::class, 'resetCounters'])->name('dashboard.reset');

// Home
Route::get('/', function () {
    return redirect()->route('dashboard');
});
