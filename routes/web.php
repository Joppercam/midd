<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Core Module Controllers
use App\Modules\Core\Controllers\DashboardController;
use App\Modules\Core\Controllers\UserController as CoreUserController;
use App\Modules\Core\Controllers\RoleController as CoreRoleController;
use App\Modules\Core\Controllers\BackupController as CoreBackupController;
use App\Modules\Core\Controllers\AuditController as CoreAuditController;
use App\Modules\Core\Controllers\NotificationController as CoreNotificationController;
use App\Modules\Core\Controllers\CompanySettingsController as CoreCompanySettingsController;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth', 'verified', '2fa'])->group(function () {
    // Core Module Routes
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/admin', [DashboardController::class, 'admin'])->name('dashboard.admin');
    Route::get('/dashboard/gerente', [DashboardController::class, 'gerente'])->name('dashboard.gerente');
    Route::get('/dashboard/contador', [DashboardController::class, 'contador'])->name('dashboard.contador');
    Route::get('/dashboard/vendedor', [DashboardController::class, 'vendedor'])->name('dashboard.vendedor');
    
    // Users Management
    Route::resource('users', CoreUserController::class);
    Route::post('/users/{user}/impersonate', [CoreUserController::class, 'impersonate'])->name('users.impersonate');
    Route::post('/users/stop-impersonating', [CoreUserController::class, 'stopImpersonating'])->name('users.stop-impersonating');
    Route::get('/users/{user}/permissions', [CoreUserController::class, 'permissions'])->name('users.permissions');
    Route::post('/users/{user}/permissions', [CoreUserController::class, 'updatePermissions'])->name('users.permissions.update');
    Route::get('/users/{user}/activity', [CoreUserController::class, 'activity'])->name('users.activity');
    
    // Roles Management
    Route::resource('roles', CoreRoleController::class);
    Route::post('/roles/{role}/duplicate', [CoreRoleController::class, 'duplicate'])->name('roles.duplicate');
    
    // Real-time Notifications
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/demo', function () {
            return Inertia::render('Notifications/Demo');
        })->name('demo');
        Route::post('/test', [App\Http\Controllers\RealTimeNotificationController::class, 'sendTestNotification'])->name('test');
        Route::post('/tenant', [App\Http\Controllers\RealTimeNotificationController::class, 'sendTenantNotification'])->name('tenant');
        Route::post('/simulate/invoice', [App\Http\Controllers\RealTimeNotificationController::class, 'simulateInvoiceNotification'])->name('simulate.invoice');
        Route::post('/simulate/payment', [App\Http\Controllers\RealTimeNotificationController::class, 'simulatePaymentNotification'])->name('simulate.payment');
        Route::post('/simulate/low-stock', [App\Http\Controllers\RealTimeNotificationController::class, 'simulateLowStockAlert'])->name('simulate.low-stock');
        Route::post('/simulate/bank-reconciliation', [App\Http\Controllers\RealTimeNotificationController::class, 'simulateBankReconciliation'])->name('simulate.bank-reconciliation');
        Route::post('/simulate/system-alert', [App\Http\Controllers\RealTimeNotificationController::class, 'simulateSystemAlert'])->name('simulate.system-alert');
        Route::get('/config', [App\Http\Controllers\RealTimeNotificationController::class, 'getConfiguration'])->name('config');
    });
    
    // Facturación
    Route::resource('invoices', App\Http\Controllers\Billing\InvoiceController::class);
    Route::post('/invoices/{invoice}/send', [App\Http\Controllers\Billing\InvoiceController::class, 'send'])->name('invoices.send');
    Route::get('/invoices/{invoice}/download', [App\Http\Controllers\Billing\InvoiceController::class, 'download'])->name('invoices.download');
    
    // SII Integration
    Route::prefix('sii')->name('sii.')->group(function () {
        Route::get('/configuration', [App\Http\Controllers\SII\SIIController::class, 'configuration'])->name('configuration');
        Route::put('/configuration', [App\Http\Controllers\SII\SIIController::class, 'updateConfiguration'])->name('configuration.update');
        Route::post('/certificate/upload', [App\Http\Controllers\SII\SIIController::class, 'uploadCertificate'])->name('certificate.upload');
        Route::post('/send/{taxDocument}', [App\Http\Controllers\SII\SIIController::class, 'send'])->name('send');
        Route::post('/batch-send', [App\Http\Controllers\SII\SIIController::class, 'batchSend'])->name('batch-send');
        Route::get('/status/{taxDocument}', [App\Http\Controllers\SII\SIIController::class, 'checkStatus'])->name('check-status');
        Route::post('/test-connection', [App\Http\Controllers\SII\SIIController::class, 'testConnection'])->name('test-connection');
    });
    
    
    
    // Cotizaciones
    Route::resource('quotes', App\Http\Controllers\Quotes\QuoteController::class);
    Route::post('quotes/{quote}/send', [App\Http\Controllers\Quotes\QuoteController::class, 'send'])->name('quotes.send');
    Route::post('quotes/{quote}/approve', [App\Http\Controllers\Quotes\QuoteController::class, 'approve'])->name('quotes.approve');
    Route::post('quotes/{quote}/reject', [App\Http\Controllers\Quotes\QuoteController::class, 'reject'])->name('quotes.reject');
    Route::post('quotes/{quote}/convert', [App\Http\Controllers\Quotes\QuoteController::class, 'convert'])->name('quotes.convert');
    Route::get('quotes/{quote}/download', [App\Http\Controllers\Quotes\QuoteController::class, 'download'])->name('quotes.download');
    
    // Tax Books (Libro de Compras y Ventas)
    Route::prefix('tax-books')->name('tax-books.')->group(function () {
        Route::get('/', [App\Http\Controllers\TaxBooks\TaxBookController::class, 'index'])->name('index');
        
        // Sales Book
        Route::prefix('sales')->name('sales.')->group(function () {
            Route::post('/generate', [App\Http\Controllers\TaxBooks\TaxBookController::class, 'generateSales'])->name('generate');
            Route::get('/{salesBook}', [App\Http\Controllers\TaxBooks\TaxBookController::class, 'showSales'])->name('show');
            Route::post('/{salesBook}/finalize', [App\Http\Controllers\TaxBooks\TaxBookController::class, 'finalizeSales'])->name('finalize');
            Route::get('/{salesBook}/export/excel', [App\Http\Controllers\TaxBooks\TaxBookController::class, 'exportSalesExcel'])->name('export.excel');
            Route::get('/{salesBook}/export/pdf', [App\Http\Controllers\TaxBooks\TaxBookController::class, 'exportSalesPdf'])->name('export.pdf');
        });
        
        // Purchase Book
        Route::prefix('purchase')->name('purchase.')->group(function () {
            Route::post('/generate', [App\Http\Controllers\TaxBooks\TaxBookController::class, 'generatePurchase'])->name('generate');
            Route::get('/{purchaseBook}', [App\Http\Controllers\TaxBooks\TaxBookController::class, 'showPurchase'])->name('show');
            Route::post('/{purchaseBook}/finalize', [App\Http\Controllers\TaxBooks\TaxBookController::class, 'finalizePurchase'])->name('finalize');
            Route::get('/{purchaseBook}/export/excel', [App\Http\Controllers\TaxBooks\TaxBookController::class, 'exportPurchaseExcel'])->name('export.excel');
            Route::get('/{purchaseBook}/export/pdf', [App\Http\Controllers\TaxBooks\TaxBookController::class, 'exportPurchasePdf'])->name('export.pdf');
        });
    });
    
    
    // Reportes
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [App\Http\Controllers\Reports\ReportController::class, 'index'])->name('index');
        Route::get('/sales', [App\Http\Controllers\Reports\ReportController::class, 'sales'])->name('sales');
        Route::get('/taxes', [App\Http\Controllers\Reports\ReportController::class, 'taxes'])->name('taxes');
        Route::get('/inventory', [App\Http\Controllers\Reports\ReportController::class, 'inventory'])->name('inventory');
        Route::get('/customer-balance', [App\Http\Controllers\Reports\ReportController::class, 'customerBalance'])->name('customer-balance');
    });
    
    // Exportaciones Contables
    Route::prefix('exports/accounting')->name('exports.accounting.')->group(function () {
        Route::get('/', [App\Http\Controllers\Exports\AccountingExportController::class, 'index'])->name('index');
        Route::post('/preview', [App\Http\Controllers\Exports\AccountingExportController::class, 'preview'])->name('preview');
        Route::post('/statistics', [App\Http\Controllers\Exports\AccountingExportController::class, 'statistics'])->name('statistics');
        Route::get('/history', [App\Http\Controllers\Exports\AccountingExportController::class, 'history'])->name('history');
        Route::get('/download/{filename}', [App\Http\Controllers\Exports\AccountingExportController::class, 'download'])->name('download');
        
        // Formatos específicos
        Route::post('/contpaq', [App\Http\Controllers\Exports\AccountingExportController::class, 'exportContpaq'])->name('contpaq');
        Route::post('/monica', [App\Http\Controllers\Exports\AccountingExportController::class, 'exportMonica'])->name('monica');
        Route::post('/tango', [App\Http\Controllers\Exports\AccountingExportController::class, 'exportTango'])->name('tango');
        Route::post('/sii', [App\Http\Controllers\Exports\AccountingExportController::class, 'exportSII'])->name('sii');
    });
    
    // Company Settings
    Route::prefix('company-settings')->name('company-settings.')->group(function () {
        Route::get('/', [CoreCompanySettingsController::class, 'index'])->name('index');
        Route::post('/basic', [CoreCompanySettingsController::class, 'updateBasicInfo'])->name('update.basic');
        Route::post('/address', [CoreCompanySettingsController::class, 'updateAddress'])->name('update.address');
        Route::post('/fiscal', [CoreCompanySettingsController::class, 'updateFiscalInfo'])->name('update.fiscal');
        Route::post('/logo', [CoreCompanySettingsController::class, 'updateLogo'])->name('update.logo');
        Route::delete('/logo', [CoreCompanySettingsController::class, 'removeLogo'])->name('remove.logo');
        Route::post('/branding', [CoreCompanySettingsController::class, 'updateBranding'])->name('update.branding');
        Route::post('/invoice-settings', [CoreCompanySettingsController::class, 'updateInvoiceSettings'])->name('update.invoice');
        Route::post('/email-settings', [CoreCompanySettingsController::class, 'updateEmailSettings'])->name('update.email');
        Route::post('/regional-settings', [CoreCompanySettingsController::class, 'updateRegionalSettings'])->name('update.regional');
    });
    
    // Backups
    Route::prefix('backups')->name('backups.')->group(function () {
        Route::get('/', [CoreBackupController::class, 'index'])->name('index');
        Route::post('/create', [CoreBackupController::class, 'create'])->name('create');
        Route::get('/{backup}/download', [CoreBackupController::class, 'download'])->name('download');
        Route::post('/{backup}/restore', [CoreBackupController::class, 'restore'])->name('restore');
        Route::delete('/{backup}', [CoreBackupController::class, 'destroy'])->name('destroy');
        Route::get('/schedules', [CoreBackupController::class, 'schedules'])->name('schedules');
        Route::post('/schedules', [CoreBackupController::class, 'schedule'])->name('schedule');
        Route::put('/schedules/{schedule}', [CoreBackupController::class, 'updateSchedule'])->name('schedule.update');
        Route::delete('/schedules/{schedule}', [CoreBackupController::class, 'deleteSchedule'])->name('schedule.delete');
    });
    
    // Audit Logs
    Route::prefix('audit')->name('audit.')->group(function () {
        Route::get('/', [CoreAuditController::class, 'index'])->name('index');
        Route::get('/export', [CoreAuditController::class, 'export'])->name('export');
        Route::get('/settings', [CoreAuditController::class, 'settings'])->name('settings');
        Route::post('/settings', [CoreAuditController::class, 'updateSettings'])->name('settings.update');
        Route::get('/report', [CoreAuditController::class, 'report'])->name('report');
    });
    
    // Notifications
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [CoreNotificationController::class, 'index'])->name('index');
        Route::post('/{notification}/read', [CoreNotificationController::class, 'markAsRead'])->name('read');
        Route::post('/read-all', [CoreNotificationController::class, 'markAllAsRead'])->name('read-all');
        Route::delete('/{notification}', [CoreNotificationController::class, 'destroy'])->name('destroy');
        Route::get('/preferences', [CoreNotificationController::class, 'preferences'])->name('preferences');
        Route::post('/preferences', [CoreNotificationController::class, 'updatePreferences'])->name('preferences.update');
        
        // Push notifications API
        Route::get('/offline', [CoreNotificationController::class, 'getOfflineNotifications'])->name('offline');
        Route::post('/mark-read/{notificationId}', [CoreNotificationController::class, 'markAsRead'])->name('mark-read');
        Route::post('/mark-all-read', [CoreNotificationController::class, 'markAllAsRead'])->name('mark-all-read');
        Route::delete('/clear-all', [CoreNotificationController::class, 'clearAll'])->name('clear-all');
        Route::post('/test', [CoreNotificationController::class, 'sendTest'])->name('test');
        Route::post('/send-to-user', [CoreNotificationController::class, 'sendToUser'])->name('send-to-user');
        Route::post('/send-to-tenant', [CoreNotificationController::class, 'sendToTenant'])->name('send-to-tenant');
        Route::post('/send-to-role', [CoreNotificationController::class, 'sendToRole'])->name('send-to-role');
        Route::get('/stats', [CoreNotificationController::class, 'statistics'])->name('stats');
        Route::get('/connection-status', [CoreNotificationController::class, 'connectionStatus'])->name('connection-status');
        Route::post('/settings', [CoreNotificationController::class, 'updateSettings'])->name('settings');
    });
    
    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [App\Http\Controllers\ReportController::class, 'index'])->name('index');
        Route::get('/statistics', [App\Http\Controllers\ReportController::class, 'statistics'])->name('statistics');
        Route::post('/cleanup', [App\Http\Controllers\ReportController::class, 'cleanup'])->name('cleanup');
        
        // Templates
        Route::get('/templates', [App\Http\Controllers\ReportController::class, 'templates'])->name('templates');
        Route::get('/templates/{template}', [App\Http\Controllers\ReportController::class, 'showTemplate'])->name('templates.show');
        Route::post('/templates/{template}/generate', [App\Http\Controllers\ReportController::class, 'generate'])->name('templates.generate');
        
        // Scheduled Reports
        Route::prefix('scheduled')->name('scheduled.')->group(function () {
            Route::get('/', [App\Http\Controllers\ReportController::class, 'scheduled'])->name('index');
            Route::get('/create', [App\Http\Controllers\ReportController::class, 'createScheduled'])->name('create');
            Route::post('/', [App\Http\Controllers\ReportController::class, 'storeScheduled'])->name('store');
            Route::get('/{scheduledReport}', [App\Http\Controllers\ReportController::class, 'showScheduled'])->name('show');
            Route::get('/{scheduledReport}/edit', [App\Http\Controllers\ReportController::class, 'editScheduled'])->name('edit');
            Route::put('/{scheduledReport}', [App\Http\Controllers\ReportController::class, 'updateScheduled'])->name('update');
            Route::delete('/{scheduledReport}', [App\Http\Controllers\ReportController::class, 'destroyScheduled'])->name('destroy');
            Route::post('/{scheduledReport}/run', [App\Http\Controllers\ReportController::class, 'runScheduled'])->name('run');
            Route::post('/{scheduledReport}/toggle', [App\Http\Controllers\ReportController::class, 'toggleScheduled'])->name('toggle');
        });
        
        // Report Executions
        Route::prefix('executions')->name('executions.')->group(function () {
            Route::get('/', [App\Http\Controllers\ReportController::class, 'executions'])->name('index');
            Route::get('/{execution}', [App\Http\Controllers\ReportController::class, 'showExecution'])->name('show');
            Route::get('/{execution}/download', [App\Http\Controllers\ReportController::class, 'downloadExecution'])->name('download');
            Route::delete('/{execution}', [App\Http\Controllers\ReportController::class, 'destroyExecution'])->name('destroy');
        });
    });
    
    // Notificaciones por Email
    Route::prefix('emails')->name('emails.')->group(function () {
        Route::get('/', [App\Http\Controllers\Emails\EmailNotificationController::class, 'index'])->name('index');
        Route::get('/{notification}', [App\Http\Controllers\Emails\EmailNotificationController::class, 'show'])->name('show');
        Route::post('/send-invoice/{invoice}', [App\Http\Controllers\Emails\EmailNotificationController::class, 'sendInvoice'])->name('send-invoice');
        Route::post('/send-reminder/{invoice}', [App\Http\Controllers\Emails\EmailNotificationController::class, 'sendPaymentReminder'])->name('send-reminder');
        Route::post('/send-overdue-reminders', [App\Http\Controllers\Emails\EmailNotificationController::class, 'sendOverdueReminders'])->name('send-overdue-reminders');
        Route::post('/{notification}/resend', [App\Http\Controllers\Emails\EmailNotificationController::class, 'resend'])->name('resend');
        Route::post('/{notification}/mark-as-read', [App\Http\Controllers\Emails\EmailNotificationController::class, 'markAsRead'])->name('mark-as-read');
        Route::get('/{notification}/track-open', [App\Http\Controllers\Emails\EmailNotificationController::class, 'trackOpen'])->name('track-open');
    });

});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

// Admin routes
require __DIR__.'/admin.php';

// Super Admin routes
require __DIR__.'/super-admin.php';

// SII routes
require __DIR__.'/sii.php';
