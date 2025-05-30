<?php

namespace App\Modules\Analytics;

use App\Core\BaseModule;

class Module extends BaseModule
{
    protected string $name = 'Analytics';
    protected string $code = 'analytics';
    protected string $description = 'Advanced analytics and reporting module with dashboards, KPIs, and data visualization';
    protected string $version = '1.0.0';
    protected ?string $icon = 'chart-line';

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getDependencies(): array
    {
        return ['core'];
    }

    public function getPermissions(): array
    {
        return [
            'analytics.view' => 'View analytics and reports',
            'analytics.export' => 'Export analytics data',
            'analytics.configure' => 'Configure analytics settings',
            'analytics.dashboards.create' => 'Create custom dashboards',
            'analytics.dashboards.edit' => 'Edit dashboards',
            'analytics.dashboards.delete' => 'Delete dashboards',
            'analytics.reports.generate' => 'Generate custom reports',
            'analytics.reports.schedule' => 'Schedule automated reports',
            'analytics.kpis.manage' => 'Manage KPIs and metrics',
        ];
    }

    public function getMenuItems(): array
    {
        return [
            [
                'title' => 'Analytics',
                'route' => 'analytics.dashboard',
                'icon' => 'chart-line',
                'permission' => 'analytics.view',
                'position' => 50,
                'children' => [
                    [
                        'title' => 'Dashboard',
                        'route' => 'analytics.dashboard',
                        'permission' => 'analytics.view',
                    ],
                    [
                        'title' => 'Reports',
                        'route' => 'analytics.reports.index',
                        'permission' => 'analytics.view',
                    ],
                    [
                        'title' => 'KPIs',
                        'route' => 'analytics.kpis.index',
                        'permission' => 'analytics.kpis.manage',
                    ],
                    [
                        'title' => 'Custom Dashboards',
                        'route' => 'analytics.dashboards.index',
                        'permission' => 'analytics.dashboards.create',
                    ],
                    [
                        'title' => 'Settings',
                        'route' => 'analytics.settings',
                        'permission' => 'analytics.configure',
                    ],
                ],
            ],
        ];
    }

    public function getWidgets(): array
    {
        return [
            [
                'name' => 'revenue-trend',
                'component' => 'Analytics/RevenueTrendWidget',
                'title' => 'Revenue Trend',
                'description' => 'Revenue trends over time',
                'permissions' => ['analytics.view'],
                'size' => 'medium',
            ],
            [
                'name' => 'top-products',
                'component' => 'Analytics/TopProductsWidget',
                'title' => 'Top Products',
                'description' => 'Best selling products',
                'permissions' => ['analytics.view'],
                'size' => 'small',
            ],
            [
                'name' => 'customer-insights',
                'component' => 'Analytics/CustomerInsightsWidget',
                'title' => 'Customer Insights',
                'description' => 'Customer behavior analytics',
                'permissions' => ['analytics.view'],
                'size' => 'large',
            ],
            [
                'name' => 'kpi-summary',
                'component' => 'Analytics/KPISummaryWidget',
                'title' => 'KPI Summary',
                'description' => 'Key performance indicators',
                'permissions' => ['analytics.view'],
                'size' => 'medium',
            ],
        ];
    }

    public function getSettings(): array
    {
        return [
            'analytics.retention_days' => [
                'type' => 'number',
                'default' => 365,
                'label' => 'Data retention (days)',
                'description' => 'How long to keep analytics data',
                'validation' => 'required|integer|min:30|max:1825',
            ],
            'analytics.default_date_range' => [
                'type' => 'select',
                'default' => 'last_30_days',
                'label' => 'Default date range',
                'options' => [
                    'today' => 'Today',
                    'yesterday' => 'Yesterday',
                    'last_7_days' => 'Last 7 days',
                    'last_30_days' => 'Last 30 days',
                    'this_month' => 'This month',
                    'last_month' => 'Last month',
                    'this_year' => 'This year',
                ],
            ],
            'analytics.enable_realtime' => [
                'type' => 'boolean',
                'default' => true,
                'label' => 'Enable real-time analytics',
                'description' => 'Show real-time data updates',
            ],
            'analytics.export_formats' => [
                'type' => 'multiselect',
                'default' => ['pdf', 'excel', 'csv'],
                'label' => 'Available export formats',
                'options' => [
                    'pdf' => 'PDF',
                    'excel' => 'Excel',
                    'csv' => 'CSV',
                    'json' => 'JSON',
                ],
            ],
        ];
    }

    public function install($tenant): void
    {
        // Create default KPIs
        $this->createDefaultKPIs();
        
        // Create default dashboards
        $this->createDefaultDashboards();
        
        // Initialize analytics tracking
        $this->initializeTracking();
    }

    public function uninstall($tenant): void
    {
        // Clean up analytics data if requested
        if (config('modules.analytics.cleanup_on_uninstall', false)) {
            $this->cleanupAnalyticsData();
        }
    }

    protected function createDefaultKPIs(): void
    {
        // TODO: Create default KPI definitions
    }

    protected function createDefaultDashboards(): void
    {
        // TODO: Create default dashboard templates
    }

    protected function initializeTracking(): void
    {
        // TODO: Initialize analytics tracking
    }

    protected function cleanupAnalyticsData(): void
    {
        // TODO: Clean up analytics data
    }
}