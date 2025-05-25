<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\TaxDocument;
use App\Models\Customer;
use App\Models\Product;
use Carbon\Carbon;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $tenant = auth()->user()->tenant;
        
        $metrics = [
            'monthly_revenue' => $this->getMonthlyRevenue($tenant),
            'total_customers' => $tenant->customers()->count(),
            'documents_this_month' => $this->getDocumentsThisMonth($tenant),
            'total_products' => $tenant->products()->count(),
            'monthly_revenue_chart' => $this->getMonthlyRevenueChart($tenant),
            'inventory_alerts' => $this->getInventoryAlerts($tenant),
        ];

        return Inertia::render('Dashboard', [
            'metrics' => $metrics,
        ]);
    }

    private function getMonthlyRevenue($tenant)
    {
        return $tenant->taxDocuments()
            ->where('status', 'accepted')
            ->whereMonth('issue_date', Carbon::now()->month)
            ->whereYear('issue_date', Carbon::now()->year)
            ->sum('total');
    }

    private function getDocumentsThisMonth($tenant)
    {
        return $tenant->taxDocuments()
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();
    }

    private function getMonthlyRevenueChart($tenant)
    {
        $data = [];
        
        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $revenue = $tenant->taxDocuments()
                ->where('status', 'accepted')
                ->whereMonth('issue_date', $date->month)
                ->whereYear('issue_date', $date->year)
                ->sum('total');
                
            $data[] = [
                'month' => $date->format('M'),
                'revenue' => $revenue,
            ];
        }
        
        return $data;
    }

    private function getInventoryAlerts($tenant)
    {
        return $tenant->products()
            ->where('is_service', false)
            ->whereRaw('stock_quantity <= min_stock_alert')
            ->get(['id', 'name', 'stock_quantity', 'min_stock_alert']);
    }
}