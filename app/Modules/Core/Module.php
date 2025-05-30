<?php

namespace App\Modules\Core;

use App\Core\BaseModule;

class Module extends BaseModule
{
    /**
     * Código único del módulo
     */
    public function getCode(): string
    {
        return 'core';
    }

    /**
     * Nombre del módulo
     */
    public function getName(): string
    {
        return 'Núcleo del Sistema';
    }

    /**
     * Versión del módulo
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Descripción del módulo
     */
    public function getDescription(): string
    {
        return 'Funcionalidades básicas del sistema: autenticación, usuarios, configuración y dashboard.';
    }

    /**
     * Dependencias del módulo
     */
    public function getDependencies(): array
    {
        return []; // El core no tiene dependencias
    }

    /**
     * Permisos que provee el módulo
     */
    public function getPermissions(): array
    {
        return [
            // Dashboard
            'dashboard.view',
            
            // Users
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'users.impersonate',
            
            // Roles
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',
            
            // Settings
            'settings.view',
            'settings.edit',
            
            // Profile
            'profile.edit',
            
            // Backups
            'backups.view',
            'backups.create',
            'backups.download',
            'backups.delete',
            'backups.restore',
            'backups.schedule',
            
            // Audit
            'audit.view',
            'audit.export',
            'audit.manage',
            
            // Notifications
            'notifications.view',
            'notifications.manage',
            'notifications.send',
            'notifications.broadcast',
            'notifications.test',
            'notifications.delete',
        ];
    }

    /**
     * Items de menú del módulo
     */
    public function getMenuItems(): array
    {
        return [
            [
                'label' => 'Dashboard',
                'route' => 'dashboard',
                'icon' => 'home',
                'permission' => 'dashboard.view',
                'order' => 1,
            ],
            [
                'label' => 'Sistema',
                'icon' => 'cog',
                'order' => 90,
                'children' => [
                    [
                        'label' => 'Usuarios',
                        'route' => 'users.index',
                        'icon' => 'users',
                        'permission' => 'users.view',
                    ],
                    [
                        'label' => 'Roles',
                        'route' => 'roles.index',
                        'icon' => 'shield-check',
                        'permission' => 'roles.view',
                    ],
                    [
                        'label' => 'Configuración',
                        'route' => 'company-settings.index',
                        'icon' => 'cog-8-tooth',
                        'permission' => 'settings.view',
                    ],
                    [
                        'label' => 'Backups',
                        'route' => 'backups.index',
                        'icon' => 'archive-box',
                        'permission' => 'backups.view',
                    ],
                    [
                        'label' => 'Auditoría',
                        'route' => 'audit.index',
                        'icon' => 'clipboard-document-list',
                        'permission' => 'audit.view',
                    ],
                    [
                        'label' => 'Notificaciones',
                        'route' => 'notifications.index',
                        'icon' => 'bell',
                        'permission' => 'notifications.view',
                    ],
                ]
            ],
        ];
    }

    /**
     * Widgets para el dashboard
     */
    public function getWidgets(): array
    {
        return [
            [
                'name' => 'system_health',
                'title' => 'Estado del Sistema',
                'component' => 'SystemHealthWidget',
                'size' => 'col-span-2',
                'permission' => 'dashboard.view',
                'order' => 100,
            ],
            [
                'name' => 'recent_activity',
                'title' => 'Actividad Reciente',
                'component' => 'RecentActivityWidget',
                'size' => 'col-span-1',
                'permission' => 'dashboard.view',
                'order' => 101,
            ],
        ];
    }

    /**
     * Configuración por defecto del módulo
     */
    public function getDefaultSettings(): array
    {
        return [
            'dashboard_refresh_interval' => 300, // 5 minutos
            'session_timeout' => 3600, // 1 hora
            'password_min_length' => 8,
            'password_require_special_chars' => true,
            'enable_2fa' => false,
            'audit_log_retention_days' => 90,
        ];
    }

    /**
     * Instalar módulo para un tenant
     */
    public function install($tenant): void
    {
        // El módulo core siempre está disponible
        // No requiere instalación específica
    }

    /**
     * Desinstalar módulo de un tenant
     */
    public function uninstall($tenant): void
    {
        // El módulo core no se puede desinstalar
        throw new \Exception('El módulo core no se puede desinstalar');
    }

    /**
     * Actualizar módulo
     */
    public function update(string $fromVersion): void
    {
        // Lógica de actualización según la versión
        switch ($fromVersion) {
            case '0.9.0':
                $this->updateFrom090();
                break;
            // Agregar más versiones según sea necesario
        }
    }

    /**
     * Actualizar desde versión 0.9.0
     */
    private function updateFrom090(): void
    {
        // Migrar configuraciones anteriores
        // Actualizar esquemas de base de datos si es necesario
        // Etc.
    }
}