<?php

namespace App\Modules\Invoicing;

use App\Core\BaseModule;

class Module extends BaseModule
{
    public function getName(): string
    {
        return 'Facturación y Pagos';
    }

    public function getCode(): string
    {
        return 'invoicing';
    }

    public function getDescription(): string
    {
        return 'Gestión completa de facturación, pagos y integración con SII';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDependencies(): array
    {
        return ['core'];
    }

    public function getPermissions(): array
    {
        return [
            // Payments
            'payments.view',
            'payments.create',
            'payments.edit',
            'payments.delete',
            'payments.allocate',
            'payments.export',
            
            // Tax Documents (Invoices)
            'invoices.view',
            'invoices.create',
            'invoices.edit',
            'invoices.delete',
            'invoices.send',
            'invoices.cancel',
            'invoices.download',
            'invoices.print',
            'invoices.email',
            
            // SII Integration
            'sii.configuration',
            'sii.certificates',
            'sii.send_documents',
            'sii.query_status',
            'sii.download_schemas',
            'sii.environment_management',
            'sii.folio_management',
            
            // Billing Reports
            'billing.reports.view',
            'billing.reports.export',
            'billing.statements.view',
            'billing.statements.send',
        ];
    }

    public function getRoutePrefix(): string
    {
        return 'invoicing';
    }

    public function getMiddleware(): array
    {
        return [
            'auth',
            'verified',
            'check.subscription',
            'check.module:invoicing'
        ];
    }

    public function boot(): void
    {
        // Load routes
        $this->loadRoutes();
        
        // Register services
        $this->registerServices();
    }

    protected function loadRoutes(): void
    {
        if (file_exists($routesPath = __DIR__ . '/routes.php')) {
            require $routesPath;
        }
    }

    protected function registerServices(): void
    {
        app()->singleton(\App\Modules\Invoicing\Services\PaymentService::class);
        app()->singleton(\App\Modules\Invoicing\Services\InvoiceService::class);
        app()->singleton(\App\Modules\Invoicing\Services\SIIIntegrationService::class);
    }
}