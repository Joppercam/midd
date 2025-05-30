<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\TenantUsageStatistic;
use App\Models\User;
use App\Services\SuperAdmin\SystemMonitoringService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    protected $monitoringService;

    public function __construct(SystemMonitoringService $monitoringService)
    {
        $this->monitoringService = $monitoringService;
    }

    public function index()
    {
        $metrics = $this->monitoringService->getSystemMetrics();
        
        $stats = [
            'total_tenants' => Tenant::count(),
            'active_tenants' => Tenant::where('is_active', true)->count(),
            'trial_tenants' => Tenant::where('subscription_status', 'trial')->count(),
            'suspended_tenants' => Tenant::where('is_active', false)->count(),
            'total_users' => User::count(),
            'total_revenue' => $this->calculateTotalRevenue(),
            'mrr' => $this->calculateMRR(),
            'growth_rate' => $this->calculateGrowthRate(),
        ];

        $recentActivities = auth()->guard('super_admin')->user()
            ->activityLogs()
            ->with('tenant')
            ->latest()
            ->take(10)
            ->get();

        $topTenants = Tenant::withCount(['users', 'taxDocuments'])
            ->orderBy('tax_documents_count', 'desc')
            ->take(10)
            ->get();

        $revenueChart = $this->getRevenueChartData();
        $tenantsChart = $this->getTenantsChartData();

        return Inertia::render('SuperAdmin/Dashboard', [
            'stats' => $stats,
            'metrics' => $metrics,
            'recentActivities' => $recentActivities,
            'topTenants' => $topTenants,
            'revenueChart' => $revenueChart,
            'tenantsChart' => $tenantsChart,
        ]);
    }

    private function calculateTotalRevenue()
    {
        return TenantSubscription::where('status', 'active')
            ->sum('amount');
    }

    private function calculateMRR()
    {
        return TenantSubscription::where('status', 'active')
            ->where('billing_cycle', 'monthly')
            ->sum('amount');
    }

    private function calculateGrowthRate()
    {
        $currentMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();

        $currentMonthTenants = Tenant::where('created_at', '>=', $currentMonth)->count();
        $lastMonthTenants = Tenant::whereBetween('created_at', [$lastMonth, $currentMonth])->count();

        if ($lastMonthTenants == 0) {
            return $currentMonthTenants * 100;
        }

        return round((($currentMonthTenants - $lastMonthTenants) / $lastMonthTenants) * 100, 2);
    }

    private function getRevenueChartData()
    {
        $data = [];
        
        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $revenue = TenantSubscription::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->where('status', 'active')
                ->sum('amount');
            
            $data[] = [
                'month' => $date->format('M Y'),
                'revenue' => $revenue,
            ];
        }

        return $data;
    }

    private function getTenantsChartData()
    {
        $data = [];
        
        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $active = Tenant::where('is_active', true)
                ->whereDate('created_at', '<=', $date->endOfMonth())
                ->count();
            $trial = Tenant::where('subscription_status', 'trial')
                ->whereDate('created_at', '<=', $date->endOfMonth())
                ->count();
            
            $data[] = [
                'month' => $date->format('M Y'),
                'active' => $active,
                'trial' => $trial,
            ];
        }

        return $data;
    }
}