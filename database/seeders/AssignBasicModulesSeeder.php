<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\SystemModule;
use App\Models\TenantModule;
use App\Models\SubscriptionPlan;
use App\Models\TenantSubscription;
use App\Services\ModuleManager;

class AssignBasicModulesSeeder extends Seeder
{
    public function run(): void
    {
        $moduleManager = app(ModuleManager::class);
        
        // Obtener módulos básicos que todos deberían tener (en orden de dependencias)
        $basicModuleCodes = [
            'core',        // Sin dependencias
            'tenancy',     // Depende de core  
            'customers',   // Depende de core
            'inventory',   // Depende de core
            'invoicing',   // Depende de core, tenancy
            'payments'     // Depende de core, invoicing
        ];
        
        $basicModules = collect($basicModuleCodes)->map(function($code) {
            return SystemModule::where('code', $code)->first();
        })->filter();

        // Obtener todos los tenants
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $this->command->info("Configurando módulos para tenant: {$tenant->name}");
            
            // Crear suscripción básica si no tiene
            if (!$tenant->subscription) {
                $starterPlan = SubscriptionPlan::where('code', 'starter')->first();
                
                if ($starterPlan) {
                    TenantSubscription::create([
                        'tenant_id' => $tenant->id,
                        'plan_id' => $starterPlan->id,
                        'status' => 'trial',
                        'started_at' => now(),
                        'trial_ends_at' => now()->addDays(30),
                        'current_period_start' => now(),
                        'current_period_end' => now()->addMonth(),
                        'monthly_amount' => $starterPlan->monthly_price,
                        'billing_cycle' => 'monthly',
                    ]);
                    
                    $this->command->info("  - Suscripción trial creada (Plan Starter)");
                }
            }

            // Asignar módulos básicos
            foreach ($basicModules as $module) {
                try {
                    // Verificar si ya tiene el módulo
                    $existingModule = TenantModule::where('tenant_id', $tenant->id)
                        ->where('module_id', $module->id)
                        ->first();

                    if (!$existingModule) {
                        $moduleManager->enableModule($tenant, $module, [
                            'settings' => [],
                        ]);
                        
                        $this->command->info("  - Módulo '{$module->name}' habilitado");
                    } else {
                        $this->command->info("  - Módulo '{$module->name}' ya existe");
                    }
                } catch (\Exception $e) {
                    $this->command->error("  - Error al habilitar '{$module->name}': " . $e->getMessage());
                }
            }

            $this->command->info("  ✓ Tenant configurado\n");
        }

        $this->command->info("Módulos básicos asignados a todos los tenants.");
    }
}