<?php

namespace App\Services;

use App\Models\SystemModule;
use App\Models\TenantModule;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ModuleManager
{
    /**
     * Obtener todos los módulos disponibles en el sistema
     */
    public function getAvailableModules(): Collection
    {
        return Cache::remember('system_modules', 3600, function () {
            return SystemModule::where('is_active', true)
                ->orderBy('category')
                ->orderBy('sort_order')
                ->get();
        });
    }

    /**
     * Obtener módulos activos para un tenant
     */
    public function getTenantModules(Tenant $tenant): Collection
    {
        return Cache::remember("tenant_modules_{$tenant->id}", 600, function () use ($tenant) {
            return $tenant->modules()
                ->where('is_enabled', true)
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->with('systemModule')
                ->get();
        });
    }

    /**
     * Verificar si un tenant tiene acceso a un módulo
     */
    public function hasAccess(Tenant $tenant, string $moduleCode): bool
    {
        $modules = $this->getTenantModules($tenant);
        
        return $modules->contains(function ($tenantModule) use ($moduleCode) {
            return $tenantModule->systemModule->code === $moduleCode;
        });
    }

    /**
     * Habilitar un módulo para un tenant
     */
    public function enableModule(Tenant $tenant, SystemModule $module, array $options = []): TenantModule
    {
        // Verificar dependencias
        if ($module->dependencies) {
            foreach ($module->dependencies as $dependency) {
                if (!$this->hasAccess($tenant, $dependency)) {
                    throw new \Exception("El módulo requiere: {$dependency}");
                }
            }
        }

        // Verificar límites del plan
        if (!$this->canAddModule($tenant, $module)) {
            throw new \Exception("Límite de módulos alcanzado para el plan actual");
        }

        DB::beginTransaction();
        try {
            $tenantModule = TenantModule::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'module_id' => $module->id,
                ],
                [
                    'is_enabled' => true,
                    'enabled_at' => now(),
                    'disabled_at' => null,
                    'expires_at' => $options['expires_at'] ?? null,
                    'settings' => $options['settings'] ?? [],
                    'custom_price' => $options['custom_price'] ?? null,
                    'billing_cycle' => $options['billing_cycle'] ?? 'monthly',
                ]
            );

            // Asignar permisos del módulo a los roles
            $this->assignModulePermissions($tenant, $module);

            // Ejecutar setup del módulo
            $this->runModuleSetup($tenant, $module);

            // Limpiar caché
            Cache::forget("tenant_modules_{$tenant->id}");

            DB::commit();

            Log::info("Módulo {$module->code} habilitado para tenant {$tenant->id}");

            return $tenantModule;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Deshabilitar un módulo para un tenant
     */
    public function disableModule(Tenant $tenant, SystemModule $module): void
    {
        // No permitir deshabilitar módulos core
        if ($module->is_core) {
            throw new \Exception("No se puede deshabilitar un módulo core");
        }

        // Verificar que no hay módulos dependientes activos
        $dependentModules = $this->getDependentModules($tenant, $module);
        if ($dependentModules->isNotEmpty()) {
            $names = $dependentModules->pluck('name')->join(', ');
            throw new \Exception("Primero debe deshabilitar: {$names}");
        }

        DB::beginTransaction();
        try {
            $tenantModule = TenantModule::where('tenant_id', $tenant->id)
                ->where('module_id', $module->id)
                ->firstOrFail();

            $tenantModule->update([
                'is_enabled' => false,
                'disabled_at' => now(),
            ]);

            // Revocar permisos del módulo
            $this->revokeModulePermissions($tenant, $module);

            // Ejecutar cleanup del módulo
            $this->runModuleCleanup($tenant, $module);

            // Limpiar caché
            Cache::forget("tenant_modules_{$tenant->id}");

            DB::commit();

            Log::info("Módulo {$module->code} deshabilitado para tenant {$tenant->id}");

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Obtener estadísticas de uso de módulos
     */
    public function getModuleUsageStats(Tenant $tenant, string $period = 'month'): array
    {
        $startDate = match($period) {
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'year' => now()->subYear(),
            default => now()->subMonth(),
        };

        return DB::table('module_usage_logs')
            ->select('system_modules.name', 'system_modules.code')
            ->selectRaw('COUNT(DISTINCT module_usage_logs.user_id) as unique_users')
            ->selectRaw('COUNT(*) as total_actions')
            ->selectRaw('SUM(module_usage_logs.count) as total_count')
            ->join('system_modules', 'module_usage_logs.module_id', '=', 'system_modules.id')
            ->where('module_usage_logs.tenant_id', $tenant->id)
            ->where('module_usage_logs.logged_at', '>=', $startDate)
            ->groupBy('system_modules.id', 'system_modules.name', 'system_modules.code')
            ->orderByDesc('total_actions')
            ->get()
            ->toArray();
    }

    /**
     * Obtener recomendaciones de módulos basadas en el tipo de negocio
     */
    public function getRecommendedModules(Tenant $tenant): Collection
    {
        // Obtener módulos actuales
        $currentModules = $this->getTenantModules($tenant)->pluck('systemModule.code');

        // Obtener recomendaciones basadas en el tipo de negocio
        $recommendations = DB::table('business_type_modules')
            ->where('business_type', $tenant->business_type ?? 'general')
            ->where('business_size', $tenant->business_size ?? 'small')
            ->first();

        if (!$recommendations) {
            return collect();
        }

        $recommendedCodes = array_merge(
            json_decode($recommendations->recommended_modules, true) ?? [],
            json_decode($recommendations->optional_modules, true) ?? []
        );

        // Filtrar módulos que ya tiene el tenant
        $recommendedCodes = array_diff($recommendedCodes, $currentModules->toArray());

        return SystemModule::whereIn('code', $recommendedCodes)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Calcular el precio total de los módulos activos
     */
    public function calculateMonthlyPrice(Tenant $tenant): float
    {
        $modules = $this->getTenantModules($tenant);
        $basePrice = 0;

        // Precio del plan base
        if ($tenant->subscription) {
            $basePrice = $tenant->subscription->monthly_amount;
        }

        // Sumar precios de módulos adicionales
        $additionalPrice = $modules->sum(function ($tenantModule) {
            return $tenantModule->custom_price ?? $tenantModule->systemModule->base_price;
        });

        return $basePrice + $additionalPrice;
    }

    /**
     * Verificar si se puede agregar un módulo según el plan
     */
    private function canAddModule(Tenant $tenant, SystemModule $module): bool
    {
        if (!$tenant->subscription) {
            return true; // Sin restricciones si no hay suscripción
        }

        $plan = $tenant->subscription->plan;
        $includedModules = $plan->included_modules ?? [];

        // Si el módulo está incluido en el plan, siempre se puede agregar
        if (in_array($module->code, $includedModules)) {
            return true;
        }

        // Verificar límites del plan
        $limits = $plan->limits ?? [];
        $maxModules = $limits['max_additional_modules'] ?? PHP_INT_MAX;

        $currentAdditionalModules = $this->getTenantModules($tenant)
            ->filter(function ($tenantModule) use ($includedModules) {
                return !in_array($tenantModule->systemModule->code, $includedModules);
            })
            ->count();

        return $currentAdditionalModules < $maxModules;
    }

    /**
     * Obtener módulos que dependen de otro módulo
     */
    private function getDependentModules(Tenant $tenant, SystemModule $module): Collection
    {
        $allModules = $this->getAvailableModules();
        $activeModules = $this->getTenantModules($tenant);

        return $activeModules->filter(function ($tenantModule) use ($module, $allModules) {
            $systemModule = $allModules->firstWhere('id', $tenantModule->module_id);
            $dependencies = $systemModule->dependencies ?? [];
            
            return in_array($module->code, $dependencies);
        });
    }

    /**
     * Asignar permisos del módulo a los roles del tenant
     */
    private function assignModulePermissions(Tenant $tenant, SystemModule $module): void
    {
        $permissions = $module->permissions ?? [];
        
        if (empty($permissions)) {
            return;
        }

        // Implementar lógica para asignar permisos
        // Esto dependerá de cómo esté implementado el sistema de permisos
    }

    /**
     * Revocar permisos del módulo
     */
    private function revokeModulePermissions(Tenant $tenant, SystemModule $module): void
    {
        $permissions = $module->permissions ?? [];
        
        if (empty($permissions)) {
            return;
        }

        // Implementar lógica para revocar permisos
    }

    /**
     * Ejecutar setup inicial del módulo
     */
    private function runModuleSetup(Tenant $tenant, SystemModule $module): void
    {
        $setupClass = "App\\Modules\\{$module->code}\\Setup";
        
        if (class_exists($setupClass)) {
            $setup = new $setupClass();
            $setup->install($tenant);
        }
    }

    /**
     * Ejecutar cleanup al deshabilitar módulo
     */
    private function runModuleCleanup(Tenant $tenant, SystemModule $module): void
    {
        $setupClass = "App\\Modules\\{$module->code}\\Setup";
        
        if (class_exists($setupClass)) {
            $setup = new $setupClass();
            if (method_exists($setup, 'uninstall')) {
                $setup->uninstall($tenant);
            }
        }
    }

    /**
     * Registrar uso de un módulo
     */
    public function logUsage(User $user, string $moduleCode, string $action, array $metadata = []): void
    {
        $module = SystemModule::where('code', $moduleCode)->first();
        
        if (!$module) {
            return;
        }

        DB::table('module_usage_logs')->insert([
            'tenant_id' => $user->tenant_id,
            'module_id' => $module->id,
            'user_id' => $user->id,
            'action' => $action,
            'entity' => $metadata['entity'] ?? null,
            'count' => $metadata['count'] ?? 1,
            'metadata' => json_encode($metadata),
            'logged_at' => now(),
        ]);
    }
}