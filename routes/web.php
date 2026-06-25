<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\ServerInfoController;
use App\Http\Controllers\ServerControlController;
use Illuminate\Support\Facades\Route;

// Health check — used by Nginx to detect server status
// (registered without middleware in bootstrap/app.php)

// Server info — returns which server handled the request
Route::get('/server-info', [ServerInfoController::class, 'info'])->name('server.info');

// System Dashboard
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');
Route::post('/dashboard/reset', [DashboardController::class, 'resetCounters'])->name('dashboard.reset');

// Server Control (start/stop from UI)
Route::post('/server/stop',        [ServerControlController::class, 'stop'])->name('server.stop');
Route::post('/server/start',       [ServerControlController::class, 'start'])->name('server.start');
Route::post('/server/start-all',   [ServerControlController::class, 'startAll'])->name('server.startAll');
Route::post('/server/stop-others', [ServerControlController::class, 'stopOthers'])->name('server.stopOthers');

// Home
Route::get('/', function () {
    return redirect()->route('dashboard');
});
