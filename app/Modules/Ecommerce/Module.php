<?php

namespace App\Modules\Ecommerce;

use App\Core\BaseModule;
use Illuminate\Support\Facades\Route;

class Module extends BaseModule
{
    public function getCode(): string
    {
        return 'ecommerce';
    }

    public function getName(): string
    {
        return 'E-commerce B2B/B2C';
    }

    public function getDescription(): string
    {
        return 'Tienda online completa con catálogo, carrito de compras, pagos y gestión de pedidos';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDependencies(): array
    {
        return ['core', 'inventory', 'invoicing'];
    }

    public function getPermissions(): array
    {
        return [
            'ecommerce.view' => 'Ver e-commerce',
            'ecommerce.catalog.manage' => 'Gestionar catálogo',
            'ecommerce.categories.manage' => 'Gestionar categorías',
            'ecommerce.products.publish' => 'Publicar productos',
            'ecommerce.orders.view' => 'Ver pedidos',
            'ecommerce.orders.process' => 'Procesar pedidos',
            'ecommerce.orders.cancel' => 'Cancelar pedidos',
            'ecommerce.customers.view' => 'Ver clientes online',
            'ecommerce.promotions.manage' => 'Gestionar promociones',
            'ecommerce.shipping.manage' => 'Gestionar envíos',
            'ecommerce.payments.manage' => 'Gestionar pagos',
            'ecommerce.reports.view' => 'Ver reportes e-commerce',
            'ecommerce.settings.manage' => 'Configurar e-commerce',
        ];
    }

    public function register(): void
    {
        parent::register();
        
        $this->loadMigrationsFrom(__DIR__ . '/Database/migrations');
        $this->loadViewsFrom(__DIR__ . '/Views', 'ecommerce');
        $this->mergeConfigFrom(__DIR__ . '/Config/ecommerce.php', 'ecommerce');
    }

    public function boot(): void
    {
        parent::boot();
        
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/Config/ecommerce.php' => config_path('ecommerce.php'),
            ], 'ecommerce-config');
        }
    }

    protected function loadRoutes(): void
    {
        // Rutas del panel de administración
        Route::middleware(['web', 'auth', 'module:ecommerce'])
            ->prefix('ecommerce')
            ->name('ecommerce.')
            ->group(__DIR__ . '/routes/admin.php');
            
        // Rutas de la tienda pública
        Route::middleware(['web'])
            ->prefix('shop')
            ->name('shop.')
            ->group(__DIR__ . '/routes/shop.php');
            
        // API para carrito y checkout
        Route::middleware(['api'])
            ->prefix('api/shop')
            ->name('api.shop.')
            ->group(__DIR__ . '/routes/api.php');
    }
}