<?php

use App\Modules\Ecommerce\Controllers\Admin\BannerController;
use App\Modules\Ecommerce\Controllers\Admin\CategoryController;
use App\Modules\Ecommerce\Controllers\Admin\CouponController;
use App\Modules\Ecommerce\Controllers\Admin\CustomerController;
use App\Modules\Ecommerce\Controllers\Admin\DashboardController;
use App\Modules\Ecommerce\Controllers\Admin\OrderController;
use App\Modules\Ecommerce\Controllers\Admin\PaymentMethodController;
use App\Modules\Ecommerce\Controllers\Admin\ProductController;
use App\Modules\Ecommerce\Controllers\Admin\ReportController;
use App\Modules\Ecommerce\Controllers\Admin\ShippingMethodController;
use App\Modules\Ecommerce\Controllers\Admin\StoreController;
use Illuminate\Support\Facades\Route;

// Dashboard
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

// Configuración de tienda
Route::get('store/settings', [StoreController::class, 'settings'])->name('store.settings');
Route::put('store/settings', [StoreController::class, 'updateSettings'])->name('store.settings.update');

// Gestión de catálogo
Route::resource('categories', CategoryController::class);
Route::post('categories/reorder', [CategoryController::class, 'reorder'])->name('categories.reorder');

Route::resource('products', ProductController::class);
Route::patch('products/{product}/toggle-published', [ProductController::class, 'togglePublished'])->name('products.toggle-published');
Route::patch('products/{product}/toggle-featured', [ProductController::class, 'toggleFeatured'])->name('products.toggle-featured');
Route::post('products/bulk-action', [ProductController::class, 'bulkAction'])->name('products.bulk-action');
Route::post('products/{product}/images', [ProductController::class, 'uploadImage'])->name('products.images.upload');
Route::delete('products/{product}/images/{image}', [ProductController::class, 'deleteImage'])->name('products.images.delete');
Route::post('products/{product}/variants', [ProductController::class, 'createVariant'])->name('products.variants.create');
Route::put('products/{product}/variants/{variant}', [ProductController::class, 'updateVariant'])->name('products.variants.update');
Route::delete('products/{product}/variants/{variant}', [ProductController::class, 'deleteVariant'])->name('products.variants.delete');
Route::post('products/{product}/publish', [ProductController::class, 'publish'])->name('products.publish');
Route::post('products/{product}/unpublish', [ProductController::class, 'unpublish'])->name('products.unpublish');
Route::post('products/import', [ProductController::class, 'import'])->name('products.import');
Route::get('products/export', [ProductController::class, 'export'])->name('products.export');

// Gestión de pedidos
Route::resource('orders', OrderController::class)->only(['index', 'show', 'update']);
Route::post('orders/{order}/fulfill', [OrderController::class, 'fulfill'])->name('orders.fulfill');
Route::post('orders/{order}/ship', [OrderController::class, 'ship'])->name('orders.ship');
Route::post('orders/{order}/deliver', [OrderController::class, 'deliver'])->name('orders.deliver');
Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
Route::post('orders/{order}/refund', [OrderController::class, 'refund'])->name('orders.refund');
Route::get('orders/{order}/invoice', [OrderController::class, 'invoice'])->name('orders.invoice');
Route::post('orders/{order}/resend-confirmation', [OrderController::class, 'resendConfirmation'])->name('orders.resend-confirmation');

// Gestión de clientes
Route::resource('customers', CustomerController::class)->only(['index', 'show', 'edit', 'update']);
Route::get('customers/{customer}/orders', [CustomerController::class, 'orders'])->name('customers.orders');
Route::get('customers/{customer}/addresses', [CustomerController::class, 'addresses'])->name('customers.addresses');
Route::post('customers/{customer}/tag', [CustomerController::class, 'addTag'])->name('customers.tag');
Route::delete('customers/{customer}/tag/{tag}', [CustomerController::class, 'removeTag'])->name('customers.untag');

// Promociones y cupones
Route::resource('coupons', CouponController::class);
Route::post('coupons/{coupon}/activate', [CouponController::class, 'activate'])->name('coupons.activate');
Route::post('coupons/{coupon}/deactivate', [CouponController::class, 'deactivate'])->name('coupons.deactivate');

// Métodos de envío
Route::resource('shipping-methods', ShippingMethodController::class);
Route::post('shipping-methods/reorder', [ShippingMethodController::class, 'reorder'])->name('shipping-methods.reorder');

// Métodos de pago
Route::resource('payment-methods', PaymentMethodController::class);
Route::post('payment-methods/reorder', [PaymentMethodController::class, 'reorder'])->name('payment-methods.reorder');
Route::post('payment-methods/{method}/test', [PaymentMethodController::class, 'test'])->name('payment-methods.test');

// Banners y contenido
Route::resource('banners', BannerController::class);
Route::post('banners/reorder', [BannerController::class, 'reorder'])->name('banners.reorder');

// Reportes
Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
Route::get('reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
Route::get('reports/products', [ReportController::class, 'products'])->name('reports.products');
Route::get('reports/customers', [ReportController::class, 'customers'])->name('reports.customers');
Route::get('reports/abandoned-carts', [ReportController::class, 'abandonedCarts'])->name('reports.abandoned-carts');
Route::get('reports/export/{type}', [ReportController::class, 'export'])->name('reports.export');

// Newsletter
Route::get('newsletter', [CustomerController::class, 'newsletter'])->name('newsletter.index');
Route::get('newsletter/export', [CustomerController::class, 'exportNewsletter'])->name('newsletter.export');

// Carritos abandonados
Route::get('abandoned-carts', [OrderController::class, 'abandonedCarts'])->name('abandoned-carts.index');
Route::post('abandoned-carts/{cart}/recover', [OrderController::class, 'recoverCart'])->name('abandoned-carts.recover');