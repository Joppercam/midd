<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use App\Services\ModuleManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Registrar servicios personalizados
        $this->app->singleton(\App\Services\EmailNotificationService::class, function () {
            return new \App\Services\EmailNotificationService();
        });

        // Registrar servicio de notificaciones en tiempo real
        $this->app->singleton(\App\Services\RealTimeNotificationService::class, function () {
            return new \App\Services\RealTimeNotificationService();
        });

        // Registrar servicios SII
        $this->app->singleton(\App\Services\SII\XSDValidatorService::class);
        $this->app->singleton(\App\Services\SII\XMLSignerService::class);
        $this->app->singleton(\App\Services\SII\ResponseProcessorService::class);
        $this->app->singleton(\App\Services\SII\FolioManagerService::class);
        
        $this->app->singleton(\App\Services\SII\XMLGeneratorService::class, function ($app) {
            return new \App\Services\SII\XMLGeneratorService(
                $app->make(\App\Services\SII\XSDValidatorService::class)
            );
        });
        
        $this->app->singleton(\App\Services\SII\DTEService::class, function ($app) {
            return new \App\Services\SII\DTEService(
                $app->make(\App\Services\SII\XMLGeneratorService::class),
                $app->make(\App\Services\SII\XMLSignerService::class),
                $app->make(\App\Services\SII\XSDValidatorService::class),
                $app->make(\App\Services\SII\ResponseProcessorService::class),
                $app->make(\App\Services\SII\FolioManagerService::class)
            );
        });

        // Registrar ModuleManager
        $this->app->singleton(ModuleManager::class);
        $this->app->alias(ModuleManager::class, 'module.manager');

        // Registrar servicios del módulo Invoicing
        $this->app->singleton(\App\Modules\Invoicing\Services\PaymentService::class);
        $this->app->singleton(\App\Modules\Invoicing\Services\SIIIntegrationService::class);
        $this->app->singleton(\App\Modules\Invoicing\Services\CertificateService::class);

        // Registrar servicios del módulo Accounting
        $this->app->singleton(\App\Modules\Accounting\Services\ExpenseService::class);
        $this->app->singleton(\App\Modules\Accounting\Services\ChartOfAccountsService::class);

        // Registrar servicios del módulo CRM
        $this->app->singleton(\App\Modules\CRM\Services\CustomerService::class);
        $this->app->singleton(\App\Modules\CRM\Services\LeadScoringService::class);

        // Registrar servicios del módulo Inventory
        $this->app->singleton(\App\Modules\Inventory\Services\InventoryService::class);
        $this->app->singleton(\App\Modules\Inventory\Services\ProductService::class);
        $this->app->singleton(\App\Modules\Inventory\Services\SupplierService::class);
        $this->app->singleton(\App\Modules\Inventory\Services\PurchaseOrderService::class);
        $this->app->singleton(\App\Modules\Inventory\Services\StockMovementService::class);

        // Registrar servicios del módulo HRM
        $this->app->singleton(\App\Modules\HRM\Services\EmployeeService::class);
        $this->app->singleton(\App\Modules\HRM\Services\AttendanceService::class);
        $this->app->singleton(\App\Modules\HRM\Services\PayrollService::class);

        // Registrar servicios del módulo POS
        $this->app->singleton(\App\Modules\POS\Services\POSService::class);
        $this->app->singleton(\App\Modules\POS\Services\CashSessionService::class);
        $this->app->singleton(\App\Modules\POS\Services\TransactionService::class);
        $this->app->singleton(\App\Modules\POS\Services\TerminalService::class);

        // Registrar servicios del módulo Analytics
        $this->app->singleton(\App\Modules\Analytics\Services\AnalyticsService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        
        // Load modules
        $this->loadModules();
    }

    /**
     * Load all active modules
     */
    protected function loadModules(): void
    {
        // Load modules in order of dependency
        $modules = [
            'Core' => \App\Modules\Core\Module::class,
            'Analytics' => \App\Modules\Analytics\Module::class,
            'Banking' => \App\Modules\Banking\Module::class,
            'CRM' => \App\Modules\CRM\Module::class,
            'Inventory' => \App\Modules\Inventory\Module::class,
            'Invoicing' => \App\Modules\Invoicing\Module::class,
            'Accounting' => \App\Modules\Accounting\Module::class,
            'HRM' => \App\Modules\Hrm\Module::class,
            'POS' => \App\Modules\POS\Module::class,
            'Ecommerce' => \App\Modules\Ecommerce\Module::class,
        ];

        foreach ($modules as $name => $moduleClass) {
            $this->loadModule($name, $moduleClass);
        }
    }

    /**
     * Safely load a single module
     */
    protected function loadModule(string $name, string $moduleClass): void
    {
        try {
            if (!class_exists($moduleClass)) {
                return;
            }

            $module = new $moduleClass($this->app);
            
            // Check dependencies
            $dependencies = $module->getDependencies();
            foreach ($dependencies as $dependency) {
                if (!$this->isModuleLoaded($dependency)) {
                    \Log::warning("Module {$name} requires {$dependency} but it's not loaded");
                    return;
                }
            }
            
            // Register module
            $module->register();
            
            // Boot module safely
            $module->boot();
            
            \Log::info("Module {$name} loaded successfully");
            
        } catch (\Throwable $e) {
            \Log::error("Failed to load module {$name}: " . $e->getMessage(), [
                'exception' => $e,
                'module' => $name,
            ]);
        }
    }

    /**
     * Check if a module is loaded
     */
    protected function isModuleLoaded(string $moduleName): bool
    {
        // For now, assume core dependencies are always available
        return in_array($moduleName, ['core']);
    }
}
