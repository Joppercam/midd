<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ModuleManager;
use App\Models\Tenant;
use App\Models\SystemModule;

class TestModuleSystem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:module-system';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the modular system functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing module system functionality...');
        
        try {
            // Test ModuleManager service resolution
            $moduleManager = app(ModuleManager::class);
            $this->info('✓ ModuleManager loaded successfully');
            
            // Test Inventory module services
            $services = [
                \App\Modules\Inventory\Services\InventoryService::class,
                \App\Modules\Inventory\Services\ProductService::class,
                \App\Modules\Inventory\Services\SupplierService::class,
                \App\Modules\Inventory\Services\PurchaseOrderService::class,
                \App\Modules\Inventory\Services\StockMovementService::class,
            ];
            
            foreach ($services as $serviceClass) {
                $service = app($serviceClass);
                $className = class_basename($serviceClass);
                $this->info("✓ {$className} loaded successfully");
            }
            
            // Test tenant and modules
            $tenant = Tenant::first();
            if (!$tenant) {
                $this->warn('No tenant found for testing module access');
                return;
            }
            
            $this->info("Testing with tenant: {$tenant->name}");
            
            // Get available modules
            $availableModules = $moduleManager->getAvailableModules();
            $this->info("Available system modules: {$availableModules->count()}");
            
            // Get tenant's current modules
            $tenantModules = $moduleManager->getTenantModules($tenant);
            $this->info("Tenant active modules: {$tenantModules->count()}");
            
            $this->table(['Module', 'Code', 'Enabled'], $tenantModules->map(function ($tenantModule) {
                return [
                    $tenantModule->systemModule->name,
                    $tenantModule->systemModule->code,
                    $tenantModule->is_enabled ? 'Yes' : 'No'
                ];
            }));
            
            // Test access checks
            $moduleCodes = ['core', 'inventory', 'accounting', 'banking', 'nonexistent'];
            $accessResults = [];
            
            foreach ($moduleCodes as $moduleCode) {
                $hasAccess = $moduleManager->hasAccess($tenant, $moduleCode);
                $accessResults[] = [
                    $moduleCode,
                    $hasAccess ? '✓ YES' : '✗ NO'
                ];
            }
            
            $this->table(['Module Code', 'Access'], $accessResults);
            
            // Test module configuration
            $modules = [
                'Core' => \App\Modules\Core\Module::class,
                'Inventory' => \App\Modules\Inventory\Module::class,
            ];
            
            $this->info('Testing module configurations:');
            foreach ($modules as $name => $moduleClass) {
                if (class_exists($moduleClass)) {
                    $module = new $moduleClass(app());
                    $this->info("  {$name}: v{$module->getVersion()} - {$module->getCode()}");
                    $this->info("    Dependencies: " . (empty($module->getDependencies()) ? 'None' : implode(', ', $module->getDependencies())));
                    $this->info("    Permissions: " . count($module->getPermissions()) . " defined");
                }
            }
            
            $this->info('');
            $this->info('🎉 All module system tests passed successfully!');
            
        } catch (\Exception $e) {
            $this->error("Test failed: {$e->getMessage()}");
            $this->error("File: {$e->getFile()}:{$e->getLine()}");
            return 1;
        }
        
        return 0;
    }
}
