<?php

namespace App\Services\SuperAdmin;

use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\TenantSubscription;
use App\Models\SubscriptionPlan;
use App\Models\SystemModule;
use App\Models\TenantUsageStatistic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TenantManagementService
{
    /**
     * Create a new tenant with default settings
     */
    public function createTenant(array $data): Tenant
    {
        $plan = SubscriptionPlan::where('code', $data['plan'])->firstOrFail();
        
        // Ensure limits and features are arrays (handle casting issues)
        $limits = is_array($plan->limits) ? $plan->limits : json_decode($plan->limits, true);
        $features = is_array($plan->features) ? $plan->features : json_decode($plan->features, true);
        
        $tenant = Tenant::create([
            'id' => Str::uuid(),
            'name' => $data['name'],
            'legal_name' => $data['legal_name'],
            'trade_name' => $data['name'],
            'rut' => $data['rut'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'domain' => $data['domain'] ?? null,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays($plan->trial_days ?? 14),
            'plan' => $plan->code,
            'max_users' => $limits['max_users'] ?? 1,
            'max_documents_per_month' => $limits['max_documents'] ?? 100,
            'max_products' => $limits['max_products'] ?? 100,
            'max_customers' => $limits['max_customers'] ?? 100,
            'api_access' => $limits['api_access'] ?? false,
            'multi_branch' => $limits['multi_branch'] ?? false,
            'features' => $features,
            'settings' => $this->getDefaultSettings(),
            'is_active' => true,
            'currency' => 'CLP',
            'timezone' => 'America/Santiago',
            'date_format' => 'd/m/Y',
            'time_format' => 'H:i',
            'fiscal_year_start_month' => 1,
        ]);

        // Create subscription record
        TenantSubscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'trial',
            'trial_ends_at' => $tenant->trial_ends_at,
            'started_at' => now(),
            'current_period_start' => now(),
            'current_period_end' => now()->addDays($plan->trial_days ?? 14),
            'billing_cycle' => 'monthly',
            'monthly_amount' => 0, // Free during trial
        ]);

        // Assign default modules based on plan
        $this->assignDefaultModules($tenant, $plan, $features);

        // Initialize usage statistics
        $this->initializeUsageStatistics($tenant);

        return $tenant;
    }

    /**
     * Get detailed usage information for a tenant
     */
    public function getTenantUsageDetails(Tenant $tenant): array
    {
        $currentMonth = now()->format('Y-m');
        
        return [
            'users' => [
                'current' => $tenant->users()->count(),
                'limit' => $tenant->max_users,
                'percentage' => ($tenant->users()->count() / $tenant->max_users) * 100,
            ],
            'documents' => [
                'current' => $tenant->taxDocuments()
                    ->whereRaw("strftime('%Y-%m', created_at) = ?", [$currentMonth])
                    ->count(),
                'limit' => $tenant->max_documents_per_month,
                'percentage' => ($tenant->taxDocuments()
                    ->whereRaw("strftime('%Y-%m', created_at) = ?", [$currentMonth])
                    ->count() / $tenant->max_documents_per_month) * 100,
            ],
            'products' => [
                'current' => $tenant->products()->count(),
                'limit' => $tenant->max_products,
                'percentage' => ($tenant->products()->count() / $tenant->max_products) * 100,
            ],
            'customers' => [
                'current' => $tenant->customers()->count(),
                'limit' => $tenant->max_customers,
                'percentage' => ($tenant->customers()->count() / $tenant->max_customers) * 100,
            ],
            'storage' => $this->calculateStorageUsage($tenant),
            'api_calls' => $this->getApiUsage($tenant),
        ];
    }

    /**
     * Get recent activities for a tenant
     */
    public function getTenantActivities(Tenant $tenant, int $limit = 20): array
    {
        $activities = collect();

        // Get recent logins
        $recentLogins = $tenant->users()
            ->whereNotNull('last_login_at')
            ->orderBy('last_login_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($user) {
                return [
                    'type' => 'login',
                    'description' => "User {$user->name} logged in",
                    'user' => $user->name,
                    'timestamp' => $user->last_login_at,
                ];
            });

        // Get recent documents
        $recentDocuments = $tenant->taxDocuments()
            ->with('user')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($doc) {
                return [
                    'type' => 'document',
                    'description' => "Created {$doc->type} #{$doc->number}",
                    'user' => $doc->user->name ?? 'System',
                    'timestamp' => $doc->created_at,
                ];
            });

        // Merge and sort by timestamp
        return $activities
            ->merge($recentLogins)
            ->merge($recentDocuments)
            ->sortByDesc('timestamp')
            ->take($limit)
            ->values()
            ->toArray();
    }

    /**
     * Get revenue information for a tenant
     */
    public function getTenantRevenue(Tenant $tenant): array
    {
        $subscriptions = $tenant->subscriptions()
            ->with('plan')
            ->orderBy('created_at', 'desc')
            ->get();

        $totalRevenue = $subscriptions
            ->where('status', 'active')
            ->sum('amount');

        $monthlyRevenue = $subscriptions
            ->where('status', 'active')
            ->where('billing_cycle', 'monthly')
            ->sum('amount');

        $yearlyRevenue = $subscriptions
            ->where('status', 'active')
            ->where('billing_cycle', 'yearly')
            ->sum('amount');

        return [
            'total' => $totalRevenue,
            'monthly' => $monthlyRevenue,
            'yearly' => $yearlyRevenue,
            'history' => $this->getRevenueHistory($tenant),
            'current_plan' => $tenant->subscription?->plan,
        ];
    }

    /**
     * Calculate storage usage for a tenant
     */
    private function calculateStorageUsage(Tenant $tenant): array
    {
        // This would typically check actual file storage
        // For now, we'll estimate based on document count
        $documentCount = $tenant->taxDocuments()->count();
        $estimatedSizeMB = $documentCount * 0.5; // Assume 0.5MB per document average

        return [
            'used_mb' => round($estimatedSizeMB, 2),
            'limit_mb' => 1024, // 1GB default
            'percentage' => ($estimatedSizeMB / 1024) * 100,
        ];
    }

    /**
     * Get API usage statistics
     */
    private function getApiUsage(Tenant $tenant): array
    {
        $currentMonth = now()->startOfMonth();
        
        // Get from API logs or usage statistics
        $apiCalls = TenantUsageStatistic::where('tenant_id', $tenant->id)
            ->where('metric_type', 'api_calls')
            ->where('date', '>=', $currentMonth)
            ->sum('value');

        return [
            'calls_this_month' => $apiCalls,
            'limit' => $tenant->api_access ? 10000 : 0,
            'percentage' => $tenant->api_access ? ($apiCalls / 10000) * 100 : 0,
        ];
    }

    /**
     * Get revenue history for the last 12 months
     */
    private function getRevenueHistory(Tenant $tenant): array
    {
        $history = [];
        
        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $revenue = TenantSubscription::where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->sum('amount');
            
            $history[] = [
                'month' => $date->format('M Y'),
                'revenue' => $revenue,
            ];
        }

        return $history;
    }

    /**
     * Get default settings for new tenants
     */
    private function getDefaultSettings(): array
    {
        return [
            'invoice_prefix' => 'INV-',
            'quote_prefix' => 'QTE-',
            'payment_terms' => 30,
            'tax_rate' => 19,
            'enable_notifications' => true,
            'enable_auto_backups' => true,
            'backup_retention_days' => 30,
        ];
    }

    /**
     * Assign default modules to a tenant based on their plan
     */
    private function assignDefaultModules(Tenant $tenant, SubscriptionPlan $plan, array $features = []): void
    {
        $defaultModules = ['core', 'invoicing', 'crm'];
        
        if ($features && in_array('inventory', $features)) {
            $defaultModules[] = 'inventory';
        }
        
        if ($features && in_array('accounting', $features)) {
            $defaultModules[] = 'accounting';
        }

        $systemModules = SystemModule::whereIn('slug', $defaultModules)->get();

        foreach ($systemModules as $module) {
            TenantModule::create([
                'tenant_id' => $tenant->id,
                'system_module_id' => $module->id,
                'is_enabled' => true,
                'settings' => [],
            ]);
        }
    }

    /**
     * Initialize usage statistics for a new tenant
     */
    private function initializeUsageStatistics(Tenant $tenant): void
    {
        $metrics = [
            'users_count' => 0,
            'invoices_count' => 0,
            'storage_mb' => 0,
            'api_calls' => 0,
        ];

        foreach ($metrics as $metric => $value) {
            TenantUsageStatistic::create([
                'tenant_id' => $tenant->id,
                'date' => now()->toDateString(),
                'metric_type' => $metric,
                'value' => $value,
            ]);
        }
    }
}