<?php

use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
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
    
    // Rutas temporales para las otras secciones
    Route::get('/products', fn() => Inertia::render('ComingSoon', ['section' => 'Inventario']))->name('products.index');
    Route::get('/customers', fn() => Inertia::render('ComingSoon', ['section' => 'Clientes']))->name('customers.index');
    Route::get('/reports', fn() => Inertia::render('ComingSoon', ['section' => 'Reportes']))->name('reports.index');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
