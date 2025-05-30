<?php

namespace App\Modules\CRM;

use App\Core\BaseModule;

class Module extends BaseModule
{
    public function getCode(): string
    {
        return 'crm';
    }

    public function getName(): string
    {
        return 'CRM';
    }

    public function getDescription(): string
    {
        return 'Customer Relationship Management - Gestión completa de clientes, oportunidades de venta, seguimiento de leads y análisis de ventas';
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
            // Customer Management
            'customers.view',
            'customers.create',
            'customers.edit',
            'customers.delete',
            'customers.export',
            'customers.import',
            'customers.merge',
            'customers.statements',
            'customers.credit_limits',
            'customers.payment_terms',
            
            // Customer Communication
            'customers.contact',
            'customers.emails',
            'customers.notes',
            'customers.attachments',
            'customers.history',
            
            // Sales Opportunities
            'opportunities.view',
            'opportunities.create',
            'opportunities.edit',
            'opportunities.delete',
            'opportunities.convert',
            'opportunities.pipeline',
            
            // Lead Management
            'leads.view',
            'leads.create',
            'leads.edit',
            'leads.delete',
            'leads.assign',
            'leads.convert',
            'leads.score',
            
            // CRM Analytics
            'crm.reports',
            'crm.analytics',
            'crm.dashboard',
            'crm.forecasting',
            
            // Customer Service
            'customer_service.tickets',
            'customer_service.support',
            'customer_service.satisfaction',
        ];
    }

    public function getRoutes(): string
    {
        return __DIR__ . '/routes.php';
    }

    public function getConfig(): array
    {
        return [
            'lead_scoring' => [
                'enabled' => true,
                'auto_assignment' => true,
                'score_thresholds' => [
                    'hot' => 80,
                    'warm' => 60,
                    'cold' => 40
                ]
            ],
            'opportunity_stages' => [
                'prospecting',
                'qualification',
                'proposal',
                'negotiation',
                'closed_won',
                'closed_lost'
            ],
            'customer_categories' => [
                'premium',
                'standard',
                'basic',
                'prospect'
            ],
            'communication_preferences' => [
                'email',
                'phone',
                'whatsapp',
                'in_person'
            ]
        ];
    }

    public function boot(): void
    {
        // Register CRM services
        app()->bind('CustomerService', \App\Modules\CRM\Services\CustomerService::class);
        app()->bind('OpportunityService', \App\Modules\CRM\Services\OpportunityService::class);
        app()->bind('LeadScoringService', \App\Modules\CRM\Services\LeadScoringService::class);
    }
}