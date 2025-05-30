<?php

namespace App\Modules\Analytics\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Analytics\Services\AnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    protected AnalyticsService $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->middleware('permission:analytics.view');
        $this->analyticsService = $analyticsService;
    }

    /**
     * Display the analytics dashboard
     */
    public function index(Request $request)
    {
        $dateRange = $request->get('range', config('modules.analytics.settings.default_date_range'));
        $startDate = $this->getStartDate($dateRange);
        $endDate = now();

        // Get key metrics
        $metrics = $this->analyticsService->getKeyMetrics($startDate, $endDate);
        
        // Get chart data
        $revenueChart = $this->analyticsService->getRevenueChart($startDate, $endDate);
        $ordersChart = $this->analyticsService->getOrdersChart($startDate, $endDate);
        $customersChart = $this->analyticsService->getCustomersChart($startDate, $endDate);
        
        // Get top lists
        $topProducts = $this->analyticsService->getTopProducts($startDate, $endDate, 10);
        $topCustomers = $this->analyticsService->getTopCustomers($startDate, $endDate, 10);
        
        // Get recent activity
        $recentActivity = $this->analyticsService->getRecentActivity(20);

        return Inertia::render('Analytics/Dashboard', [
            'metrics' => $metrics,
            'charts' => [
                'revenue' => $revenueChart,
                'orders' => $ordersChart,
                'customers' => $customersChart,
            ],
            'topProducts' => $topProducts,
            'topCustomers' => $topCustomers,
            'recentActivity' => $recentActivity,
            'dateRange' => $dateRange,
            'availableRanges' => $this->getAvailableDateRanges(),
        ]);
    }

    /**
     * Get specific metric data via AJAX
     */
    public function getMetricData(Request $request, string $metric)
    {
        $dateRange = $request->get('range', config('modules.analytics.settings.default_date_range'));
        $startDate = $this->getStartDate($dateRange);
        $endDate = now();

        $data = match($metric) {
            'revenue' => $this->analyticsService->getRevenueData($startDate, $endDate),
            'orders' => $this->analyticsService->getOrdersData($startDate, $endDate),
            'customers' => $this->analyticsService->getCustomersData($startDate, $endDate),
            'products' => $this->analyticsService->getProductsData($startDate, $endDate),
            'conversion' => $this->analyticsService->getConversionData($startDate, $endDate),
            default => throw new \InvalidArgumentException("Unknown metric: {$metric}"),
        };

        return response()->json([
            'metric' => $metric,
            'data' => $data,
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
        ]);
    }

    /**
     * Get start date based on range
     */
    protected function getStartDate(string $range): \Carbon\Carbon
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

    /**
     * Get available date ranges
     */
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
}