<?php

use App\Modules\Ecommerce\Controllers\Api\CartApiController;
use App\Modules\Ecommerce\Controllers\Api\CheckoutApiController;
use App\Modules\Ecommerce\Controllers\Api\ProductApiController;
use App\Modules\Ecommerce\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

// API del carrito
Route::prefix('cart')->group(function () {
    Route::get('/', [CartApiController::class, 'show']);
    Route::post('/items', [CartApiController::class, 'addItem']);
    Route::put('/items/{item}', [CartApiController::class, 'updateItem']);
    Route::delete('/items/{item}', [CartApiController::class, 'removeItem']);
    Route::delete('/', [CartApiController::class, 'clear']);
    Route::post('/coupon', [CartApiController::class, 'applyCoupon']);
    Route::delete('/coupon', [CartApiController::class, 'removeCoupon']);
});

// API de checkout
Route::prefix('checkout')->group(function () {
    Route::post('/calculate-shipping', [CheckoutApiController::class, 'calculateShipping']);
    Route::post('/validate-address', [CheckoutApiController::class, 'validateAddress']);
    Route::post('/validate-coupon', [CheckoutApiController::class, 'validateCoupon']);
    Route::get('/payment-methods', [CheckoutApiController::class, 'paymentMethods']);
    Route::post('/process', [CheckoutApiController::class, 'process']);
});

// API de productos
Route::prefix('products')->group(function () {
    Route::get('/', [ProductApiController::class, 'index']);
    Route::get('/{product}', [ProductApiController::class, 'show']);
    Route::get('/{product}/availability', [ProductApiController::class, 'checkAvailability']);
    Route::get('/{product}/related', [ProductApiController::class, 'related']);
});

// Webhooks de pagos
Route::prefix('webhooks')->group(function () {
    Route::post('/webpay', [WebhookController::class, 'webpay']);
    Route::post('/mercadopago', [WebhookController::class, 'mercadopago']);
    Route::post('/paypal', [WebhookController::class, 'paypal']);
    Route::post('/stripe', [WebhookController::class, 'stripe']);
});

// Tracking
Route::post('/track/view', [ProductApiController::class, 'trackView']);
Route::post('/track/cart', [CartApiController::class, 'trackCart']);
Route::post('/track/purchase', [CheckoutApiController::class, 'trackPurchase']);