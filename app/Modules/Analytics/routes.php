<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Analytics\Controllers\DashboardController;
use App\Modules\Analytics\Controllers\ReportController;
use App\Modules\Analytics\Controllers\KPIController;
use App\Modules\Analytics\Controllers\CustomDashboardController;
use App\Modules\Analytics\Controllers\SettingsController;

Route::prefix('analytics')->name('analytics.')->middleware(['module:analytics'])->group(function () {
    // Main dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/data/{metric}', [DashboardController::class, 'getMetricData'])->name('data.metric');
    
    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/generate', [ReportController::class, 'generate'])->name('generate');
        Route::post('/generate', [ReportController::class, 'store'])->name('store');
        Route::get('/{report}', [ReportController::class, 'show'])->name('show');
        Route::get('/{report}/export', [ReportController::class, 'export'])->name('export');
        Route::post('/{report}/schedule', [ReportController::class, 'schedule'])->name('schedule');
        Route::delete('/{report}', [ReportController::class, 'destroy'])->name('destroy');
    });
    
    // KPIs
    Route::prefix('kpis')->name('kpis.')->group(function () {
        Route::get('/', [KPIController::class, 'index'])->name('index');
        Route::get('/create', [KPIController::class, 'create'])->name('create');
        Route::post('/', [KPIController::class, 'store'])->name('store');
        Route::get('/{kpi}/edit', [KPIController::class, 'edit'])->name('edit');
        Route::put('/{kpi}', [KPIController::class, 'update'])->name('update');
        Route::delete('/{kpi}', [KPIController::class, 'destroy'])->name('destroy');
        Route::get('/{kpi}/data', [KPIController::class, 'getData'])->name('data');
    });
    
    // Custom Dashboards
    Route::prefix('dashboards')->name('dashboards.')->group(function () {
        Route::get('/', [CustomDashboardController::class, 'index'])->name('index');
        Route::get('/create', [CustomDashboardController::class, 'create'])->name('create');
        Route::post('/', [CustomDashboardController::class, 'store'])->name('store');
        Route::get('/{dashboard}', [CustomDashboardController::class, 'show'])->name('show');
        Route::get('/{dashboard}/edit', [CustomDashboardController::class, 'edit'])->name('edit');
        Route::put('/{dashboard}', [CustomDashboardController::class, 'update'])->name('update');
        Route::delete('/{dashboard}', [CustomDashboardController::class, 'destroy'])->name('destroy');
        Route::post('/{dashboard}/duplicate', [CustomDashboardController::class, 'duplicate'])->name('duplicate');
        Route::post('/{dashboard}/share', [CustomDashboardController::class, 'share'])->name('share');
    });
    
    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
});