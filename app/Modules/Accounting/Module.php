<?php

namespace App\Modules\Accounting;

use App\Core\BaseModule;

class Module extends BaseModule
{
    public function getName(): string
    {
        return 'Contabilidad y Gastos';
    }

    public function getCode(): string
    {
        return 'accounting';
    }

    public function getDescription(): string
    {
        return 'Gestión completa de contabilidad, gastos y reportes financieros';
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
            // Expenses
            'expenses.view',
            'expenses.create',
            'expenses.edit',
            'expenses.delete',
            'expenses.approve',
            'expenses.reject',
            'expenses.export',
            'expenses.reports',
            
            // Chart of Accounts
            'chart_accounts.view',
            'chart_accounts.create',
            'chart_accounts.edit',
            'chart_accounts.delete',
            'chart_accounts.import',
            'chart_accounts.export',
            
            // Journal Entries
            'journal_entries.view',
            'journal_entries.create',
            'journal_entries.edit',
            'journal_entries.delete',
            'journal_entries.post',
            'journal_entries.reverse',
            'journal_entries.export',
            
            // Financial Reports
            'financial_reports.view',
            'financial_reports.generate',
            'financial_reports.export',
            'financial_reports.schedule',
            
            // Accounting Exports
            'accounting_exports.view',
            'accounting_exports.create',
            'accounting_exports.download',
            'accounting_exports.history',
            
            // Budget Management
            'budgets.view',
            'budgets.create',
            'budgets.edit',
            'budgets.delete',
            'budgets.approve',
            'budgets.reports',
            
            // Tax Management
            'tax_management.view',
            'tax_management.configure',
            'tax_management.calculate',
            'tax_management.reports',
            
            // Financial Analysis
            'financial_analysis.view',
            'financial_analysis.reports',
            'financial_analysis.forecasting',
        ];
    }

    public function getRoutePrefix(): string
    {
        return 'accounting';
    }

    public function getMiddleware(): array
    {
        return [
            'auth',
            'verified',
            'check.subscription',
            'check.module:accounting'
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
        app()->singleton(\App\Modules\Accounting\Services\ExpenseService::class);
        app()->singleton(\App\Modules\Accounting\Services\ChartOfAccountsService::class);
        app()->singleton(\App\Modules\Accounting\Services\JournalEntryService::class);
        app()->singleton(\App\Modules\Accounting\Services\FinancialReportService::class);
        app()->singleton(\App\Modules\Accounting\Services\AccountingExportService::class);
    }
}