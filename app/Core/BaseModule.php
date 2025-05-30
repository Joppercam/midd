<?php

namespace App\Core;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

abstract class BaseModule extends ServiceProvider
{
    /**
     * Código único del módulo
     */
    abstract public function getCode(): string;

    /**
     * Nombre del módulo
     */
    abstract public function getName(): string;

    /**
     * Versión del módulo
     */
    abstract public function getVersion(): string;

    /**
     * Descripción del módulo
     */
    abstract public function getDescription(): string;

    /**
     * Dependencias del módulo
     */
    public function getDependencies(): array
    {
        return [];
    }

    /**
     * Permisos que provee el módulo
     */
    public function getPermissions(): array
    {
        return [];
    }

    /**
     * Items de menú del módulo
     */
    public function getMenuItems(): array
    {
        return [];
    }

    /**
     * Widgets para el dashboard
     */
    public function getWidgets(): array
    {
        return [];
    }

    /**
     * Configuración por defecto del módulo
     */
    public function getDefaultSettings(): array
    {
        return [];
    }

    /**
     * Configuración del módulo
     */
    public function getConfig(): array
    {
        return config('modules.' . $this->getCode(), []);
    }

    /**
     * Registrar servicios del módulo
     */
    public function register()
    {
        $this->mergeConfigFrom(
            $this->getModulePath('config.php'),
            'modules.' . $this->getCode()
        );
    }

    /**
     * Bootstrap del módulo
     */
    public function boot()
    {
        if ($this->isEnabled()) {
            $this->loadRoutes();
            $this->loadViews();
            $this->loadMigrations();
            $this->loadTranslations();
            $this->registerCommands();
            $this->registerEventListeners();
            $this->registerMiddleware();
        }
    }

    /**
     * Verificar si el módulo está habilitado
     */
    protected function isEnabled(): bool
    {
        // En contexto web, verificar tenant actual
        if (app()->runningInConsole()) {
            return true;
        }

        $tenant = tenant();
        if (!$tenant) {
            return false;
        }

        return app('module.manager')->hasAccess($tenant, $this->getCode());
    }

    /**
     * Cargar rutas del módulo
     */
    protected function loadRoutes(): void
    {
        $routesFile = $this->getModulePath('routes.php');
        
        if (file_exists($routesFile)) {
            Route::middleware(['web', 'auth', 'module:' . $this->getCode()])
                ->prefix($this->getCode())
                ->name($this->getCode() . '.')
                ->group($routesFile);
        }

        // API routes
        $apiRoutesFile = $this->getModulePath('routes/api.php');
        if (file_exists($apiRoutesFile)) {
            Route::middleware(['api', 'auth:sanctum', 'module:' . $this->getCode()])
                ->prefix('api/v1/' . $this->getCode())
                ->name('api.' . $this->getCode() . '.')
                ->group($apiRoutesFile);
        }
    }

    /**
     * Cargar vistas del módulo
     */
    protected function loadViews(): void
    {
        $viewsPath = $this->getModulePath('Views');
        
        if (is_dir($viewsPath)) {
            View::addNamespace($this->getCode(), $viewsPath);
        }

        // Publicar vistas Vue.js
        $this->publishes([
            $this->getModulePath('resources/js') => resource_path('js/Modules/' . ucfirst($this->getCode())),
        ], $this->getCode() . '-views');
    }

    /**
     * Cargar migraciones del módulo
     */
    protected function loadMigrations(): void
    {
        $migrationsPath = $this->getModulePath('database/migrations');
        
        if (is_dir($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }
    }

    /**
     * Cargar traducciones del módulo
     */
    protected function loadTranslations(): void
    {
        $langPath = $this->getModulePath('lang');
        
        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->getCode());
        }
    }

    /**
     * Registrar comandos del módulo
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $commandsPath = $this->getModulePath('Console/Commands');
            
            if (is_dir($commandsPath)) {
                $commands = glob($commandsPath . '/*.php');
                
                foreach ($commands as $command) {
                    $class = $this->getClassFromFile($command);
                    if (class_exists($class)) {
                        $this->commands([$class]);
                    }
                }
            }
        }
    }

    /**
     * Registrar event listeners del módulo
     */
    protected function registerEventListeners(): void
    {
        // Override en módulos específicos
    }

    /**
     * Registrar middleware del módulo
     */
    protected function registerMiddleware(): void
    {
        // Override en módulos específicos
    }

    /**
     * Obtener ruta del módulo
     */
    protected function getModulePath(string $path = ''): string
    {
        $modulePath = app_path('Modules/' . ucfirst($this->getCode()));
        
        return $path ? $modulePath . '/' . $path : $modulePath;
    }

    /**
     * Obtener clase desde archivo
     */
    protected function getClassFromFile(string $file): string
    {
        $className = basename($file, '.php');
        $namespace = 'App\\Modules\\' . ucfirst($this->getCode()) . '\\Console\\Commands';
        
        return $namespace . '\\' . $className;
    }

    /**
     * Instalar módulo para un tenant
     */
    public function install($tenant): void
    {
        // Override para lógica de instalación específica
    }

    /**
     * Desinstalar módulo de un tenant
     */
    public function uninstall($tenant): void
    {
        // Override para lógica de desinstalación
    }

    /**
     * Actualizar módulo
     */
    public function update(string $fromVersion): void
    {
        // Override para lógica de actualización
    }
}