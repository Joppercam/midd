<?php

use Illuminate\Support\Facades\Route;
use App\Modules\CRM\Controllers\CustomerController;

/*
|--------------------------------------------------------------------------
| CRM Module Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'check.subscription', 'check.module:crm'])->group(function () {
    
    // Customer Management Routes
    Route::prefix('crm/customers')->name('crm.customers.')->group(function () {
        Route::get('/', [CustomerController::class, 'index'])->name('index');
        Route::get('/create', [CustomerController::class, 'create'])->name('create');
        Route::post('/', [CustomerController::class, 'store'])->name('store');
        Route::get('/{customer}', [CustomerController::class, 'show'])->name('show');
        Route::get('/{customer}/edit', [CustomerController::class, 'edit'])->name('edit');
        Route::put('/{customer}', [CustomerController::class, 'update'])->name('update');
        Route::delete('/{customer}', [CustomerController::class, 'destroy'])->name('destroy');
        
        // Customer Statement
        Route::get('/{customer}/statement', [CustomerController::class, 'statement'])->name('statement');
        
        // Customer Export/Import
        Route::get('/export', [CustomerController::class, 'export'])->name('export');
        Route::post('/import', [CustomerController::class, 'import'])->name('import');
        
        // Customer Operations
        Route::post('/merge', [CustomerController::class, 'merge'])->name('merge');
        Route::put('/{customer}/credit-limit', [CustomerController::class, 'updateCreditLimit'])->name('credit-limit.update');
        Route::put('/{customer}/payment-terms', [CustomerController::class, 'updatePaymentTerms'])->name('payment-terms.update');
        
        // Customer Communication
        Route::post('/{customer}/notes', [CustomerController::class, 'addNote'])->name('notes.store');
        Route::get('/{customer}/history', [CustomerController::class, 'getContactHistory'])->name('history');
    });
    
    // CRM Dashboard
    Route::get('/crm/dashboard', function () {
        return inertia('CRM/Dashboard');
    })->name('crm.dashboard');
    
    // Opportunities Routes (placeholder for future implementation)
    Route::prefix('crm/opportunities')->name('crm.opportunities.')->group(function () {
        Route::get('/', function () {
            return inertia('CRM/Opportunities/Index');
        })->name('index');
        Route::get('/create', function () {
            return inertia('CRM/Opportunities/Create');
        })->name('create');
    });
    
    // Leads Routes (placeholder for future implementation)  
    Route::prefix('crm/leads')->name('crm.leads.')->group(function () {
        Route::get('/', function () {
            return inertia('CRM/Leads/Index');
        })->name('index');
        Route::get('/create', function () {
            return inertia('CRM/Leads/Create');
        })->name('create');
    });
    
    // CRM Reports Routes (placeholder for future implementation)
    Route::prefix('crm/reports')->name('crm.reports.')->group(function () {
        Route::get('/', function () {
            return inertia('CRM/Reports/Index');
        })->name('index');
        Route::get('/sales-pipeline', function () {
            return inertia('CRM/Reports/SalesPipeline');
        })->name('sales-pipeline');
        Route::get('/customer-analytics', function () {
            return inertia('CRM/Reports/CustomerAnalytics');
        })->name('customer-analytics');
    });
});