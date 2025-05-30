<?php

use App\Modules\POS\Controllers\Api\POSApiController;
use App\Modules\POS\Controllers\Api\SyncApiController;
use Illuminate\Support\Facades\Route;

// API para aplicaciones móviles/tablets
Route::prefix('v1')->group(function () {
    // Autenticación
    Route::post('/login', [POSApiController::class, 'login']);
    Route::post('/logout', [POSApiController::class, 'logout']);
    
    Route::middleware(['auth:sanctum'])->group(function () {
        // Terminal y caja
        Route::get('/terminals', [POSApiController::class, 'terminals']);
        Route::post('/terminal/select', [POSApiController::class, 'selectTerminal']);
        Route::get('/cash-session/current', [POSApiController::class, 'currentCashSession']);
        Route::post('/cash-session/open', [POSApiController::class, 'openCashSession']);
        Route::post('/cash-session/close', [POSApiController::class, 'closeCashSession']);
        
        // Productos
        Route::get('/products', [POSApiController::class, 'products']);
        Route::get('/products/search', [POSApiController::class, 'searchProducts']);
        Route::get('/products/barcode/{barcode}', [POSApiController::class, 'findByBarcode']);
        Route::get('/products/quick', [POSApiController::class, 'quickProducts']);
        Route::get('/categories', [POSApiController::class, 'categories']);
        
        // Clientes
        Route::get('/customers/search', [POSApiController::class, 'searchCustomers']);
        Route::post('/customers/quick', [POSApiController::class, 'createQuickCustomer']);
        Route::get('/customers/{customer}/loyalty', [POSApiController::class, 'customerLoyalty']);
        
        // Carrito y venta
        Route::post('/cart/add', [POSApiController::class, 'addToCart']);
        Route::put('/cart/update', [POSApiController::class, 'updateCart']);
        Route::delete('/cart/clear', [POSApiController::class, 'clearCart']);
        Route::post('/sale/process', [POSApiController::class, 'processSale']);
        Route::post('/sale/calculate', [POSApiController::class, 'calculateTotals']);
        
        // Transacciones
        Route::get('/transactions', [POSApiController::class, 'transactions']);
        Route::get('/transactions/{transaction}', [POSApiController::class, 'transactionDetail']);
        Route::post('/transactions/{transaction}/void', [POSApiController::class, 'voidTransaction']);
        Route::post('/transactions/{transaction}/refund', [POSApiController::class, 'refundTransaction']);
        Route::get('/transactions/{transaction}/receipt', [POSApiController::class, 'receipt']);
        
        // Métodos de pago y descuentos
        Route::get('/payment-methods', [POSApiController::class, 'paymentMethods']);
        Route::get('/discounts', [POSApiController::class, 'availableDiscounts']);
        Route::post('/discounts/validate', [POSApiController::class, 'validateDiscount']);
        
        // Reportes
        Route::get('/reports/daily', [POSApiController::class, 'dailyReport']);
        Route::get('/reports/cash-session', [POSApiController::class, 'cashSessionReport']);
        
        // Configuración
        Route::get('/config', [POSApiController::class, 'configuration']);
        Route::get('/user/permissions', [POSApiController::class, 'userPermissions']);
    });
});

// Sincronización offline
Route::prefix('sync')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/check', [SyncApiController::class, 'checkPendingSync']);
    Route::post('/transactions', [SyncApiController::class, 'syncTransactions']);
    Route::post('/cash-movements', [SyncApiController::class, 'syncCashMovements']);
    Route::get('/status', [SyncApiController::class, 'syncStatus']);
    Route::post('/download', [SyncApiController::class, 'downloadData']);
});