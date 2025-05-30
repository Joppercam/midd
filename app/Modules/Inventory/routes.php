<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Inventory\Controllers\ProductController;
use App\Modules\Inventory\Controllers\SupplierController;
use App\Modules\Inventory\Controllers\PurchaseOrderController;

/*
|--------------------------------------------------------------------------
| Inventory Module Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'check.subscription', 'check.module:inventory'])->group(function () {
    
    // Product Management Routes
    Route::prefix('inventory/products')->name('inventory.products.')->group(function () {
        Route::get('/', [ProductController::class, 'index'])->name('index');
        Route::get('/create', [ProductController::class, 'create'])->name('create');
        Route::post('/', [ProductController::class, 'store'])->name('store');
        Route::get('/{product}', [ProductController::class, 'show'])->name('show');
        Route::get('/{product}/edit', [ProductController::class, 'edit'])->name('edit');
        Route::put('/{product}', [ProductController::class, 'update'])->name('update');
        Route::delete('/{product}', [ProductController::class, 'destroy'])->name('destroy');
        
        // Stock Management
        Route::post('/{product}/update-stock', [ProductController::class, 'updateStock'])->name('update-stock');
        Route::put('/{product}/update-prices', [ProductController::class, 'updatePrices'])->name('update-prices');
        Route::get('/{product}/movement-history', [ProductController::class, 'getMovementHistory'])->name('movement-history');
        Route::get('/{product}/barcode', [ProductController::class, 'barcode'])->name('barcode');
        
        // Bulk Operations
        Route::post('/import', [ProductController::class, 'import'])->name('import');
        Route::get('/export', [ProductController::class, 'export'])->name('export');
        Route::post('/barcode-labels', [ProductController::class, 'barcodeLabels'])->name('barcode-labels');
        Route::post('/update-minimum-stock', [ProductController::class, 'updateMinimumStock'])->name('update-minimum-stock');
    });
    
    // Supplier Management Routes
    Route::prefix('inventory/suppliers')->name('inventory.suppliers.')->group(function () {
        Route::get('/', [SupplierController::class, 'index'])->name('index');
        Route::get('/create', [SupplierController::class, 'create'])->name('create');
        Route::post('/', [SupplierController::class, 'store'])->name('store');
        Route::get('/{supplier}', [SupplierController::class, 'show'])->name('show');
        Route::get('/{supplier}/edit', [SupplierController::class, 'edit'])->name('edit');
        Route::put('/{supplier}', [SupplierController::class, 'update'])->name('update');
        Route::delete('/{supplier}', [SupplierController::class, 'destroy'])->name('destroy');
        
        // Supplier Operations
        Route::get('/{supplier}/statement', [SupplierController::class, 'statement'])->name('statement');
        Route::post('/{supplier}/evaluate', [SupplierController::class, 'evaluate'])->name('evaluate');
        Route::put('/{supplier}/payment-terms', [SupplierController::class, 'updatePaymentTerms'])->name('payment-terms.update');
        Route::get('/{supplier}/price-lists', [SupplierController::class, 'priceLists'])->name('price-lists');
        Route::post('/{supplier}/price-lists', [SupplierController::class, 'uploadPriceList'])->name('price-lists.upload');
        Route::get('/{supplier}/performance', [SupplierController::class, 'getPerformanceMetrics'])->name('performance');
        
        // Supplier Tools
        Route::post('/compare-prices', [SupplierController::class, 'comparePrices'])->name('compare-prices');
        Route::get('/export', [SupplierController::class, 'export'])->name('export');
    });
    
    // Purchase Order Routes
    Route::prefix('inventory/purchase-orders')->name('inventory.purchase-orders.')->group(function () {
        Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');
        Route::get('/create', [PurchaseOrderController::class, 'create'])->name('create');
        Route::post('/', [PurchaseOrderController::class, 'store'])->name('store');
        Route::get('/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('show');
        Route::get('/{purchaseOrder}/edit', [PurchaseOrderController::class, 'edit'])->name('edit');
        Route::put('/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('update');
        Route::delete('/{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])->name('destroy');
        
        // Purchase Order Actions
        Route::post('/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->name('approve');
        Route::post('/{purchaseOrder}/reject', [PurchaseOrderController::class, 'reject'])->name('reject');
        Route::post('/{purchaseOrder}/send', [PurchaseOrderController::class, 'send'])->name('send');
        Route::post('/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->name('cancel');
        Route::post('/{purchaseOrder}/duplicate', [PurchaseOrderController::class, 'duplicate'])->name('duplicate');
        
        // Receiving
        Route::get('/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('receive');
        Route::post('/{purchaseOrder}/receive', [PurchaseOrderController::class, 'processReceipt'])->name('receive.process');
        
        // Reports and Tools
        Route::get('/{purchaseOrder}/pdf', [PurchaseOrderController::class, 'pdf'])->name('pdf');
        Route::get('/{purchaseOrder}/approval-history', [PurchaseOrderController::class, 'getApprovalHistory'])->name('approval-history');
        Route::post('/compare-quotes', [PurchaseOrderController::class, 'compareQuotes'])->name('compare-quotes');
        Route::get('/export', [PurchaseOrderController::class, 'export'])->name('export');
    });
    
    // Inventory Reports
    Route::prefix('inventory/reports')->name('inventory.reports.')->group(function () {
        Route::get('/', [ProductController::class, 'inventoryReport'])->name('index');
        Route::get('/stock-alerts', [ProductController::class, 'stockAlerts'])->name('stock-alerts');
        Route::get('/valuation', function () {
            return inertia('Inventory/Reports/Valuation');
        })->name('valuation');
        Route::get('/movements', function () {
            return inertia('Inventory/Reports/Movements');
        })->name('movements');
        Route::get('/aging', function () {
            return inertia('Inventory/Reports/Aging');
        })->name('aging');
        Route::get('/turnover', function () {
            return inertia('Inventory/Reports/Turnover');
        })->name('turnover');
    });
    
    // Inventory Dashboard
    Route::get('/inventory/dashboard', function () {
        return inertia('Inventory/Dashboard');
    })->name('inventory.dashboard');
    
    // Warehouse Management (placeholder for future)
    Route::prefix('inventory/warehouses')->name('inventory.warehouses.')->group(function () {
        Route::get('/', function () {
            return inertia('Inventory/Warehouses/Index');
        })->name('index');
    });
});