<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Invoicing\Controllers\PaymentController;
use App\Modules\Invoicing\Controllers\InvoiceController;
use App\Modules\Invoicing\Controllers\SIIController;
use App\Modules\Invoicing\Controllers\CertificateController;
use App\Modules\Invoicing\Controllers\SchemaController;
use App\Modules\Invoicing\Controllers\BillingController;

// Payments
Route::prefix('payments')->name('payments.')->group(function () {
    Route::get('/', [PaymentController::class, 'index'])->name('index');
    Route::get('/create', [PaymentController::class, 'create'])->name('create');
    Route::post('/', [PaymentController::class, 'store'])->name('store');
    Route::get('/{payment}', [PaymentController::class, 'show'])->name('show');
    Route::get('/{payment}/edit', [PaymentController::class, 'edit'])->name('edit');
    Route::put('/{payment}', [PaymentController::class, 'update'])->name('update');
    Route::delete('/{payment}', [PaymentController::class, 'destroy'])->name('destroy');
    
    // Payment specific actions
    Route::post('/{payment}/allocate', [PaymentController::class, 'allocate'])->name('allocate');
    Route::delete('/{payment}/allocation/{allocation}', [PaymentController::class, 'removeAllocation'])->name('allocation.remove');
    Route::get('/customers/{customer}/unpaid-documents', [PaymentController::class, 'getUnpaidDocuments'])->name('unpaid-documents');
    Route::post('/{payment}/void', [PaymentController::class, 'void'])->name('void');
    Route::get('/export', [PaymentController::class, 'export'])->name('export');
});

// Tax Documents (Invoices)
Route::prefix('invoices')->name('invoices.')->group(function () {
    Route::get('/', [InvoiceController::class, 'index'])->name('index');
    Route::get('/create', [InvoiceController::class, 'create'])->name('create');
    Route::post('/', [InvoiceController::class, 'store'])->name('store');
    Route::get('/{invoice}', [InvoiceController::class, 'show'])->name('show');
    Route::get('/{invoice}/edit', [InvoiceController::class, 'edit'])->name('edit');
    Route::put('/{invoice}', [InvoiceController::class, 'update'])->name('update');
    Route::delete('/{invoice}', [InvoiceController::class, 'destroy'])->name('destroy');
    
    // Invoice specific actions
    Route::post('/{invoice}/send', [InvoiceController::class, 'send'])->name('send');
    Route::post('/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('cancel');
    Route::get('/{invoice}/download', [InvoiceController::class, 'download'])->name('download');
    Route::get('/{invoice}/print', [InvoiceController::class, 'print'])->name('print');
    Route::post('/{invoice}/email', [InvoiceController::class, 'email'])->name('email');
    Route::post('/{invoice}/duplicate', [InvoiceController::class, 'duplicate'])->name('duplicate');
    
    // SII specific actions
    Route::post('/{invoice}/sii/send', [InvoiceController::class, 'sendToSII'])->name('sii.send');
    Route::get('/{invoice}/sii/status', [InvoiceController::class, 'getSIIStatus'])->name('sii.status');
    Route::post('/{invoice}/sii/cancel', [InvoiceController::class, 'cancelInSII'])->name('sii.cancel');
});

// SII Integration
Route::prefix('sii')->name('sii.')->group(function () {
    // Configuration
    Route::get('/configuration', [SIIController::class, 'configuration'])->name('configuration');
    Route::post('/configuration', [SIIController::class, 'updateConfiguration'])->name('configuration.update');
    Route::post('/test-connection', [SIIController::class, 'testConnection'])->name('test-connection');
    
    // Environment Management
    Route::get('/environments', [SIIController::class, 'environments'])->name('environments');
    Route::post('/environment/switch', [SIIController::class, 'switchEnvironment'])->name('environment.switch');
    
    // Document Management
    Route::get('/documents', [SIIController::class, 'documents'])->name('documents');
    Route::get('/documents/{document}/status', [SIIController::class, 'getDocumentStatus'])->name('documents.status');
    Route::post('/documents/bulk-send', [SIIController::class, 'bulkSend'])->name('documents.bulk-send');
    Route::post('/documents/bulk-status', [SIIController::class, 'bulkStatus'])->name('documents.bulk-status');
    
    // Folio Management
    Route::get('/folios', [SIIController::class, 'folios'])->name('folios');
    Route::post('/folios/request', [SIIController::class, 'requestFolios'])->name('folios.request');
    Route::get('/folios/download/{type}', [SIIController::class, 'downloadFolios'])->name('folios.download');
    
    // Certificate Management
    Route::prefix('certificate')->name('certificate.')->group(function () {
        Route::get('/', [CertificateController::class, 'index'])->name('index');
        Route::post('/upload', [CertificateController::class, 'upload'])->name('upload');
        Route::delete('/', [CertificateController::class, 'destroy'])->name('destroy');
        Route::post('/test', [CertificateController::class, 'test'])->name('test');
        Route::get('/info', [CertificateController::class, 'info'])->name('info');
        Route::post('/renew', [CertificateController::class, 'renew'])->name('renew');
    });
    
    // Schema Management
    Route::prefix('schemas')->name('schemas.')->group(function () {
        Route::get('/', [SchemaController::class, 'index'])->name('index');
        Route::post('/download', [SchemaController::class, 'download'])->name('download');
        Route::post('/validate', [SchemaController::class, 'validate'])->name('validate');
        Route::post('/update', [SchemaController::class, 'update'])->name('update');
        Route::delete('/{schema}', [SchemaController::class, 'destroy'])->name('destroy');
    });
    
    // Reports and Logs
    Route::get('/logs', [SIIController::class, 'logs'])->name('logs');
    Route::get('/stats', [SIIController::class, 'stats'])->name('stats');
    Route::get('/reports/summary', [SIIController::class, 'summaryReport'])->name('reports.summary');
});

// Billing and Reports
Route::prefix('billing')->name('billing.')->group(function () {
    // Customer Statements
    Route::get('/statements', [BillingController::class, 'statements'])->name('statements');
    Route::get('/statements/{customer}', [BillingController::class, 'customerStatement'])->name('statements.customer');
    Route::post('/statements/{customer}/send', [BillingController::class, 'sendStatement'])->name('statements.send');
    Route::get('/statements/{customer}/download', [BillingController::class, 'downloadStatement'])->name('statements.download');
    
    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [BillingController::class, 'reports'])->name('index');
        Route::get('/revenue', [BillingController::class, 'revenueReport'])->name('revenue');
        Route::get('/aging', [BillingController::class, 'agingReport'])->name('aging');
        Route::get('/tax-summary', [BillingController::class, 'taxSummaryReport'])->name('tax-summary');
        Route::get('/payment-summary', [BillingController::class, 'paymentSummaryReport'])->name('payment-summary');
        Route::post('/custom', [BillingController::class, 'customReport'])->name('custom');
    });
    
    // Exports
    Route::prefix('exports')->name('exports.')->group(function () {
        Route::post('/invoices', [BillingController::class, 'exportInvoices'])->name('invoices');
        Route::post('/payments', [BillingController::class, 'exportPayments'])->name('payments');
        Route::post('/aging', [BillingController::class, 'exportAging'])->name('aging');
        Route::post('/tax-report', [BillingController::class, 'exportTaxReport'])->name('tax-report');
    });
    
    // Bulk Operations
    Route::prefix('bulk')->name('bulk.')->group(function () {
        Route::post('/send-invoices', [BillingController::class, 'bulkSendInvoices'])->name('send-invoices');
        Route::post('/send-reminders', [BillingController::class, 'bulkSendReminders'])->name('send-reminders');
        Route::post('/cancel-invoices', [BillingController::class, 'bulkCancelInvoices'])->name('cancel-invoices');
    });
});