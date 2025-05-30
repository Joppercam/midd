<?php

namespace App\Modules\Banking;

use App\Core\BaseModule;

class Module extends BaseModule
{
    /**
     * Código único del módulo
     */
    public function getCode(): string
    {
        return 'banking';
    }

    /**
     * Nombre del módulo
     */
    public function getName(): string
    {
        return 'Conciliación Bancaria';
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
        return 'Gestión de cuentas bancarias, importación de extractos y conciliación automática de transacciones.';
    }

    /**
     * Dependencias del módulo
     */
    public function getDependencies(): array
    {
        return ['core']; // Requiere el módulo core
    }

    /**
     * Permisos que provee el módulo
     */
    public function getPermissions(): array
    {
        return [
            // Bank accounts
            'bank_accounts.view' => 'Ver cuentas bancarias',
            'bank_accounts.create' => 'Crear cuentas bancarias',
            'bank_accounts.edit' => 'Editar cuentas bancarias',
            'bank_accounts.delete' => 'Eliminar cuentas bancarias',
            
            // Bank transactions
            'bank_transactions.view' => 'Ver transacciones bancarias',
            'bank_transactions.import' => 'Importar extractos bancarios',
            'bank_transactions.edit' => 'Editar transacciones bancarias',
            'bank_transactions.delete' => 'Eliminar transacciones bancarias',
            
            // Reconciliation
            'bank_reconciliation.view' => 'Ver conciliaciones',
            'bank_reconciliation.create' => 'Crear conciliaciones',
            'bank_reconciliation.reconcile' => 'Realizar conciliación',
            'bank_reconciliation.approve' => 'Aprobar conciliaciones',
            'bank_reconciliation.export' => 'Exportar reportes',
            'bank_reconciliation.manage' => 'Gestionar conciliaciones',
        ];
    }

    /**
     * Items de menú del módulo
     */
    public function getMenuItems(): array
    {
        return [
            [
                'label' => 'Banca',
                'icon' => 'building-library',
                'route' => 'banking.index',
                'permission' => 'bank_accounts.view',
                'order' => 40,
                'children' => [
                    [
                        'label' => 'Resumen',
                        'route' => 'banking.index',
                        'icon' => 'chart-pie',
                        'permission' => 'bank_accounts.view',
                    ],
                    [
                        'label' => 'Cuentas',
                        'route' => 'banking.accounts',
                        'icon' => 'credit-card',
                        'permission' => 'bank_accounts.view',
                    ],
                    [
                        'label' => 'Transacciones',
                        'route' => 'banking.transactions',
                        'icon' => 'arrow-path',
                        'permission' => 'bank_transactions.view',
                    ],
                    [
                        'label' => 'Conciliación',
                        'route' => 'banking.reconcile',
                        'icon' => 'check-badge',
                        'permission' => 'bank_reconciliation.view',
                    ],
                    [
                        'label' => 'Reportes',
                        'route' => 'banking.reports',
                        'icon' => 'document-chart-bar',
                        'permission' => 'bank_reconciliation.export',
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
                'name' => 'bank_balance',
                'title' => 'Balance Bancario',
                'component' => 'BankBalanceWidget',
                'size' => 'col-span-2',
                'permission' => 'bank_accounts.view',
                'order' => 30,
            ],
            [
                'name' => 'pending_reconciliations',
                'title' => 'Conciliaciones Pendientes',
                'component' => 'PendingReconciliationsWidget',
                'size' => 'col-span-1',
                'permission' => 'bank_reconciliation.view',
                'order' => 31,
            ],
        ];
    }

    /**
     * Configuración por defecto del módulo
     */
    public function getDefaultSettings(): array
    {
        return [
            'auto_match_tolerance_days' => 3,
            'auto_match_tolerance_amount' => 0.01,
            'import_formats' => ['csv', 'ofx', 'xlsx'],
            'default_reconciliation_period' => 'monthly',
            'enable_auto_categorization' => true,
            'retention_days' => 365,
        ];
    }

    /**
     * Instalar módulo para un tenant
     */
    public function install($tenant): void
    {
        // Crear configuraciones por defecto para el tenant
        $settings = $this->getDefaultSettings();
        foreach ($settings as $key => $value) {
            $tenant->setSetting("banking.{$key}", $value);
        }

        // Log de instalación
        activity()
            ->performedOn($tenant)
            ->causedBy(auth()->user())
            ->withProperties(['module' => 'banking'])
            ->log('Módulo Banking instalado');
    }

    /**
     * Desinstalar módulo de un tenant
     */
    public function uninstall($tenant): void
    {
        // Verificar que no haya datos críticos
        $hasTransactions = \App\Models\BankTransaction::where('tenant_id', $tenant->id)->exists();
        if ($hasTransactions) {
            throw new \Exception('No se puede desinstalar el módulo Banking mientras existan transacciones bancarias.');
        }

        // Eliminar configuraciones
        $tenant->clearSettings('banking.*');

        // Log de desinstalación
        activity()
            ->performedOn($tenant)
            ->causedBy(auth()->user())
            ->withProperties(['module' => 'banking'])
            ->log('Módulo Banking desinstalado');
    }

    /**
     * Actualizar módulo
     */
    public function update(string $fromVersion): void
    {
        // Lógica de actualización según la versión
        switch ($fromVersion) {
            case '0.9.0':
                // Migrar datos antiguos si es necesario
                break;
        }
    }
}