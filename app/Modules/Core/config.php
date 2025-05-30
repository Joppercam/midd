<?php

return [
    'name' => 'Núcleo del Sistema',
    'description' => 'Funcionalidades básicas del sistema',
    'version' => '1.0.0',
    'author' => 'CrecePyme Team',
    'is_core' => true,
    
    'settings' => [
        'dashboard_refresh_interval' => [
            'type' => 'number',
            'default' => 300,
            'min' => 60,
            'max' => 3600,
            'description' => 'Intervalo de actualización del dashboard (segundos)',
        ],
        'session_timeout' => [
            'type' => 'number',
            'default' => 3600,
            'min' => 900,
            'max' => 28800,
            'description' => 'Tiempo de expiración de sesión (segundos)',
        ],
        'password_min_length' => [
            'type' => 'number',
            'default' => 8,
            'min' => 6,
            'max' => 20,
            'description' => 'Longitud mínima de contraseña',
        ],
        'password_require_special_chars' => [
            'type' => 'boolean',
            'default' => true,
            'description' => 'Requerir caracteres especiales en contraseñas',
        ],
        'enable_2fa' => [
            'type' => 'boolean',
            'default' => false,
            'description' => 'Habilitar autenticación de dos factores',
        ],
        'audit_log_retention_days' => [
            'type' => 'number',
            'default' => 90,
            'min' => 30,
            'max' => 365,
            'description' => 'Días de retención de logs de auditoría',
        ],
    ],
    
    'routes' => [
        'web' => [
            '/dashboard' => 'DashboardController@index',
            '/users' => 'UserController@index',
            '/roles' => 'RoleController@index',
            '/settings' => 'SettingsController@index',
        ],
        'api' => [
            '/dashboard/stats' => 'Api\DashboardController@stats',
            '/users' => 'Api\UserController@index',
        ],
    ],
    
    'permissions' => [
        // Dashboard
        'dashboard.view' => 'Ver dashboard',
        
        // Users
        'users.view' => 'Ver usuarios',
        'users.create' => 'Crear usuarios',
        'users.edit' => 'Editar usuarios',
        'users.delete' => 'Eliminar usuarios',
        'users.impersonate' => 'Suplantar usuarios',
        
        // Roles
        'roles.view' => 'Ver roles',
        'roles.create' => 'Crear roles',
        'roles.edit' => 'Editar roles',
        'roles.delete' => 'Eliminar roles',
        
        // Settings
        'settings.view' => 'Ver configuración',
        'settings.edit' => 'Editar configuración',
        
        // Profile
        'profile.edit' => 'Editar perfil',
        
        // Backups
        'backups.view' => 'Ver backups',
        'backups.create' => 'Crear backups',
        'backups.download' => 'Descargar backups',
        'backups.delete' => 'Eliminar backups',
        'backups.restore' => 'Restaurar backups',
        'backups.schedule' => 'Programar backups',
        
        // Audit
        'audit.view' => 'Ver auditoría',
        'audit.export' => 'Exportar auditoría',
        'audit.manage' => 'Gestionar auditoría',
        
        // Notifications
        'notifications.view' => 'Ver notificaciones',
        'notifications.manage' => 'Gestionar notificaciones',
        'notifications.send' => 'Enviar notificaciones',
        'notifications.broadcast' => 'Enviar notificaciones masivas',
        'notifications.test' => 'Probar notificaciones',
        'notifications.delete' => 'Eliminar notificaciones',
    ],
    
    'widgets' => [
        'system_health' => [
            'title' => 'Estado del Sistema',
            'component' => 'SystemHealthWidget',
            'size' => 'large',
            'refresh_interval' => 300,
        ],
        'recent_activity' => [
            'title' => 'Actividad Reciente',
            'component' => 'RecentActivityWidget',
            'size' => 'medium',
            'refresh_interval' => 60,
        ],
    ],
];