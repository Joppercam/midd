<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\TaxDocument;
use App\Models\User;
use App\Models\SuperAdminActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * System analytics dashboard
     */
    public function index(Request $request)
    {
        $range = $request->get('range', 'last_30_days');
        $startDate = $this->getStartDate($range);
        $endDate = now();

        $metrics = $this->getSystemMetrics($startDate, $endDate);
        $charts = $this->getChartData($startDate, $endDate);
        $growth = $this->getGrowthMetrics($startDate, $endDate);
        $revenue = $this->getRevenueMetrics($startDate, $endDate);

        return Inertia::render('SuperAdmin/Analytics/Index', [
            'metrics' => $metrics,
            'charts' => $charts,
            'growth' => $growth,
            'revenue' => $revenue,
            'dateRange' => $range,
            'availableRanges' => $this->getAvailableDateRanges(),
        ]);
    }

    /**
     * Get system-wide metrics
     */
    protected function getSystemMetrics(Carbon $startDate, Carbon $endDate): array
    {
        $previousStart = $startDate->copy()->subDays($startDate->diffInDays($endDate));
        $previousEnd = $startDate->copy()->subDay();

        // Current period
        $currentTenants = Tenant::whereBetween('created_at', [$startDate, $endDate])->count();
        $currentRevenue = TenantSubscription::where('status', 'active')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');
        $currentUsers = User::whereBetween('created_at', [$startDate, $endDate])->count();
        $currentDocuments = TaxDocument::whereBetween('created_at', [$startDate, $endDate])->count();

        // Previous period
        $previousTenants = Tenant::whereBetween('created_at', [$previousStart, $previousEnd])->count();
        $previousRevenue = TenantSubscription::where('status', 'active')
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->sum('amount');
        $previousUsers = User::whereBetween('created_at', [$previousStart, $previousEnd])->count();
        $previousDocuments = TaxDocument::whereBetween('created_at', [$previousStart, $previousEnd])->count();

        return [
            'new_tenants' => [
                'value' => $currentTenants,
                'change' => $this->calculateChange($previousTenants, $currentTenants),
                'trend' => $this->getTrend($previousTenants, $currentTenants),
            ],
            'revenue' => [
                'value' => $currentRevenue,
                'change' => $this->calculateChange($previousRevenue, $currentRevenue),
                'trend' => $this->getTrend($previousRevenue, $currentRevenue),
            ],
            'new_users' => [
                'value' => $currentUsers,
                'change' => $this->calculateChange($previousUsers, $currentUsers),
                'trend' => $this->getTrend($previousUsers, $currentUsers),
            ],
            'documents_created' => [
                'value' => $currentDocuments,
                'change' => $this->calculateChange($previousDocuments, $currentDocuments),
                'trend' => $this->getTrend($previousDocuments, $currentDocuments),
            ],
            'total_tenants' => Tenant::count(),
            'active_tenants' => Tenant::where('is_active', true)->count(),
            'trial_tenants' => Tenant::where('subscription_status', 'trial')->count(),
            'mrr' => $this->calculateMRR(),
            'churn_rate' => $this->calculateChurnRate(),
        ];
    }

    /**
     * Get chart data for analytics
     */
    protected function getChartData(Carbon $startDate, Carbon $endDate): array
    {
        return [
            'tenant_growth' => $this->getTenantGrowthChart($startDate, $endDate),
            'revenue_trend' => $this->getRevenueTrendChart($startDate, $endDate),
            'usage_by_module' => $this->getModuleUsageChart(),
            'top_plans' => $this->getTopPlansChart(),
        ];
    }

    /**
     * Get tenant growth chart data
     */
    protected function getTenantGrowthChart(Carbon $startDate, Carbon $endDate): array
    {
        $data = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $nextPeriod = $current->copy()->addDay();
            
            $newTenants = Tenant::whereBetween('created_at', [$current, $nextPeriod])
                ->count();
            
            $data[] = [
                'date' => $current->format('Y-m-d'),
                'new_tenants' => $newTenants,
                'total_tenants' => Tenant::where('created_at', '<=', $current)->count(),
            ];
            
            $current->addDay();
        }

        return $data;
    }

    /**
     * Get revenue trend chart data
     */
    protected function getRevenueTrendChart(Carbon $startDate, Carbon $endDate): array
    {
        $data = [];
        $months = [];
        
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months[] = $date->format('M Y');
        }

        foreach ($months as $month) {
            $date = Carbon::createFromFormat('M Y', $month);
            $revenue = TenantSubscription::where('status', 'active')
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->sum('amount');
            
            $data[] = [
                'month' => $month,
                'revenue' => $revenue,
            ];
        }

        return $data;
    }

    /**
     * Get module usage statistics
     */
    protected function getModuleUsageChart(): array
    {
        return DB::table('tenant_modules')
            ->join('system_modules', 'tenant_modules.system_module_id', '=', 'system_modules.id')
            ->where('tenant_modules.is_active', true)
            ->select('system_modules.name', DB::raw('count(*) as usage_count'))
            ->groupBy('system_modules.id', 'system_modules.name')
            ->orderByDesc('usage_count')
            ->get()
            ->toArray();
    }

    /**
     * Get top subscription plans
     */
    protected function getTopPlansChart(): array
    {
        return TenantSubscription::where('status', 'active')
            ->join('subscription_plans', 'tenant_subscriptions.plan_id', '=', 'subscription_plans.id')
            ->select('subscription_plans.name', DB::raw('count(*) as count'), DB::raw('sum(tenant_subscriptions.amount) as revenue'))
            ->groupBy('subscription_plans.id', 'subscription_plans.name')
            ->orderByDesc('count')
            ->get()
            ->toArray();
    }

    /**
     * Get growth metrics
     */
    protected function getGrowthMetrics(Carbon $startDate, Carbon $endDate): array
    {
        $currentMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        return [
            'customer_acquisition_cost' => $this->calculateCAC(),
            'lifetime_value' => $this->calculateLTV(),
            'conversion_rate' => $this->calculateConversionRate(),
            'average_revenue_per_user' => $this->calculateARPU(),
        ];
    }

    /**
     * Get detailed revenue metrics
     */
    protected function getRevenueMetrics(Carbon $startDate, Carbon $endDate): array
    {
        return [
            'total_revenue' => TenantSubscription::where('status', 'active')->sum('amount'),
            'mrr' => $this->calculateMRR(),
            'arr' => $this->calculateMRR() * 12,
            'revenue_by_plan' => $this->getRevenueByPlan(),
            'churn_revenue' => $this->calculateChurnRevenue(),
        ];
    }

    /**
     * Calculate Monthly Recurring Revenue
     */
    protected function calculateMRR(): float
    {
        return TenantSubscription::where('status', 'active')
            ->where('billing_cycle', 'monthly')
            ->sum('amount');
    }

    /**
     * Calculate churn rate
     */
    protected function calculateChurnRate(): float
    {
        $currentMonth = now()->startOfMonth();
        $startOfMonth = Tenant::where('created_at', '<', $currentMonth)
            ->where('is_active', true)
            ->count();
        
        $churned = Tenant::where('suspended_at', '>=', $currentMonth)
            ->count();

        return $startOfMonth > 0 ? round(($churned / $startOfMonth) * 100, 2) : 0;
    }

    /**
     * Calculate Customer Acquisition Cost
     */
    protected function calculateCAC(): float
    {
        // Simplified calculation - you'd want to include actual marketing costs
        $newTenantsThisMonth = Tenant::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        
        // Placeholder marketing cost - should come from actual data
        $marketingCost = 50000; // $50,000 per month

        return $newTenantsThisMonth > 0 ? round($marketingCost / $newTenantsThisMonth, 2) : 0;
    }

    /**
     * Calculate Lifetime Value
     */
    protected function calculateLTV(): float
    {
        $avgMonthlyRevenue = $this->calculateARPU();
        $avgLifetimeMonths = 24; // Placeholder - calculate from actual churn data
        
        return round($avgMonthlyRevenue * $avgLifetimeMonths, 2);
    }

    /**
     * Calculate conversion rate
     */
    protected function calculateConversionRate(): float
    {
        $trialTenants = Tenant::where('subscription_status', 'trial')->count();
        $paidTenants = Tenant::where('subscription_status', 'active')->count();
        $totalTenants = $trialTenants + $paidTenants;

        return $totalTenants > 0 ? round(($paidTenants / $totalTenants) * 100, 2) : 0;
    }

    /**
     * Calculate Average Revenue Per User
     */
    protected function calculateARPU(): float
    {
        $totalRevenue = TenantSubscription::where('status', 'active')->sum('amount');
        $activeTenants = Tenant::where('is_active', true)->count();

        return $activeTenants > 0 ? round($totalRevenue / $activeTenants, 2) : 0;
    }

    /**
     * Get revenue by plan
     */
    protected function getRevenueByPlan(): array
    {
        return TenantSubscription::where('status', 'active')
            ->join('subscription_plans', 'tenant_subscriptions.plan_id', '=', 'subscription_plans.id')
            ->select('subscription_plans.name', DB::raw('sum(tenant_subscriptions.amount) as revenue'))
            ->groupBy('subscription_plans.id', 'subscription_plans.name')
            ->orderByDesc('revenue')
            ->get()
            ->toArray();
    }

    /**
     * Calculate churned revenue
     */
    protected function calculateChurnRevenue(): float
    {
        return TenantSubscription::where('status', 'cancelled')
            ->whereMonth('updated_at', now()->month)
            ->sum('amount');
    }

    // Utility methods
    protected function getStartDate(string $range): Carbon
    {
        return match($range) {
            'today' => now()->startOfDay(),
            'yesterday' => now()->subDay()->startOfDay(),
            'last_7_days' => now()->subDays(7)->startOfDay(),
            'last_30_days' => now()->subDays(30)->startOfDay(),
            'this_month' => now()->startOfMonth(),
            'last_month' => now()->subMonth()->startOfMonth(),
            'this_quarter' => now()->startOfQuarter(),
            'last_quarter' => now()->subQuarter()->startOfQuarter(),
            'this_year' => now()->startOfYear(),
            'last_year' => now()->subYear()->startOfYear(),
            default => now()->subDays(30)->startOfDay(),
        };
    }

    protected function getAvailableDateRanges(): array
    {
        return [
            'today' => 'Today',
            'yesterday' => 'Yesterday',
            'last_7_days' => 'Last 7 days',
            'last_30_days' => 'Last 30 days',
            'this_month' => 'This month',
            'last_month' => 'Last month',
            'this_quarter' => 'This quarter',
            'last_quarter' => 'Last quarter',
            'this_year' => 'This year',
            'last_year' => 'Last year',
        ];
    }

    protected function calculateChange($previous, $current): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        
        return round((($current - $previous) / $previous) * 100, 1);
    }

    protected function getTrend($previous, $current): string
    {
        if ($current > $previous) return 'up';
        if ($current < $previous) return 'down';
        return 'neutral';
    }
}