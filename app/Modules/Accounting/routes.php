<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Accounting\Controllers\ExpenseController;
use App\Modules\Accounting\Controllers\ChartOfAccountsController;
use App\Modules\Accounting\Controllers\JournalEntryController;
use App\Modules\Accounting\Controllers\FinancialReportController;
use App\Modules\Accounting\Controllers\AccountingExportController;
use App\Modules\Accounting\Controllers\BudgetController;
use App\Modules\Accounting\Controllers\TaxManagementController;

// Expenses
Route::prefix('expenses')->name('expenses.')->group(function () {
    Route::get('/', [ExpenseController::class, 'index'])->name('index');
    Route::get('/create', [ExpenseController::class, 'create'])->name('create');
    Route::post('/', [ExpenseController::class, 'store'])->name('store');
    Route::get('/{expense}', [ExpenseController::class, 'show'])->name('show');
    Route::get('/{expense}/edit', [ExpenseController::class, 'edit'])->name('edit');
    Route::put('/{expense}', [ExpenseController::class, 'update'])->name('update');
    Route::delete('/{expense}', [ExpenseController::class, 'destroy'])->name('destroy');
    
    // Expense specific actions
    Route::post('/{expense}/approve', [ExpenseController::class, 'approve'])->name('approve');
    Route::post('/{expense}/reject', [ExpenseController::class, 'reject'])->name('reject');
    Route::post('/{expense}/mark-as-paid', [ExpenseController::class, 'markAsPaid'])->name('mark-as-paid');
    Route::post('/{expense}/register-payment', [ExpenseController::class, 'registerPayment'])->name('register-payment');
    Route::post('/{expense}/duplicate', [ExpenseController::class, 'duplicate'])->name('duplicate');
    Route::post('/{expense}/attach-file', [ExpenseController::class, 'attachFile'])->name('attach-file');
    Route::delete('/{expense}/file/{fileId}', [ExpenseController::class, 'removeFile'])->name('remove-file');
    
    // Bulk operations
    Route::post('/bulk-approve', [ExpenseController::class, 'bulkApprove'])->name('bulk-approve');
    Route::post('/bulk-reject', [ExpenseController::class, 'bulkReject'])->name('bulk-reject');
    Route::post('/bulk-export', [ExpenseController::class, 'bulkExport'])->name('bulk-export');
    
    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ExpenseController::class, 'reports'])->name('index');
        Route::get('/by-category', [ExpenseController::class, 'reportByCategory'])->name('by-category');
        Route::get('/by-supplier', [ExpenseController::class, 'reportBySupplier'])->name('by-supplier');
        Route::get('/by-period', [ExpenseController::class, 'reportByPeriod'])->name('by-period');
        Route::get('/pending-approval', [ExpenseController::class, 'reportPendingApproval'])->name('pending-approval');
    });
});

// Chart of Accounts
Route::prefix('chart-of-accounts')->name('chart-of-accounts.')->group(function () {
    Route::get('/', [ChartOfAccountsController::class, 'index'])->name('index');
    Route::get('/create', [ChartOfAccountsController::class, 'create'])->name('create');
    Route::post('/', [ChartOfAccountsController::class, 'store'])->name('store');
    Route::get('/{account}', [ChartOfAccountsController::class, 'show'])->name('show');
    Route::get('/{account}/edit', [ChartOfAccountsController::class, 'edit'])->name('edit');
    Route::put('/{account}', [ChartOfAccountsController::class, 'update'])->name('update');
    Route::delete('/{account}', [ChartOfAccountsController::class, 'destroy'])->name('destroy');
    
    // Chart management
    Route::post('/import', [ChartOfAccountsController::class, 'import'])->name('import');
    Route::get('/export', [ChartOfAccountsController::class, 'export'])->name('export');
    Route::post('/initialize-default', [ChartOfAccountsController::class, 'initializeDefault'])->name('initialize-default');
    Route::post('/reorder', [ChartOfAccountsController::class, 'reorder'])->name('reorder');
    Route::get('/{account}/balance', [ChartOfAccountsController::class, 'getBalance'])->name('balance');
    Route::get('/{account}/transactions', [ChartOfAccountsController::class, 'getTransactions'])->name('transactions');
});

// Journal Entries
Route::prefix('journal-entries')->name('journal-entries.')->group(function () {
    Route::get('/', [JournalEntryController::class, 'index'])->name('index');
    Route::get('/create', [JournalEntryController::class, 'create'])->name('create');
    Route::post('/', [JournalEntryController::class, 'store'])->name('store');
    Route::get('/{journalEntry}', [JournalEntryController::class, 'show'])->name('show');
    Route::get('/{journalEntry}/edit', [JournalEntryController::class, 'edit'])->name('edit');
    Route::put('/{journalEntry}', [JournalEntryController::class, 'update'])->name('update');
    Route::delete('/{journalEntry}', [JournalEntryController::class, 'destroy'])->name('destroy');
    
    // Journal entry actions
    Route::post('/{journalEntry}/post', [JournalEntryController::class, 'post'])->name('post');
    Route::post('/{journalEntry}/reverse', [JournalEntryController::class, 'reverse'])->name('reverse');
    Route::post('/{journalEntry}/approve', [JournalEntryController::class, 'approve'])->name('approve');
    Route::post('/{journalEntry}/reject', [JournalEntryController::class, 'reject'])->name('reject');
    Route::get('/{journalEntry}/print', [JournalEntryController::class, 'print'])->name('print');
    
    // Templates and recurring entries
    Route::prefix('templates')->name('templates.')->group(function () {
        Route::get('/', [JournalEntryController::class, 'templates'])->name('index');
        Route::post('/{journalEntry}/save-as-template', [JournalEntryController::class, 'saveAsTemplate'])->name('save');
        Route::post('/create-from-template/{template}', [JournalEntryController::class, 'createFromTemplate'])->name('create-from');
    });
});

// Financial Reports
Route::prefix('reports')->name('reports.')->group(function () {
    Route::get('/', [FinancialReportController::class, 'index'])->name('index');
    
    // Standard reports
    Route::get('/balance-sheet', [FinancialReportController::class, 'balanceSheet'])->name('balance-sheet');
    Route::get('/income-statement', [FinancialReportController::class, 'incomeStatement'])->name('income-statement');
    Route::get('/cash-flow', [FinancialReportController::class, 'cashFlow'])->name('cash-flow');
    Route::get('/trial-balance', [FinancialReportController::class, 'trialBalance'])->name('trial-balance');
    Route::get('/general-ledger', [FinancialReportController::class, 'generalLedger'])->name('general-ledger');
    Route::get('/aged-receivables', [FinancialReportController::class, 'agedReceivables'])->name('aged-receivables');
    Route::get('/aged-payables', [FinancialReportController::class, 'agedPayables'])->name('aged-payables');
    
    // Custom reports
    Route::prefix('custom')->name('custom.')->group(function () {
        Route::get('/', [FinancialReportController::class, 'customReports'])->name('index');
        Route::post('/generate', [FinancialReportController::class, 'generateCustomReport'])->name('generate');
        Route::post('/save', [FinancialReportController::class, 'saveCustomReport'])->name('save');
    });
    
    // Report exports
    Route::post('/{reportType}/export', [FinancialReportController::class, 'exportReport'])->name('export');
    Route::get('/scheduled', [FinancialReportController::class, 'scheduledReports'])->name('scheduled');
    Route::post('/schedule', [FinancialReportController::class, 'scheduleReport'])->name('schedule');
});

// Accounting Exports
Route::prefix('exports')->name('exports.')->group(function () {
    Route::get('/', [AccountingExportController::class, 'index'])->name('index');
    Route::post('/preview', [AccountingExportController::class, 'preview'])->name('preview');
    Route::post('/generate', [AccountingExportController::class, 'generate'])->name('generate');
    Route::get('/download/{export}', [AccountingExportController::class, 'download'])->name('download');
    Route::get('/history', [AccountingExportController::class, 'history'])->name('history');
    Route::delete('/{export}', [AccountingExportController::class, 'destroy'])->name('destroy');
    
    // Format specific exports
    Route::post('/contpaq', [AccountingExportController::class, 'exportContpaq'])->name('contpaq');
    Route::post('/monica', [AccountingExportController::class, 'exportMonica'])->name('monica');
    Route::post('/tango', [AccountingExportController::class, 'exportTango'])->name('tango');
    Route::post('/sii', [AccountingExportController::class, 'exportSII'])->name('sii');
    Route::post('/excel', [AccountingExportController::class, 'exportExcel'])->name('excel');
});

// Budget Management
Route::prefix('budgets')->name('budgets.')->group(function () {
    Route::get('/', [BudgetController::class, 'index'])->name('index');
    Route::get('/create', [BudgetController::class, 'create'])->name('create');
    Route::post('/', [BudgetController::class, 'store'])->name('store');
    Route::get('/{budget}', [BudgetController::class, 'show'])->name('show');
    Route::get('/{budget}/edit', [BudgetController::class, 'edit'])->name('edit');
    Route::put('/{budget}', [BudgetController::class, 'update'])->name('update');
    Route::delete('/{budget}', [BudgetController::class, 'destroy'])->name('destroy');
    
    // Budget actions
    Route::post('/{budget}/approve', [BudgetController::class, 'approve'])->name('approve');
    Route::post('/{budget}/activate', [BudgetController::class, 'activate'])->name('activate');
    Route::post('/{budget}/copy', [BudgetController::class, 'copy'])->name('copy');
    Route::get('/{budget}/variance-analysis', [BudgetController::class, 'varianceAnalysis'])->name('variance-analysis');
    
    // Budget reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/variance', [BudgetController::class, 'varianceReport'])->name('variance');
        Route::get('/performance', [BudgetController::class, 'performanceReport'])->name('performance');
        Route::get('/forecasting', [BudgetController::class, 'forecastingReport'])->name('forecasting');
    });
});

// Tax Management
Route::prefix('tax-management')->name('tax-management.')->group(function () {
    Route::get('/', [TaxManagementController::class, 'index'])->name('index');
    Route::get('/configuration', [TaxManagementController::class, 'configuration'])->name('configuration');
    Route::post('/configuration', [TaxManagementController::class, 'updateConfiguration'])->name('configuration.update');
    
    // Tax calculations
    Route::get('/calculate', [TaxManagementController::class, 'calculate'])->name('calculate');
    Route::post('/calculate/period', [TaxManagementController::class, 'calculatePeriod'])->name('calculate.period');
    Route::get('/withholdings', [TaxManagementController::class, 'withholdings'])->name('withholdings');
    
    // Tax reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/vat-summary', [TaxManagementController::class, 'vatSummary'])->name('vat-summary');
        Route::get('/withholding-summary', [TaxManagementController::class, 'withholdingSummary'])->name('withholding-summary');
        Route::get('/tax-liability', [TaxManagementController::class, 'taxLiability'])->name('tax-liability');
    });
});