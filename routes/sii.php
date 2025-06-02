<?php

use App\Http\Controllers\SIIController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', '2fa'])->prefix('sii')->name('sii.')->group(function () {
    // Configuration
    Route::get('/configuration', [SIIController::class, 'configuration'])->name('configuration');
    Route::post('/certificate', [SIIController::class, 'uploadCertificate'])->name('certificate.upload');
    Route::post('/environment', [SIIController::class, 'toggleEnvironment'])->name('environment.toggle');
    Route::post('/certification/complete', [SIIController::class, 'completeCertification'])->name('certification.complete');
    
    // Document operations
    Route::post('/documents/{document}/send', [SIIController::class, 'sendDocument'])->name('documents.send');
    Route::post('/documents/{document}/check-status', [SIIController::class, 'checkStatus'])->name('documents.check-status');
    Route::get('/documents/{document}/xml', [SIIController::class, 'downloadXML'])->name('documents.xml');
    Route::get('/documents/{document}/pdf', [SIIController::class, 'viewPDF'])->name('documents.pdf');
    
    // Folio management
    Route::get('/folios', [SIIController::class, 'folios'])->name('folios');
    Route::post('/folios/caf', [SIIController::class, 'uploadCAF'])->name('folios.caf');
});