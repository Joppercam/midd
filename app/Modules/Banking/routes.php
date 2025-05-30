<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Banking\Controllers\BankAccountController;
use App\Modules\Banking\Controllers\BankTransactionController;
use App\Modules\Banking\Controllers\BankReconciliationController;
use App\Modules\Banking\Controllers\BankReportController;

Route::middleware(['auth', 'verified'])->prefix('banking')->name('banking.')->group(function () {
    // Dashboard
    Route::get('/', [BankReconciliationController::class, 'index'])->name('index');
    
    // Bank Accounts
    Route::prefix('accounts')->name('accounts.')->group(function () {
        Route::get('/', [BankAccountController::class, 'index'])->name('index');
        Route::get('/create', [BankAccountController::class, 'create'])->name('create');
        Route::post('/', [BankAccountController::class, 'store'])->name('store');
        Route::get('/{bankAccount}', [BankAccountController::class, 'show'])->name('show');
        Route::get('/{bankAccount}/edit', [BankAccountController::class, 'edit'])->name('edit');
        Route::put('/{bankAccount}', [BankAccountController::class, 'update'])->name('update');
        Route::delete('/{bankAccount}', [BankAccountController::class, 'destroy'])->name('destroy');
        Route::post('/{bankAccount}/toggle', [BankAccountController::class, 'toggle'])->name('toggle');
    });
    
    // Bank Transactions
    Route::prefix('transactions')->name('transactions.')->group(function () {
        Route::get('/', [BankTransactionController::class, 'index'])->name('index');
        Route::get('/account/{bankAccount}', [BankTransactionController::class, 'byAccount'])->name('by-account');
        Route::post('/import/{bankAccount}', [BankTransactionController::class, 'import'])->name('import');
        Route::get('/import-preview', [BankTransactionController::class, 'importPreview'])->name('import-preview');
        Route::post('/import-confirm', [BankTransactionController::class, 'importConfirm'])->name('import-confirm');
        Route::get('/{transaction}', [BankTransactionController::class, 'show'])->name('show');
        Route::put('/{transaction}', [BankTransactionController::class, 'update'])->name('update');
        Route::delete('/{transaction}', [BankTransactionController::class, 'destroy'])->name('destroy');
        Route::post('/{transaction}/categorize', [BankTransactionController::class, 'categorize'])->name('categorize');
        Route::get('/{transaction}/suggestions', [BankTransactionController::class, 'getSuggestions'])->name('suggestions');
        Route::post('/{transaction}/match', [BankTransactionController::class, 'match'])->name('match');
        Route::post('/{transaction}/unmatch', [BankTransactionController::class, 'unmatch'])->name('unmatch');
        Route::post('/{transaction}/ignore', [BankTransactionController::class, 'ignore'])->name('ignore');
    });
    
    // Reconciliation
    Route::prefix('reconcile')->name('reconcile.')->group(function () {
        Route::get('/', [BankReconciliationController::class, 'reconcileIndex'])->name('index');
        Route::post('/start/{bankAccount}', [BankReconciliationController::class, 'startReconciliation'])->name('start');
        Route::get('/{reconciliation}', [BankReconciliationController::class, 'reconcile'])->name('show');
        Route::post('/{reconciliation}/auto', [BankReconciliationController::class, 'autoMatch'])->name('auto');
        Route::post('/{reconciliation}/complete', [BankReconciliationController::class, 'completeReconciliation'])->name('complete');
        Route::post('/{reconciliation}/approve', [BankReconciliationController::class, 'approveReconciliation'])->name('approve');
        Route::post('/{reconciliation}/reopen', [BankReconciliationController::class, 'reopenReconciliation'])->name('reopen');
        Route::post('/{reconciliation}/adjustment', [BankReconciliationController::class, 'addAdjustment'])->name('adjustment.add');
        Route::delete('/{reconciliation}/adjustment/{index}', [BankReconciliationController::class, 'removeAdjustment'])->name('adjustment.remove');
    });
    
    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [BankReportController::class, 'index'])->name('index');
        Route::get('/reconciliation/{reconciliation}', [BankReportController::class, 'reconciliationReport'])->name('reconciliation');
        Route::get('/monthly', [BankReportController::class, 'monthlyReport'])->name('monthly');
        Route::get('/account/{bankAccount}', [BankReportController::class, 'accountReport'])->name('account');
        Route::get('/cash-flow', [BankReportController::class, 'cashFlowReport'])->name('cash-flow');
        
        // Export routes
        Route::get('/reconciliation/{reconciliation}/export/pdf', [BankReportController::class, 'exportReconciliationPdf'])->name('reconciliation.export.pdf');
        Route::get('/reconciliation/{reconciliation}/export/excel', [BankReportController::class, 'exportReconciliationExcel'])->name('reconciliation.export.excel');
        Route::get('/monthly/export/pdf', [BankReportController::class, 'exportMonthlyPdf'])->name('monthly.export.pdf');
        Route::get('/monthly/export/excel', [BankReportController::class, 'exportMonthlyExcel'])->name('monthly.export.excel');
    });
});