<?php

use App\Modules\Ecommerce\Controllers\Shop\AccountController;
use App\Modules\Ecommerce\Controllers\Shop\CartController;
use App\Modules\Ecommerce\Controllers\Shop\CategoryController;
use App\Modules\Ecommerce\Controllers\Shop\CheckoutController;
use App\Modules\Ecommerce\Controllers\Shop\HomeController;
use App\Modules\Ecommerce\Controllers\Shop\ProductController;
use App\Modules\Ecommerce\Controllers\Shop\SearchController;
use Illuminate\Support\Facades\Route;

// Página principal
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/about', [HomeController::class, 'about'])->name('about');
Route::get('/contact', [HomeController::class, 'contact'])->name('contact');
Route::post('/contact', [HomeController::class, 'sendContact'])->name('contact.send');

// Catálogo y categorías
Route::get('/catalog', [CategoryController::class, 'catalog'])->name('catalog');
Route::get('/category/{slug}', [CategoryController::class, 'show'])->name('category.show');

// Productos
Route::get('/product/{slug}', [ProductController::class, 'show'])->name('product.show');
Route::post('/product/{product}/review', [ProductController::class, 'review'])->name('product.review');

// Búsqueda
Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::get('/search/suggestions', [SearchController::class, 'suggestions'])->name('search.suggestions');

// Carrito
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
Route::put('/cart/update/{item}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/remove/{item}', [CartController::class, 'remove'])->name('cart.remove');
Route::post('/cart/coupon', [CartController::class, 'applyCoupon'])->name('cart.coupon');
Route::delete('/cart/coupon', [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');

// Checkout
Route::middleware(['throttle:checkout'])->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout/customer', [CheckoutController::class, 'saveCustomer'])->name('checkout.customer');
    Route::post('/checkout/shipping', [CheckoutController::class, 'saveShipping'])->name('checkout.shipping');
    Route::post('/checkout/payment', [CheckoutController::class, 'savePayment'])->name('checkout.payment');
    Route::post('/checkout/process', [CheckoutController::class, 'process'])->name('checkout.process');
    Route::get('/checkout/success/{order}', [CheckoutController::class, 'success'])->name('checkout.success');
    Route::get('/checkout/failure', [CheckoutController::class, 'failure'])->name('checkout.failure');
});

// Cuenta del cliente
Route::middleware(['auth:ecommerce'])->group(function () {
    Route::get('/account', [AccountController::class, 'index'])->name('account.index');
    Route::get('/account/orders', [AccountController::class, 'orders'])->name('account.orders');
    Route::get('/account/orders/{order}', [AccountController::class, 'orderDetail'])->name('account.order.detail');
    Route::get('/account/addresses', [AccountController::class, 'addresses'])->name('account.addresses');
    Route::post('/account/addresses', [AccountController::class, 'addAddress'])->name('account.addresses.add');
    Route::put('/account/addresses/{address}', [AccountController::class, 'updateAddress'])->name('account.addresses.update');
    Route::delete('/account/addresses/{address}', [AccountController::class, 'deleteAddress'])->name('account.addresses.delete');
    Route::get('/account/wishlist', [AccountController::class, 'wishlist'])->name('account.wishlist');
    Route::get('/account/profile', [AccountController::class, 'profile'])->name('account.profile');
    Route::put('/account/profile', [AccountController::class, 'updateProfile'])->name('account.profile.update');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password.update');
    Route::post('/logout', [AccountController::class, 'logout'])->name('logout');
});

// Autenticación del cliente
Route::middleware(['guest:ecommerce'])->group(function () {
    Route::get('/login', [AccountController::class, 'showLogin'])->name('login');
    Route::post('/login', [AccountController::class, 'login'])->name('login.post');
    Route::get('/register', [AccountController::class, 'showRegister'])->name('register');
    Route::post('/register', [AccountController::class, 'register'])->name('register.post');
    Route::get('/forgot-password', [AccountController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AccountController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [AccountController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AccountController::class, 'resetPassword'])->name('password.update');
});

// Wishlist (puede funcionar con o sin autenticación)
Route::post('/wishlist/add/{product}', [ProductController::class, 'addToWishlist'])->name('wishlist.add');
Route::delete('/wishlist/remove/{product}', [ProductController::class, 'removeFromWishlist'])->name('wishlist.remove');

// Newsletter
Route::post('/newsletter/subscribe', [HomeController::class, 'subscribeNewsletter'])->name('newsletter.subscribe');
Route::get('/newsletter/confirm/{token}', [HomeController::class, 'confirmNewsletter'])->name('newsletter.confirm');
Route::post('/newsletter/unsubscribe', [HomeController::class, 'unsubscribeNewsletter'])->name('newsletter.unsubscribe');

// Páginas estáticas
Route::get('/terms', [HomeController::class, 'terms'])->name('terms');
Route::get('/privacy', [HomeController::class, 'privacy'])->name('privacy');
Route::get('/shipping-info', [HomeController::class, 'shippingInfo'])->name('shipping-info');
Route::get('/returns', [HomeController::class, 'returns'])->name('returns');