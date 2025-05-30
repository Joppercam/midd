<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Core\Controllers\BackupController;
use App\Modules\Core\Controllers\AuditController;
use App\Modules\Core\Controllers\NotificationController;
use App\Modules\Core\Controllers\CompanySettingsController;
use App\Modules\Core\Controllers\DashboardController;
use App\Modules\Core\Controllers\UserController;
use App\Modules\Core\Controllers\RoleController;

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/admin', [DashboardController::class, 'admin'])->name('dashboard.admin');
    Route::get('/dashboard/gerente', [DashboardController::class, 'gerente'])->name('dashboard.gerente');
    Route::get('/dashboard/contador', [DashboardController::class, 'contador'])->name('dashboard.contador');
    Route::get('/dashboard/vendedor', [DashboardController::class, 'vendedor'])->name('dashboard.vendedor');
    
    // Company Settings
    Route::prefix('company-settings')->name('company-settings.')->group(function () {
        Route::get('/', [CompanySettingsController::class, 'index'])->name('index');
        Route::post('/basic', [CompanySettingsController::class, 'updateBasicInfo'])->name('update.basic');
        Route::post('/address', [CompanySettingsController::class, 'updateAddress'])->name('update.address');
        Route::post('/fiscal', [CompanySettingsController::class, 'updateFiscalInfo'])->name('update.fiscal');
        Route::post('/logo', [CompanySettingsController::class, 'updateLogo'])->name('update.logo');
        Route::delete('/logo', [CompanySettingsController::class, 'removeLogo'])->name('remove.logo');
        Route::post('/branding', [CompanySettingsController::class, 'updateBranding'])->name('update.branding');
        Route::post('/invoice-settings', [CompanySettingsController::class, 'updateInvoiceSettings'])->name('update.invoice');
        Route::post('/email-settings', [CompanySettingsController::class, 'updateEmailSettings'])->name('update.email');
        Route::post('/regional-settings', [CompanySettingsController::class, 'updateRegionalSettings'])->name('update.regional');
    });
    
    // Users Management
    Route::resource('users', UserController::class);
    Route::post('/users/{user}/impersonate', [UserController::class, 'impersonate'])->name('users.impersonate');
    Route::post('/users/stop-impersonating', [UserController::class, 'stopImpersonating'])->name('users.stop-impersonating');
    Route::get('/users/{user}/permissions', [UserController::class, 'permissions'])->name('users.permissions');
    Route::post('/users/{user}/permissions', [UserController::class, 'updatePermissions'])->name('users.permissions.update');
    Route::get('/users/{user}/activity', [UserController::class, 'activity'])->name('users.activity');
    
    // Roles Management
    Route::resource('roles', RoleController::class);
    Route::post('/roles/{role}/duplicate', [RoleController::class, 'duplicate'])->name('roles.duplicate');
    
    // Backups
    Route::prefix('backups')->name('backups.')->group(function () {
        Route::get('/', [BackupController::class, 'index'])->name('index');
        Route::post('/create', [BackupController::class, 'create'])->name('create');
        Route::get('/{backup}/download', [BackupController::class, 'download'])->name('download');
        Route::post('/{backup}/restore', [BackupController::class, 'restore'])->name('restore');
        Route::delete('/{backup}', [BackupController::class, 'destroy'])->name('destroy');
        Route::get('/schedules', [BackupController::class, 'schedules'])->name('schedules');
        Route::post('/schedules', [BackupController::class, 'schedule'])->name('schedule');
        Route::put('/schedules/{schedule}', [BackupController::class, 'updateSchedule'])->name('schedule.update');
        Route::delete('/schedules/{schedule}', [BackupController::class, 'deleteSchedule'])->name('schedule.delete');
    });
    
    // Audit Logs
    Route::prefix('audit')->name('audit.')->group(function () {
        Route::get('/', [AuditController::class, 'index'])->name('index');
        Route::get('/export', [AuditController::class, 'export'])->name('export');
        Route::get('/settings', [AuditController::class, 'settings'])->name('settings');
        Route::post('/settings', [AuditController::class, 'updateSettings'])->name('settings.update');
        Route::get('/report', [AuditController::class, 'report'])->name('report');
    });
    
    // Notifications
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('/{notification}/read', [NotificationController::class, 'markAsRead'])->name('read');
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->name('read-all');
        Route::delete('/{notification}', [NotificationController::class, 'destroy'])->name('destroy');
        Route::get('/preferences', [NotificationController::class, 'preferences'])->name('preferences');
        Route::post('/preferences', [NotificationController::class, 'updatePreferences'])->name('preferences.update');
    });
});