<?php

namespace App\Modules\Analytics\Services;

use App\Models\TaxDocument;
use App\Models\Customer;
use App\Models\Product;
use App\Models\TaxDocumentItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AnalyticsService
{
    /**
     * Get key metrics for the dashboard
     */
    public function getKeyMetrics(Carbon $startDate, Carbon $endDate): array
    {
        $cacheKey = "analytics.metrics.{$startDate->format('Y-m-d')}.{$endDate->format('Y-m-d')}";
        
        return Cache::remember($cacheKey, 300, function () use ($startDate, $endDate) {
            $currentPeriod = $this->getPeriodMetrics($startDate, $endDate);
            
            // Calculate previous period for comparison
            $periodLength = $startDate->diffInDays($endDate);
            $previousStart = $startDate->copy()->subDays($periodLength);
            $previousEnd = $startDate->copy()->subDay();
            $previousPeriod = $this->getPeriodMetrics($previousStart, $previousEnd);
            
            return [
                'revenue' => [
                    'value' => $currentPeriod['revenue'],
                    'change' => $this->calculateChange($previousPeriod['revenue'], $currentPeriod['revenue']),
                    'trend' => $this->getTrend($previousPeriod['revenue'], $currentPeriod['revenue']),
                ],
                'orders' => [
                    'value' => $currentPeriod['orders'],
                    'change' => $this->calculateChange($previousPeriod['orders'], $currentPeriod['orders']),
                    'trend' => $this->getTrend($previousPeriod['orders'], $currentPeriod['orders']),
                ],
                'customers' => [
                    'value' => $currentPeriod['customers'],
                    'change' => $this->calculateChange($previousPeriod['customers'], $currentPeriod['customers']),
                    'trend' => $this->getTrend($previousPeriod['customers'], $currentPeriod['customers']),
                ],
                'average_order_value' => [
                    'value' => $currentPeriod['orders'] > 0 ? $currentPeriod['revenue'] / $currentPeriod['orders'] : 0,
                    'change' => $this->calculateAOVChange($previousPeriod, $currentPeriod),
                    'trend' => $this->getAOVTrend($previousPeriod, $currentPeriod),
                ],
            ];
        });
    }

    /**
     * Get metrics for a specific period
     */
    protected function getPeriodMetrics(Carbon $startDate, Carbon $endDate): array
    {
        $tenant = tenant();
        
        $revenue = TaxDocument::where('tenant_id', $tenant->id)
            ->whereIn('status', ['accepted', 'sent'])
            ->whereBetween('issue_date', [$startDate, $endDate])
            ->sum('total');
            
        $orders = TaxDocument::where('tenant_id', $tenant->id)
            ->whereIn('status', ['accepted', 'sent'])
            ->whereBetween('issue_date', [$startDate, $endDate])
            ->count();
            
        $customers = TaxDocument::where('tenant_id', $tenant->id)
            ->whereIn('status', ['accepted', 'sent'])
            ->whereBetween('issue_date', [$startDate, $endDate])
            ->distinct('customer_id')
            ->count('customer_id');
            
        return [
            'revenue' => $revenue,
            'orders' => $orders,
            'customers' => $customers,
        ];
    }

    /**
     * Get revenue chart data
     */
    public function getRevenueChart(Carbon $startDate, Carbon $endDate): array
    {
        $tenant = tenant();
        $data = [];
        
        // Determine grouping based on date range
        $days = $startDate->diffInDays($endDate);
        
        if ($days <= 31) {
            // Daily grouping
            $query = TaxDocument::where('tenant_id', $tenant->id)
                ->whereIn('status', ['accepted', 'sent'])
                ->whereBetween('issue_date', [$startDate, $endDate])
                ->groupBy('date')
                ->orderBy('date')
                ->select(
                    DB::raw('DATE(issue_date) as date'),
                    DB::raw('SUM(total) as revenue'),
                    DB::raw('COUNT(*) as orders')
                );
        } elseif ($days <= 365) {
            // Monthly grouping
            $query = TaxDocument::where('tenant_id', $tenant->id)
                ->whereIn('status', ['accepted', 'sent'])
                ->whereBetween('issue_date', [$startDate, $endDate])
                ->groupBy('month')
                ->orderBy('month')
                ->select(
                    DB::raw('DATE_FORMAT(issue_date, "%Y-%m") as month'),
                    DB::raw('SUM(total) as revenue'),
                    DB::raw('COUNT(*) as orders')
                );
        } else {
            // Yearly grouping
            $query = TaxDocument::where('tenant_id', $tenant->id)
                ->whereIn('status', ['accepted', 'sent'])
                ->whereBetween('issue_date', [$startDate, $endDate])
                ->groupBy('year')
                ->orderBy('year')
                ->select(
                    DB::raw('YEAR(issue_date) as year'),
                    DB::raw('SUM(total) as revenue'),
                    DB::raw('COUNT(*) as orders')
                );
        }
        
        $results = $query->get();
        
        return [
            'labels' => $results->pluck($days <= 31 ? 'date' : ($days <= 365 ? 'month' : 'year')),
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $results->pluck('revenue'),
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                ],
            ],
        ];
    }

    /**
     * Get top products
     */
    public function getTopProducts(Carbon $startDate, Carbon $endDate, int $limit = 10): array
    {
        $tenant = tenant();
        
        return DB::table('tax_document_items')
            ->join('tax_documents', 'tax_document_items.tax_document_id', '=', 'tax_documents.id')
            ->join('products', 'tax_document_items.product_id', '=', 'products.id')
            ->where('tax_documents.tenant_id', $tenant->id)
            ->whereIn('tax_documents.status', ['accepted', 'sent'])
            ->whereBetween('tax_documents.issue_date', [$startDate, $endDate])
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->select(
                'products.id',
                'products.name',
                DB::raw('SUM(tax_document_items.quantity) as quantity_sold'),
                DB::raw('SUM(tax_document_items.quantity * tax_document_items.unit_price) as revenue')
            )
            ->get();
    }

    /**
     * Get top customers
     */
    public function getTopCustomers(Carbon $startDate, Carbon $endDate, int $limit = 10): array
    {
        $tenant = tenant();
        
        return DB::table('tax_documents')
            ->join('customers', 'tax_documents.customer_id', '=', 'customers.id')
            ->where('tax_documents.tenant_id', $tenant->id)
            ->whereIn('tax_documents.status', ['accepted', 'sent'])
            ->whereBetween('tax_documents.issue_date', [$startDate, $endDate])
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->select(
                'customers.id',
                'customers.name',
                DB::raw('COUNT(tax_documents.id) as orders'),
                DB::raw('SUM(tax_documents.total) as revenue')
            )
            ->get()
            ->toArray();
    }

    /**
     * Get recent activity
     */
    public function getRecentActivity(int $limit = 20): array
    {
        $tenant = tenant();
        
        $documents = TaxDocument::where('tenant_id', $tenant->id)
            ->with(['customer:id,name', 'user:id,name'])
            ->latest()
            ->limit($limit)
            ->get(['id', 'type', 'number', 'customer_id', 'user_id', 'total', 'status', 'created_at'])
            ->map(function ($doc) {
                return [
                    'id' => $doc->id,
                    'type' => 'document',
                    'description' => "{$doc->type} #{$doc->number}",
                    'customer' => $doc->customer?->name,
                    'user' => $doc->user?->name,
                    'amount' => $doc->total,
                    'status' => $doc->status,
                    'created_at' => $doc->created_at,
                ];
            });
            
        return $documents->toArray();
    }

    /**
     * Calculate percentage change
     */
    protected function calculateChange($previous, $current): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        
        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Get trend direction
     */
    protected function getTrend($previous, $current): string
    {
        if ($current > $previous) return 'up';
        if ($current < $previous) return 'down';
        return 'neutral';
    }

    /**
     * Calculate AOV change
     */
    protected function calculateAOVChange(array $previous, array $current): float
    {
        $previousAOV = $previous['orders'] > 0 ? $previous['revenue'] / $previous['orders'] : 0;
        $currentAOV = $current['orders'] > 0 ? $current['revenue'] / $current['orders'] : 0;
        
        return $this->calculateChange($previousAOV, $currentAOV);
    }

    /**
     * Get AOV trend
     */
    protected function getAOVTrend(array $previous, array $current): string
    {
        $previousAOV = $previous['orders'] > 0 ? $previous['revenue'] / $previous['orders'] : 0;
        $currentAOV = $current['orders'] > 0 ? $current['revenue'] / $current['orders'] : 0;
        
        return $this->getTrend($previousAOV, $currentAOV);
    }

    // Additional methods for other charts and data...
    
    public function getOrdersChart(Carbon $startDate, Carbon $endDate): array
    {
        // Similar implementation to getRevenueChart but counting orders
        return [];
    }
    
    public function getCustomersChart(Carbon $startDate, Carbon $endDate): array
    {
        // Implementation for customer growth chart
        return [];
    }
    
    public function getRevenueData(Carbon $startDate, Carbon $endDate): array
    {
        return $this->getRevenueChart($startDate, $endDate);
    }
    
    public function getOrdersData(Carbon $startDate, Carbon $endDate): array
    {
        return $this->getOrdersChart($startDate, $endDate);
    }
    
    public function getCustomersData(Carbon $startDate, Carbon $endDate): array
    {
        return $this->getCustomersChart($startDate, $endDate);
    }
    
    public function getProductsData(Carbon $startDate, Carbon $endDate): array
    {
        return $this->getTopProducts($startDate, $endDate);
    }
    
    public function getConversionData(Carbon $startDate, Carbon $endDate): array
    {
        // Implementation for conversion metrics
        return [];
    }
}