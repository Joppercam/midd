<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SystemModule extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'version',
        'category',
        'dependencies',
        'settings_schema',
        'is_core',
        'is_active',
        'base_price',
        'icon',
        'color',
        'sort_order',
        'features',
        'permissions',
    ];

    protected $casts = [
        'dependencies' => 'array',
        'settings_schema' => 'array',
        'features' => 'array',
        'permissions' => 'array',
        'is_core' => 'boolean',
        'is_active' => 'boolean',
        'base_price' => 'decimal:2',
    ];

    /**
     * Categorías de módulos
     */
    const CATEGORIES = [
        'core' => 'Núcleo',
        'finance' => 'Finanzas',
        'sales' => 'Ventas',
        'operations' => 'Operaciones',
        'hr' => 'Recursos Humanos',
        'analytics' => 'Análisis',
        'integration' => 'Integraciones',
        'industry' => 'Industria Específica',
    ];

    /**
     * Obtener tenants que tienen este módulo
     */
    public function tenantModules(): HasMany
    {
        return $this->hasMany(TenantModule::class, 'module_id');
    }

    /**
     * Obtener tenants activos
     */
    public function activeTenants()
    {
        return $this->tenantModules()
            ->where('is_enabled', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Verificar si el módulo tiene dependencias
     */
    public function hasDependencies(): bool
    {
        return !empty($this->dependencies);
    }

    /**
     * Obtener módulos de los que depende
     */
    public function getDependencyModules()
    {
        if (!$this->hasDependencies()) {
            return collect();
        }

        return self::whereIn('code', $this->dependencies)->get();
    }

    /**
     * Obtener el label de la categoría
     */
    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    /**
     * Obtener el precio formateado
     */
    public function getFormattedPriceAttribute(): string
    {
        if ($this->base_price == 0) {
            return 'Gratis';
        }

        return '$' . number_format($this->base_price, 0, ',', '.');
    }

    /**
     * Verificar si es un módulo premium
     */
    public function isPremium(): bool
    {
        return $this->base_price > 0;
    }

    /**
     * Scope para módulos por categoría
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope para módulos activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para módulos core
     */
    public function scopeCore($query)
    {
        return $query->where('is_core', true);
    }

    /**
     * Obtener configuración del módulo
     */
    public function getConfig(?string $key = null, $default = null)
    {
        $config = config("modules.{$this->code}");

        if ($key === null) {
            return $config;
        }

        return data_get($config, $key, $default);
    }

    /**
     * Verificar si el módulo está instalado
     */
    public function isInstalled(): bool
    {
        return class_exists("App\\Modules\\{$this->code}\\Module");
    }

    /**
     * Obtener la clase del módulo
     */
    public function getModuleClass(): ?string
    {
        $className = "App\\Modules\\{$this->code}\\Module";
        
        return class_exists($className) ? $className : null;
    }

    /**
     * Obtener rutas del módulo
     */
    public function getRoutes(): array
    {
        $routesFile = base_path("app/Modules/{$this->code}/routes.php");
        
        if (file_exists($routesFile)) {
            return require $routesFile;
        }

        return [];
    }

    /**
     * Obtener menú del módulo
     */
    public function getMenuItems(): array
    {
        $moduleClass = $this->getModuleClass();
        
        if ($moduleClass && method_exists($moduleClass, 'getMenuItems')) {
            return $moduleClass::getMenuItems();
        }

        return [];
    }
}