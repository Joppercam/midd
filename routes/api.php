<?php

use App\Http\Controllers\Api\V1\CustomerApiController;
use App\Http\Controllers\Api\V1\ProductApiController;
use App\Http\Controllers\Api\V1\InvoiceApiController;
use App\Http\Controllers\Api\V1\PaymentApiController;
use App\Http\Controllers\Api\V1\ExpenseApiController;
use App\Http\Controllers\Api\V1\SupplierApiController;
use App\Http\Controllers\Api\V1\BankReconciliationApiController;
use App\Http\Controllers\Api\V1\AuditApiController;
use App\Http\Controllers\Api\V1\AuthApiController;
use App\Http\Controllers\Api\V1\DashboardMetricsApiController;
use App\Http\Controllers\Api\Mobile\MobileApiController;
use App\Http\Controllers\Api\ApiDocumentationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// API Documentation (public)
Route::get('/info', [ApiDocumentationController::class, 'apiInfo'])->name('api.info');

// Health check (public)
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'version' => config('app.version', '1.0.0')
    ]);
})->name('api.health');

// API v1 Routes (require authentication)
Route::prefix('v1')->middleware(['api.auth'])->group(function () {
    
    // Customers
    Route::apiResource('customers', CustomerApiController::class)->names([
        'index' => 'api.customers.index',
        'store' => 'api.customers.store',
        'show' => 'api.customers.show',
        'update' => 'api.customers.update',
        'destroy' => 'api.customers.destroy'
    ]);
    Route::get('customers/{id}/balance', [CustomerApiController::class, 'balance'])
        ->name('api.customers.balance');
    Route::get('customers/{id}/transactions', [CustomerApiController::class, 'transactions'])
        ->name('api.customers.transactions');
    
    // Products
    Route::apiResource('products', ProductApiController::class)->names([
        'index' => 'api.products.index',
        'store' => 'api.products.store',
        'show' => 'api.products.show',
        'update' => 'api.products.update',
        'destroy' => 'api.products.destroy'
    ]);
    Route::post('products/{id}/stock', [ProductApiController::class, 'updateStock'])
        ->name('api.products.stock');
    Route::get('products/inventory/report', [ProductApiController::class, 'inventoryReport'])
        ->name('api.products.inventory');
    
    // Invoices/Tax Documents
    Route::apiResource('invoices', InvoiceApiController::class)->names([
        'index' => 'api.invoices.index',
        'store' => 'api.invoices.store',
        'show' => 'api.invoices.show',
        'update' => 'api.invoices.update',
        'destroy' => 'api.invoices.destroy'
    ]);
    Route::post('invoices/{id}/send', [InvoiceApiController::class, 'send'])
        ->name('api.invoices.send');
    Route::get('invoices/{id}/pdf', [InvoiceApiController::class, 'pdf'])
        ->name('api.invoices.pdf');
    Route::post('invoices/{id}/payment', [InvoiceApiController::class, 'recordPayment'])
        ->name('api.invoices.payment');
    
    // Payments
    Route::apiResource('payments', PaymentApiController::class)->names([
        'index' => 'api.payments.index',
        'store' => 'api.payments.store',
        'show' => 'api.payments.show',
        'update' => 'api.payments.update',
        'destroy' => 'api.payments.destroy'
    ]);
    Route::post('payments/{id}/allocate', [PaymentApiController::class, 'allocate'])
        ->name('api.payments.allocate');
    Route::get('payments/statistics', [PaymentApiController::class, 'statistics'])
        ->name('api.payments.statistics');
    
    // Expenses
    Route::apiResource('expenses', ExpenseApiController::class)->names([
        'index' => 'api.expenses.index',
        'store' => 'api.expenses.store',
        'show' => 'api.expenses.show',
        'update' => 'api.expenses.update',
        'destroy' => 'api.expenses.destroy'
    ]);
    Route::post('expenses/{id}/mark-paid', [ExpenseApiController::class, 'markAsPaid'])
        ->name('api.expenses.mark_paid');
    Route::get('expenses/categories', [ExpenseApiController::class, 'categories'])
        ->name('api.expenses.categories');
    Route::get('expenses/statistics', [ExpenseApiController::class, 'statistics'])
        ->name('api.expenses.statistics');
    
    // Suppliers
    Route::apiResource('suppliers', SupplierApiController::class)->names([
        'index' => 'api.suppliers.index',
        'store' => 'api.suppliers.store',
        'show' => 'api.suppliers.show',
        'update' => 'api.suppliers.update',
        'destroy' => 'api.suppliers.destroy'
    ]);
    Route::get('suppliers/statistics', [SupplierApiController::class, 'stats'])
        ->name('api.suppliers.stats');
    
    // Dashboard Metrics (Real-time)
    Route::prefix('dashboard')->group(function () {
        Route::get('metrics', [DashboardMetricsApiController::class, 'index'])
            ->name('api.dashboard.metrics');
        Route::get('metrics/{metric}', [DashboardMetricsApiController::class, 'show'])
            ->name('api.dashboard.metric');
        Route::get('charts', [DashboardMetricsApiController::class, 'charts'])
            ->name('api.dashboard.charts');
    });

    // Reports - TODO: Implement ReportApiController
    /*
    Route::prefix('reports')->group(function () {
        Route::get('sales', [ReportApiController::class, 'sales'])
            ->name('api.reports.sales');
        Route::get('taxes', [ReportApiController::class, 'taxes'])
            ->name('api.reports.taxes');
        Route::get('inventory', [ReportApiController::class, 'inventory'])
            ->name('api.reports.inventory');
        Route::get('customer-balance', [ReportApiController::class, 'customerBalance'])
            ->name('api.reports.customer_balance');
    });
    */
    
    // Bank Reconciliation
    Route::prefix('bank-reconciliation')->group(function () {
        Route::get('accounts', [BankReconciliationApiController::class, 'accounts'])
            ->name('api.bank_reconciliation.accounts');
        Route::post('upload-statement', [BankReconciliationApiController::class, 'uploadStatement'])
            ->name('api.bank_reconciliation.upload');
        Route::get('transactions', [BankReconciliationApiController::class, 'transactions'])
            ->name('api.bank_reconciliation.transactions');
        Route::post('auto-match', [BankReconciliationApiController::class, 'autoMatch'])
            ->name('api.bank_reconciliation.auto_match');
        Route::post('create-match', [BankReconciliationApiController::class, 'createMatch'])
            ->name('api.bank_reconciliation.create_match');
        Route::post('reconcile', [BankReconciliationApiController::class, 'reconcile'])
            ->name('api.bank_reconciliation.reconcile');
        Route::get('statistics', [BankReconciliationApiController::class, 'stats'])
            ->name('api.bank_reconciliation.stats');
    });
    
    // Audit
    Route::prefix('audit')->group(function () {
        Route::get('logs', [AuditApiController::class, 'logs'])
            ->name('api.audit.logs');
        Route::get('logs/{auditLog}', [AuditApiController::class, 'show'])
            ->name('api.audit.show');
        Route::get('settings', [AuditApiController::class, 'settings'])
            ->name('api.audit.settings');
        Route::put('settings', [AuditApiController::class, 'updateSettings'])
            ->name('api.audit.update_settings');
        Route::get('statistics', [AuditApiController::class, 'stats'])
            ->name('api.audit.stats');
        Route::post('cleanup', [AuditApiController::class, 'cleanup'])
            ->name('api.audit.cleanup');
        Route::post('export', [AuditApiController::class, 'export'])
            ->name('api.audit.export');
    });
    
    // Backups - TODO: Implement BackupApiController
    /*
    Route::prefix('backups')->group(function () {
        Route::get('/', [BackupApiController::class, 'index'])
            ->name('api.backups.index');
        Route::post('/', [BackupApiController::class, 'create'])
            ->name('api.backups.create');
        Route::get('{id}', [BackupApiController::class, 'show'])
            ->name('api.backups.show');
        Route::delete('{id}', [BackupApiController::class, 'destroy'])
            ->name('api.backups.destroy');
        Route::post('{id}/restore', [BackupApiController::class, 'restore'])
            ->name('api.backups.restore');
        Route::get('{id}/download', [BackupApiController::class, 'download'])
            ->name('api.backups.download');
    });
    */
    
    // Webhooks - TODO: Implement WebhookApiController
    /*
    Route::prefix('webhooks')->group(function () {
        Route::get('/', [WebhookApiController::class, 'index'])
            ->name('api.webhooks.index');
        Route::post('/', [WebhookApiController::class, 'store'])
            ->name('api.webhooks.store');
        Route::get('{id}', [WebhookApiController::class, 'show'])
            ->name('api.webhooks.show');
        Route::put('{id}', [WebhookApiController::class, 'update'])
            ->name('api.webhooks.update');
        Route::delete('{id}', [WebhookApiController::class, 'destroy'])
            ->name('api.webhooks.destroy');
        Route::post('{id}/test', [WebhookApiController::class, 'test'])
            ->name('api.webhooks.test');
    });
    */
    
    // Account/User Management - TODO: Implement AccountApiController
    /*
    Route::prefix('account')->group(function () {
        Route::get('profile', [AccountApiController::class, 'profile'])
            ->name('api.account.profile');
        Route::put('profile', [AccountApiController::class, 'updateProfile'])
            ->name('api.account.update_profile');
        Route::get('tokens', [AccountApiController::class, 'tokens'])
            ->name('api.account.tokens');
        Route::post('tokens', [AccountApiController::class, 'createToken'])
            ->name('api.account.create_token');
        Route::delete('tokens/{id}', [AccountApiController::class, 'revokeToken'])
            ->name('api.account.revoke_token');
        Route::get('usage', [AccountApiController::class, 'usage'])
            ->name('api.account.usage');
    });
    */
    
    // System endpoints (admin only) - TODO: Implement SystemApiController
    /*
    Route::prefix('system')->middleware(['api.admin'])->group(function () {
        Route::get('stats', [SystemApiController::class, 'stats'])
            ->name('api.system.stats');
        Route::get('logs', [SystemApiController::class, 'logs'])
            ->name('api.system.logs');
        Route::post('maintenance', [SystemApiController::class, 'maintenance'])
            ->name('api.system.maintenance');
    });
    */
});

// Mobile API Routes (require mobile auth)
Route::prefix('mobile')->middleware(['api.auth'])->group(function () {
    Route::get('dashboard', [MobileApiController::class, 'dashboard'])
        ->name('api.mobile.dashboard');
    Route::post('invoice/quick', [MobileApiController::class, 'createQuickInvoice'])
        ->name('api.mobile.invoice.quick');
    Route::post('payment/register', [MobileApiController::class, 'registerPayment'])
        ->name('api.mobile.payment.register');
    Route::get('customers/search', [MobileApiController::class, 'searchCustomers'])
        ->name('api.mobile.customers.search');
    Route::get('products/search', [MobileApiController::class, 'searchProducts'])
        ->name('api.mobile.products.search');
    Route::post('sync/upload', [MobileApiController::class, 'syncUpload'])
        ->name('api.mobile.sync.upload');
    Route::get('sync/download', [MobileApiController::class, 'syncDownload'])
        ->name('api.mobile.sync.download');
    Route::post('notifications/register', [MobileApiController::class, 'registerDevice'])
        ->name('api.mobile.notifications.register');
    Route::get('config', [MobileApiController::class, 'getAppConfig'])
        ->name('api.mobile.config');
});

// Webhook receivers (public, but with verification) - TODO: Implement WebhookReceiverController
/*
Route::prefix('webhooks/receive')->group(function () {
    Route::post('sii', [WebhookReceiverController::class, 'sii'])
        ->name('webhooks.receive.sii');
    Route::post('bank/{provider}', [WebhookReceiverController::class, 'bank'])
        ->name('webhooks.receive.bank');
    Route::post('payment/{provider}', [WebhookReceiverController::class, 'payment'])
        ->name('webhooks.receive.payment');
});
*/

// Authentication endpoints (public, rate limited)
Route::middleware(['throttle:api'])->prefix('v1/auth')->group(function () {
    Route::post('token', [AuthApiController::class, 'createToken'])
        ->name('api.auth.token');
    Route::post('refresh', [AuthApiController::class, 'refreshToken'])
        ->middleware(['api.auth'])
        ->name('api.auth.refresh');
    Route::post('revoke', [AuthApiController::class, 'revokeToken'])
        ->middleware(['api.auth'])
        ->name('api.auth.revoke');
    Route::post('revoke-all', [AuthApiController::class, 'revokeAllTokens'])
        ->middleware(['api.auth'])
        ->name('api.auth.revoke_all');
    Route::get('token-info', [AuthApiController::class, 'tokenInfo'])
        ->middleware(['api.auth'])
        ->name('api.auth.token_info');
    Route::get('validate', [AuthApiController::class, 'validateToken'])
        ->middleware(['api.auth'])
        ->name('api.auth.validate');
});