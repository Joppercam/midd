<?php

if (!function_exists('tenant')) {
    /**
     * Obtener el tenant actual
     */
    function tenant(): ?App\Models\Tenant
    {
        if (!auth()->check()) {
            return null;
        }

        return auth()->user()->tenant;
    }
}

if (!function_exists('hasModuleAccess')) {
    /**
     * Verificar si el tenant actual tiene acceso a un módulo
     */
    function hasModuleAccess(string $moduleCode): bool
    {
        $tenant = tenant();
        
        if (!$tenant) {
            return false;
        }

        return app('module.manager')->hasAccess($tenant, $moduleCode);
    }
}

if (!function_exists('getActiveModules')) {
    /**
     * Obtener módulos activos del tenant actual
     */
    function getActiveModules(): \Illuminate\Support\Collection
    {
        $tenant = tenant();
        
        if (!$tenant) {
            return collect();
        }

        return app('module.manager')->getTenantModules($tenant);
    }
}

if (!function_exists('logModuleUsage')) {
    /**
     * Registrar uso de un módulo
     */
    function logModuleUsage(string $moduleCode, string $action, array $metadata = []): void
    {
        if (!auth()->check()) {
            return;
        }

        app('module.manager')->logUsage(auth()->user(), $moduleCode, $action, $metadata);
    }
}

if (!function_exists('getModuleConfig')) {
    /**
     * Obtener configuración de un módulo
     */
    function getModuleConfig(string $moduleCode, ?string $key = null, $default = null)
    {
        $config = config("modules.{$moduleCode}");

        if ($key === null) {
            return $config;
        }

        return data_get($config, $key, $default);
    }
}

if (!function_exists('moduleRoute')) {
    /**
     * Generar URL para una ruta de módulo con verificación de acceso
     */
    function moduleRoute(string $moduleCode, string $route, array $parameters = []): string
    {
        if (!hasModuleAccess($moduleCode)) {
            return route('dashboard');
        }

        return route("{$moduleCode}.{$route}", $parameters);
    }
}

if (!function_exists('formatRut')) {
    /**
     * Formatear RUT chileno
     */
    function formatRut(string $rut): string
    {
        $rut = preg_replace('/[^0-9kK]/', '', $rut);
        
        if (strlen($rut) < 2) {
            return $rut;
        }

        $dv = substr($rut, -1);
        $number = substr($rut, 0, -1);
        
        return number_format($number, 0, '', '.') . '-' . strtoupper($dv);
    }
}

if (!function_exists('validateRut')) {
    /**
     * Validar RUT chileno
     */
    function validateRut(string $rut): bool
    {
        $rut = preg_replace('/[^0-9kK]/', '', $rut);
        
        if (strlen($rut) < 2) {
            return false;
        }

        $dv = strtoupper(substr($rut, -1));
        $number = substr($rut, 0, -1);
        
        $sum = 0;
        $multiplier = 2;
        
        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $sum += $number[$i] * $multiplier;
            $multiplier = ($multiplier == 7) ? 2 : $multiplier + 1;
        }
        
        $calculatedDv = 11 - ($sum % 11);
        
        if ($calculatedDv == 11) {
            $calculatedDv = '0';
        } elseif ($calculatedDv == 10) {
            $calculatedDv = 'K';
        } else {
            $calculatedDv = (string) $calculatedDv;
        }
        
        return $dv === $calculatedDv;
    }
}

if (!function_exists('formatCurrency')) {
    /**
     * Formatear moneda chilena
     */
    function formatCurrency(float $amount, bool $includeSymbol = true): string
    {
        $formatted = number_format($amount, 0, ',', '.');
        
        return $includeSymbol ? '$' . $formatted : $formatted;
    }
}

if (!function_exists('parseAmount')) {
    /**
     * Parsear string de moneda a float
     */
    function parseAmount(string $amount): float
    {
        // Remover símbolos de moneda y espacios
        $clean = preg_replace('/[^\d,.-]/', '', $amount);
        
        // Convertir comas a puntos para decimales
        $clean = str_replace(',', '.', $clean);
        
        return (float) $clean;
    }
}

if (!function_exists('canUserAccess')) {
    /**
     * Verificar si el usuario actual puede acceder a una funcionalidad
     */
    function canUserAccess(string $permission, ?string $moduleCode = null): bool
    {
        if (!auth()->check()) {
            return false;
        }

        // Verificar acceso al módulo si se especifica
        if ($moduleCode && !hasModuleAccess($moduleCode)) {
            return false;
        }

        // Verificar permiso
        return auth()->user()->can($permission);
    }
}