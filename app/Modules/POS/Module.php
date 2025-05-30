<?php

namespace App\Modules\POS;

use App\Core\BaseModule;
use Illuminate\Support\Facades\Route;

class Module extends BaseModule
{
    public function getCode(): string
    {
        return 'pos';
    }

    public function getName(): string
    {
        return 'Punto de Venta';
    }

    public function getDescription(): string
    {
        return 'Sistema de punto de venta completo con gestión de cajas, ventas rápidas y facturación instantánea';
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
            // Acceso general
            'pos.view' => 'Ver punto de venta',
            'pos.dashboard' => 'Ver dashboard POS',
            
            // Ventas
            'pos.sales.create' => 'Crear ventas',
            'pos.sales.view' => 'Ver ventas',
            'pos.sales.edit' => 'Editar ventas',
            'pos.sales.void' => 'Anular ventas',
            'pos.sales.refund' => 'Procesar devoluciones',
            'pos.sales.discount' => 'Aplicar descuentos',
            'pos.sales.print' => 'Imprimir tickets',
            'pos.sales.email' => 'Enviar tickets por email',
            
            // Gestión de cajas
            'pos.cash_register.view' => 'Ver cajas registradoras',
            'pos.cash_register.open' => 'Abrir caja',
            'pos.cash_register.close' => 'Cerrar caja',
            'pos.cash_register.manage' => 'Gestionar cajas',
            'pos.cash_register.count' => 'Realizar arqueos',
            'pos.cash_register.transfer' => 'Transferir entre cajas',
            
            // Terminales
            'pos.terminals.view' => 'Ver terminales',
            'pos.terminals.create' => 'Crear terminales',
            'pos.terminals.edit' => 'Editar terminales',
            'pos.terminals.delete' => 'Eliminar terminales',
            'pos.terminals.assign' => 'Asignar terminales',
            
            // Transacciones y sesiones
            'pos.transactions.view' => 'Ver transacciones',
            'pos.transactions.void' => 'Anular transacciones',
            'pos.sessions.view' => 'Ver sesiones de caja',
            'pos.sessions.manage' => 'Gestionar sesiones',
            
            // Reportes
            'pos.reports.view' => 'Ver reportes POS',
            'pos.reports.sales' => 'Reportes de ventas',
            'pos.reports.cash' => 'Reportes de caja',
            'pos.reports.products' => 'Reportes de productos',
            'pos.reports.users' => 'Reportes de usuarios',
            'pos.reports.export' => 'Exportar reportes',
            
            // Configuración
            'pos.settings.view' => 'Ver configuración POS',
            'pos.settings.manage' => 'Configurar POS',
            'pos.settings.printers' => 'Configurar impresoras',
            'pos.settings.payments' => 'Configurar métodos de pago',
            'pos.settings.taxes' => 'Configurar impuestos',
            
            // Funciones especiales
            'pos.offline.sync' => 'Sincronizar ventas offline',
            'pos.training.mode' => 'Modo entrenamiento',
            'pos.backup.create' => 'Crear respaldos POS',
            'pos.backup.restore' => 'Restaurar respaldos POS',
        ];
    }

    public function register(): void
    {
        parent::register();
        
        $this->loadMigrationsFrom(__DIR__ . '/Database/migrations');
        $this->loadViewsFrom(__DIR__ . '/Views', 'pos');
        $this->mergeConfigFrom(__DIR__ . '/Config/pos.php', 'pos');
    }

    public function boot(): void
    {
        parent::boot();
        
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/Config/pos.php' => config_path('pos.php'),
            ], 'pos-config');
        }
    }

    protected function loadRoutes(): void
    {
        Route::middleware(['web', 'auth', 'module:pos'])
            ->prefix('pos')
            ->name('pos.')
            ->group(__DIR__ . '/routes.php');
            
        // API para aplicación móvil/tablet
        Route::middleware(['api', 'auth:sanctum', 'module:pos'])
            ->prefix('api/pos')
            ->name('api.pos.')
            ->group(__DIR__ . '/routes/api.php');
    }
}