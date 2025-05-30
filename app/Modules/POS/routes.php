<?php

use App\Modules\POS\Controllers\POSController;
use App\Modules\POS\Controllers\CashSessionController;
use App\Modules\POS\Controllers\TerminalController;
use Illuminate\Support\Facades\Route;

// Rutas principales del POS
Route::get('/', [POSController::class, 'index'])->name('index');
Route::get('/dashboard', [POSController::class, 'dashboard'])->name('dashboard');
Route::get('/sale', [POSController::class, 'sale'])->name('sale');
Route::get('/training', [POSController::class, 'trainingMode'])->name('training');

// Procesamiento de ventas
Route::post('/sales', [POSController::class, 'processeSale'])->name('sales.process');
Route::post('/sales/{sale}/void', [POSController::class, 'voidSale'])->name('sales.void');
Route::post('/sales/{sale}/refund', [POSController::class, 'refund'])->name('sales.refund');

// Búsqueda de productos y clientes
Route::get('/search/products', [POSController::class, 'searchProducts'])->name('search.products');
Route::get('/search/product-by-code', [POSController::class, 'getProductByCode'])->name('search.product-by-code');
Route::get('/search/customers', [POSController::class, 'getCustomer'])->name('search.customers');

// Descuentos y promociones
Route::post('/discounts/apply', [POSController::class, 'applyDiscount'])->name('discounts.apply');

// Impresión y recibos
Route::get('/sales/{sale}/receipt', [POSController::class, 'printReceipt'])->name('sales.receipt');
Route::post('/sales/{sale}/email-receipt', [POSController::class, 'emailReceipt'])->name('sales.email-receipt');

// Configuración
Route::get('/settings', [POSController::class, 'getSettings'])->name('settings.index');
Route::put('/settings', [POSController::class, 'updateSettings'])->name('settings.update');

// Cajón de dinero
Route::post('/cash-drawer/open', [POSController::class, 'openDrawer'])->name('cash-drawer.open');

// Reportes
Route::get('/reports', [POSController::class, 'reports'])->name('reports.index');
Route::post('/reports/generate', [POSController::class, 'generateReport'])->name('reports.generate');

// === SESIONES DE CAJA ===
Route::prefix('cash-sessions')->name('cash-sessions.')->group(function () {
    Route::get('/', [CashSessionController::class, 'index'])->name('index');
    Route::get('/create', [CashSessionController::class, 'create'])->name('create');
    Route::post('/', [CashSessionController::class, 'store'])->name('store');
    Route::get('/{session}', [CashSessionController::class, 'show'])->name('show');
    Route::post('/{session}/close', [CashSessionController::class, 'close'])->name('close');
    Route::post('/{session}/suspend', [CashSessionController::class, 'suspend'])->name('suspend');
    Route::post('/{session}/resume', [CashSessionController::class, 'resume'])->name('resume');
    Route::get('/history', [CashSessionController::class, 'history'])->name('history');
    
    // Movimientos de caja
    Route::post('/{session}/movements', [CashSessionController::class, 'addCashMovement'])->name('movements.add');
    Route::get('/{session}/balance', [CashSessionController::class, 'getCurrentBalance'])->name('balance');
    Route::post('/{session}/count', [CashSessionController::class, 'count'])->name('count');
    
    // Transferencias
    Route::post('/transfer', [CashSessionController::class, 'transfer'])->name('transfer');
    
    // Reportes y exportación
    Route::get('/{session}/report', [CashSessionController::class, 'printReport'])->name('report');
    Route::post('/export', [CashSessionController::class, 'export'])->name('export');
});

// === TERMINALES ===
Route::prefix('terminals')->name('terminals.')->group(function () {
    Route::get('/', [TerminalController::class, 'index'])->name('index');
    Route::get('/create', [TerminalController::class, 'create'])->name('create');
    Route::post('/', [TerminalController::class, 'store'])->name('store');
    Route::get('/{terminal}', [TerminalController::class, 'show'])->name('show');
    Route::get('/{terminal}/edit', [TerminalController::class, 'edit'])->name('edit');
    Route::put('/{terminal}', [TerminalController::class, 'update'])->name('update');
    Route::delete('/{terminal}', [TerminalController::class, 'destroy'])->name('destroy');
    
    // Gestión de estado
    Route::post('/{terminal}/activate', [TerminalController::class, 'activate'])->name('activate');
    Route::post('/{terminal}/deactivate', [TerminalController::class, 'deactivate'])->name('deactivate');
    Route::post('/{terminal}/restart', [TerminalController::class, 'restartTerminal'])->name('restart');
    Route::get('/{terminal}/status', [TerminalController::class, 'getStatus'])->name('status');
    
    // Asignación de usuarios
    Route::post('/{terminal}/assign', [TerminalController::class, 'assign'])->name('assign');
    Route::post('/{terminal}/unassign', [TerminalController::class, 'unassign'])->name('unassign');
    
    // Configuración y mantenimiento
    Route::put('/{terminal}/settings', [TerminalController::class, 'updateSettings'])->name('settings.update');
    Route::get('/{terminal}/maintenance', [TerminalController::class, 'maintenance'])->name('maintenance');
    Route::post('/{terminal}/clear-cache', [TerminalController::class, 'clearCache'])->name('clear-cache');
    
    // Pruebas de hardware
    Route::post('/{terminal}/test-printer', [TerminalController::class, 'testPrinter'])->name('test-printer');
    
    // Respaldo y restauración
    Route::post('/{terminal}/backup', [TerminalController::class, 'backup'])->name('backup');
    Route::post('/{terminal}/restore', [TerminalController::class, 'restore'])->name('restore');
});