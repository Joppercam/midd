<?php

use App\Http\Controllers\Admin\ModuleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| Rutas para administración del sistema
|
*/

Route::middleware(['auth', 'role:super-admin|admin'])->prefix('admin')->name('admin.')->group(function () {
    
    // Gestión de módulos
    Route::controller(ModuleController::class)->prefix('modules')->name('modules.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{module}', 'show')->name('show');
        Route::post('/', 'store')->name('store');
        Route::put('/{module}', 'update')->name('update');
        
        // Gestión de módulos por tenant
        Route::get('/tenants/{tenant}/modules', 'getTenantModules')->name('tenant.modules');
        Route::post('/tenants/{tenant}/modules', 'updateTenantModules')->name('tenant.modules.update');
        
        // Solicitudes de módulos
        Route::post('/requests/{request}/approve', 'approveModuleRequest')->name('requests.approve');
        Route::post('/requests/{request}/reject', 'rejectModuleRequest')->name('requests.reject');
        
        // APIs para estadísticas
        Route::get('/api/usage-stats', 'getUsageStats')->name('api.usage_stats');
        Route::get('/api/recommendations/{tenant}', 'getRecommendations')->name('api.recommendations');
    });
    
    // Gestión de planes de suscripción (futuro)
    /*
    Route::resource('subscription-plans', SubscriptionPlanController::class);
    Route::resource('module-permissions', ModulePermissionController::class);
    Route::resource('integrations', IntegrationController::class);
    */
    
});