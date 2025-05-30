<?php

use App\Http\Controllers\SuperAdmin\SuperAdminAuthController;
use App\Http\Controllers\SuperAdmin\DashboardController;
use App\Http\Controllers\SuperAdmin\TenantController;
use App\Http\Controllers\SuperAdmin\SubscriptionController;
use App\Http\Controllers\SuperAdmin\SystemController;
use App\Http\Controllers\SuperAdmin\AnalyticsController;
use App\Http\Controllers\SuperAdmin\ModuleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Super Admin Routes
|--------------------------------------------------------------------------
|
| These routes are for the super admin panel that manages the multi-tenant
| system. They require super admin authentication and have full system access.
|
*/

// Super Admin Authentication Routes
Route::middleware('super_admin_guest')->group(function () {
    Route::get('/super-admin/login', [SuperAdminAuthController::class, 'showLoginForm'])
        ->name('super-admin.login');
    Route::post('/super-admin/login', [SuperAdminAuthController::class, 'login'])
        ->name('super-admin.login.post');
});

Route::post('/super-admin/logout', [SuperAdminAuthController::class, 'logout'])
    ->middleware('auth:super_admin')
    ->name('super-admin.logout');

// Protected Super Admin Routes
Route::middleware(['auth:super_admin', 'super_admin', 'prevent_impersonation_conflict'])->group(function () {
    
    // Dashboard
    Route::get('/super-admin', [DashboardController::class, 'index'])
        ->name('super-admin.dashboard');
    
    // Tenant Management
    Route::prefix('super-admin/tenants')->name('super-admin.tenants.')->group(function () {
        Route::get('/', [TenantController::class, 'index'])->name('index');
        Route::get('/create', [TenantController::class, 'create'])->name('create');
        Route::post('/', [TenantController::class, 'store'])->name('store');
        Route::get('/{tenant}', [TenantController::class, 'show'])->name('show');
        Route::get('/{tenant}/edit', [TenantController::class, 'edit'])->name('edit');
        Route::put('/{tenant}', [TenantController::class, 'update'])->name('update');
        Route::delete('/{tenant}', [TenantController::class, 'destroy'])->name('destroy');
        Route::post('/{tenant}/suspend', [TenantController::class, 'suspend'])->name('suspend');
        Route::post('/{tenant}/reactivate', [TenantController::class, 'reactivate'])->name('reactivate');
        Route::post('/{tenant}/impersonate', [TenantController::class, 'impersonate'])->name('impersonate');
    });
    
    // Subscription Management
    Route::prefix('super-admin/subscriptions')->name('super-admin.subscriptions.')->group(function () {
        // Subscription Plans
        Route::get('/plans', [SubscriptionController::class, 'plans'])->name('plans');
        Route::get('/plans/create', [SubscriptionController::class, 'createPlan'])->name('plans.create');
        Route::post('/plans', [SubscriptionController::class, 'storePlan'])->name('plans.store');
        Route::get('/plans/{plan}/edit', [SubscriptionController::class, 'editPlan'])->name('plans.edit');
        Route::put('/plans/{plan}', [SubscriptionController::class, 'updatePlan'])->name('plans.update');
        Route::patch('/plans/{plan}/toggle', [SubscriptionController::class, 'togglePlanStatus'])->name('plans.toggle');
        Route::delete('/plans/{plan}', [SubscriptionController::class, 'destroyPlan'])->name('plans.destroy');
        
        // Subscriptions
        Route::get('/', [SubscriptionController::class, 'subscriptions'])->name('index');
        Route::post('/{subscription}/upgrade', [SubscriptionController::class, 'upgradeTenant'])->name('upgrade');
        Route::post('/{subscription}/downgrade', [SubscriptionController::class, 'downgradeTenant'])->name('downgrade');
        Route::post('/{subscription}/cancel', [SubscriptionController::class, 'cancelSubscription'])->name('cancel');
        
        // Revenue Analytics
        Route::get('/revenue', [SubscriptionController::class, 'revenue'])->name('revenue');
    });
    
    // System Management
    Route::prefix('super-admin/system')->name('super-admin.system.')->group(function () {
        // Settings
        Route::get('/settings', [SystemController::class, 'settings'])->name('settings');
        Route::put('/settings', [SystemController::class, 'updateSettings'])->name('settings.update');
        
        // Monitoring
        Route::get('/monitoring', [SystemController::class, 'monitoring'])->name('monitoring');
        
        // Audit Logs
        Route::get('/audit-logs', [SystemController::class, 'auditLogs'])->name('audit-logs');
        
        // Maintenance
        Route::get('/maintenance', [SystemController::class, 'maintenance'])->name('maintenance');
        Route::post('/maintenance/toggle', [SystemController::class, 'toggleMaintenance'])->name('maintenance.toggle');
        Route::post('/cache/clear', [SystemController::class, 'clearCache'])->name('cache.clear');
        Route::post('/optimize', [SystemController::class, 'optimizeSystem'])->name('optimize');
        Route::post('/command', [SystemController::class, 'runCommand'])->name('command');
    });

    // Analytics
    Route::prefix('super-admin/analytics')->name('super-admin.analytics.')->group(function () {
        Route::get('/', [AnalyticsController::class, 'index'])->name('index');
    });

    // Module Management
    Route::prefix('super-admin/modules')->name('super-admin.modules.')->group(function () {
        Route::get('/', [ModuleController::class, 'index'])->name('index');
        Route::get('/create', [ModuleController::class, 'create'])->name('create');
        Route::post('/', [ModuleController::class, 'store'])->name('store');
        Route::get('/analytics', [ModuleController::class, 'analytics'])->name('analytics');
        Route::get('/{module}', [ModuleController::class, 'show'])->name('show');
        Route::get('/{module}/edit', [ModuleController::class, 'edit'])->name('edit');
        Route::put('/{module}', [ModuleController::class, 'update'])->name('update');
        Route::patch('/{module}/toggle', [ModuleController::class, 'toggleStatus'])->name('toggle');
        Route::post('/{module}/force-install', [ModuleController::class, 'forceInstall'])->name('force-install');
        Route::post('/{module}/force-uninstall', [ModuleController::class, 'forceUninstall'])->name('force-uninstall');
        Route::post('/bulk-action', [ModuleController::class, 'bulkAction'])->name('bulk-action');
    });
});

// Stop Impersonation Route (accessible from regular user panel)
Route::post('/stop-impersonation', [TenantController::class, 'stopImpersonation'])
    ->middleware('auth')
    ->name('super-admin.stop-impersonation');