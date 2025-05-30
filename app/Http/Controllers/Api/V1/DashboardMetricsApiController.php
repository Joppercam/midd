<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TaxDocument;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\User;
use App\Models\Activity;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class DashboardMetricsApiController extends Controller
{
    /**
     * Get real-time dashboard metrics
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id;
        $now = Carbon::now();
        
        // Cache key único por usuario y minuto para evitar múltiples consultas
        $cacheKey = "metrics:realtime:{$tenantId}:{$user->id}:" . $now->format('Y-m-d-H-i');
        $cacheTTL = 60; // 1 minuto de cache
        
        $metrics = Cache::remember($cacheKey, $cacheTTL, function() use ($tenantId, $user, $now) {
            $startOfMonth = $now->copy()->startOfMonth();
            $endOfMonth = $now->copy()->endOfMonth();
            $startOfDay = $now->copy()->startOfDay();
            $endOfDay = $now->copy()->endOfDay();
            
            return [
                // Métricas básicas
                'basic' => $this->getBasicMetrics($tenantId, $now, $startOfMonth, $endOfMonth),
                
                // Métricas de hoy
                'today' => $this->getTodayMetrics($tenantId, $startOfDay, $endOfDay),
                
                // Actividad en tiempo real
                'activity' => $this->getRecentActivity($tenantId),
                
                // Notificaciones urgentes
                'notifications' => $this->getUrgentNotifications($tenantId),
                
                // Estado del sistema
                'system' => $this->getSystemStatus(),
                
                // Métricas específicas por rol
                'role_specific' => $this->getRoleSpecificMetrics($user, $tenantId, $now),
                
                // Timestamp de actualización
                'last_updated' => $now->toISOString(),
                'next_update' => $now->addMinutes(1)->toISOString(),
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $metrics,
            'meta' => [
                'user_role' => $user->role,
                'tenant_name' => $user->tenant->name,
                'timezone' => 'America/Santiago',
                'cache_ttl' => $cacheTTL,
            ]
        ]);
    }
    
    /**
     * Get specific metric by key
     */
    public function show(Request $request, $metric)
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id;
        $now = Carbon::now();
        
        $cacheKey = "metric:{$metric}:{$tenantId}:" . $now->format('Y-m-d-H-i');
        
        $data = Cache::remember($cacheKey, 30, function() use ($metric, $tenantId, $now) {
            switch ($metric) {
                case 'revenue':
                    return $this->getRevenueMetric($tenantId, $now);
                case 'customers':
                    return $this->getCustomersMetric($tenantId, $now);
                case 'inventory':
                    return $this->getInventoryMetric($tenantId);
                case 'cash_flow':
                    return $this->getCashFlowMetric($tenantId, $now);
                case 'activity':
                    return $this->getActivityMetric($tenantId);
                default:
                    return null;
            }
        });
        
        if ($data === null) {
            return response()->json([
                'success' => false,
                'message' => 'Métrica no encontrada'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $data,
            'last_updated' => $now->toISOString()
        ]);
    }
    
    /**
     * Get live chart data
     */
    public function charts(Request $request)
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id;
        $chartType = $request->get('type', 'revenue');
        $period = $request->get('period', '7d');
        
        $cacheKey = "charts:{$chartType}:{$period}:{$tenantId}:" . Carbon::now()->format('Y-m-d-H');
        
        $data = Cache::remember($cacheKey, 1800, function() use ($chartType, $period, $tenantId) {
            switch ($chartType) {
                case 'revenue':
                    return $this->getRevenueChartData($tenantId, $period);
                case 'sales_hourly':
                    return $this->getHourlySalesData($tenantId);
                case 'customers_growth':
                    return $this->getCustomersGrowthData($tenantId, $period);
                case 'top_products':
                    return $this->getTopProductsData($tenantId, $period);
                default:
                    return $this->getRevenueChartData($tenantId, $period);
            }
        });
        
        return response()->json([
            'success' => true,
            'data' => $data,
            'type' => $chartType,
            'period' => $period
        ]);
    }
    
    private function getBasicMetrics($tenantId, $now, $startOfMonth, $endOfMonth)
    {
        return [
            'monthly_revenue' => TaxDocument::where('tenant_id', $tenantId)
                ->where('status', 'accepted')
                ->whereBetween('issue_date', [$startOfMonth, $endOfMonth])
                ->sum('total'),
                
            'total_customers' => Customer::where('tenant_id', $tenantId)->count(),
            
            'new_customers_month' => Customer::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->count(),
                
            'pending_invoices' => TaxDocument::where('tenant_id', $tenantId)
                ->where('payment_status', 'pending')
                ->count(),
                
            'overdue_invoices' => TaxDocument::where('tenant_id', $tenantId)
                ->where('payment_status', 'pending')
                ->where('due_date', '<', $now)
                ->count(),
                
            'total_products' => Product::where('tenant_id', $tenantId)->count(),
            
            'low_stock_products' => Product::where('tenant_id', $tenantId)
                ->where('track_inventory', true)
                ->whereColumn('current_stock', '<=', 'minimum_stock')
                ->count(),
                
            'total_expenses_month' => Expense::where('tenant_id', $tenantId)
                ->whereBetween('expense_date', [$startOfMonth, $endOfMonth])
                ->sum('amount'),
                
            'cash_flow' => $this->calculateCashFlow($tenantId, $startOfMonth, $endOfMonth),
            
            'users_count' => User::where('tenant_id', $tenantId)->count(),
            
            'pending_reconciliations' => 0, // TODO: Implementar cuando exista el modelo
        ];
    }
    
    private function getTodayMetrics($tenantId, $startOfDay, $endOfDay)
    {
        return [
            'sales_today' => TaxDocument::where('tenant_id', $tenantId)
                ->where('status', 'accepted')
                ->whereBetween('issue_date', [$startOfDay, $endOfDay])
                ->sum('total'),
                
            'invoices_today' => TaxDocument::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->count(),
                
            'payments_today' => Payment::where('tenant_id', $tenantId)
                ->whereBetween('payment_date', [$startOfDay, $endOfDay])
                ->sum('amount'),
                
            'new_customers_today' => Customer::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->count(),
                
            'active_users_today' => User::where('tenant_id', $tenantId)
                ->whereDate('last_login_at', $startOfDay)
                ->count(),
        ];
    }
    
    private function getRecentActivity($tenantId)
    {
        return Activity::where('tenant_id', $tenantId)
            ->with('user:id,name')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'user' => $activity->user?->name ?? 'Sistema',
                    'action' => $activity->action,
                    'description' => $activity->description,
                    'time' => $activity->created_at->diffForHumans(),
                    'timestamp' => $activity->created_at->toISOString(),
                ];
            });
    }
    
    private function getUrgentNotifications($tenantId)
    {
        $notifications = [];
        
        // Facturas vencidas
        $overdueCount = TaxDocument::where('tenant_id', $tenantId)
            ->where('payment_status', 'pending')
            ->where('due_date', '<', Carbon::now())
            ->count();
            
        if ($overdueCount > 0) {
            $notifications[] = [
                'id' => 'overdue_invoices',
                'type' => 'warning',
                'title' => 'Facturas Vencidas',
                'message' => "Tienes {$overdueCount} facturas vencidas por cobrar",
                'count' => $overdueCount,
                'action' => '/invoices?filter=overdue',
                'created_at' => Carbon::now()->toISOString(),
            ];
        }
        
        // Stock bajo
        $lowStockCount = Product::where('tenant_id', $tenantId)
            ->where('track_inventory', true)
            ->whereColumn('current_stock', '<=', 'minimum_stock')
            ->count();
            
        if ($lowStockCount > 0) {
            $notifications[] = [
                'id' => 'low_stock',
                'type' => 'error',
                'title' => 'Stock Bajo',
                'message' => "Hay {$lowStockCount} productos con stock bajo",
                'count' => $lowStockCount,
                'action' => '/products?filter=low_stock',
                'created_at' => Carbon::now()->toISOString(),
            ];
        }
        
        // Vencimientos próximos (7 días)
        $upcomingDue = TaxDocument::where('tenant_id', $tenantId)
            ->where('payment_status', 'pending')
            ->whereBetween('due_date', [Carbon::now(), Carbon::now()->addDays(7)])
            ->count();
            
        if ($upcomingDue > 0) {
            $notifications[] = [
                'id' => 'upcoming_due',
                'type' => 'info',
                'title' => 'Vencimientos Próximos',
                'message' => "{$upcomingDue} facturas vencen en los próximos 7 días",
                'count' => $upcomingDue,
                'action' => '/invoices?filter=upcoming',
                'created_at' => Carbon::now()->toISOString(),
            ];
        }
        
        return $notifications;
    }
    
    private function getSystemStatus()
    {
        return [
            'status' => 'healthy',
            'database' => 'connected',
            'cache' => Cache::has('test') ? 'active' : 'inactive',
            'last_backup' => Carbon::now()->subHours(2)->toISOString(), // Ejemplo
            'uptime' => '99.9%',
        ];
    }
    
    private function getRoleSpecificMetrics($user, $tenantId, $now)
    {
        switch ($user->role) {
            case 'admin':
                return $this->getAdminMetrics($tenantId, $now);
            case 'sales':
                return $this->getSalesMetrics($user->id, $tenantId, $now);
            case 'accountant':
                return $this->getAccountantMetrics($tenantId, $now);
            default:
                return [];
        }
    }
    
    private function getAdminMetrics($tenantId, $now)
    {
        $startOfMonth = $now->copy()->startOfMonth();
        
        return [
            'active_users_today' => User::where('tenant_id', $tenantId)
                ->whereDate('last_login_at', $now)
                ->count(),
                
            'new_users_month' => User::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$startOfMonth, $now])
                ->count(),
                
            'module_usage' => [
                'invoicing' => Activity::where('tenant_id', $tenantId)
                    ->where('model_type', 'App\\Models\\TaxDocument')
                    ->whereDate('created_at', $now)
                    ->count(),
                'customers' => Activity::where('tenant_id', $tenantId)
                    ->where('model_type', 'App\\Models\\Customer')
                    ->whereDate('created_at', $now)
                    ->count(),
                'products' => Activity::where('tenant_id', $tenantId)
                    ->where('model_type', 'App\\Models\\Product')
                    ->whereDate('created_at', $now)
                    ->count(),
            ],
        ];
    }
    
    private function getSalesMetrics($userId, $tenantId, $now)
    {
        $startOfMonth = $now->copy()->startOfMonth();
        
        $monthlySales = TaxDocument::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', 'accepted')
            ->whereBetween('issue_date', [$startOfMonth, $now])
            ->sum('total');
            
        $monthlyTarget = 5000000; // 5M CLP ejemplo
        
        return [
            'monthly_sales' => $monthlySales,
            'monthly_target' => $monthlyTarget,
            'target_percentage' => $monthlyTarget > 0 ? round(($monthlySales / $monthlyTarget) * 100, 1) : 0,
            'sales_today' => TaxDocument::where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->whereDate('issue_date', $now)
                ->sum('total'),
            'quotes_pending' => 0, // TODO: Implementar cuando exista el modelo
        ];
    }
    
    private function getAccountantMetrics($tenantId, $now)
    {
        $startOfMonth = $now->copy()->startOfMonth();
        
        return [
            'tax_documents_pending' => TaxDocument::where('tenant_id', $tenantId)
                ->where('sii_status', 'pending')
                ->count(),
                
            'reconciliation_pending' => 0, // TODO: Implementar
            
            'monthly_iva' => TaxDocument::where('tenant_id', $tenantId)
                ->whereBetween('issue_date', [$startOfMonth, $now])
                ->sum('tax'),
        ];
    }
    
    private function calculateCashFlow($tenantId, $startOfMonth, $endOfMonth)
    {
        $income = Payment::where('tenant_id', $tenantId)
            ->whereBetween('payment_date', [$startOfMonth, $endOfMonth])
            ->sum('amount');
            
        $expenses = Expense::where('tenant_id', $tenantId)
            ->whereBetween('expense_date', [$startOfMonth, $endOfMonth])
            ->sum('amount');
            
        return $income - $expenses;
    }
    
    private function getRevenueChartData($tenantId, $period)
    {
        $data = [];
        $days = $period === '30d' ? 30 : 7;
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $revenue = TaxDocument::where('tenant_id', $tenantId)
                ->where('status', 'accepted')
                ->whereDate('issue_date', $date)
                ->sum('total');
                
            $data[] = [
                'date' => $date->format('Y-m-d'),
                'label' => $date->format('d/m'),
                'value' => $revenue,
            ];
        }
        
        return $data;
    }
    
    private function getHourlySalesData($tenantId)
    {
        $data = [];
        $today = Carbon::today();
        
        for ($hour = 0; $hour < 24; $hour++) {
            $start = $today->copy()->addHours($hour);
            $end = $start->copy()->addHour();
            
            $sales = TaxDocument::where('tenant_id', $tenantId)
                ->where('status', 'accepted')
                ->whereBetween('created_at', [$start, $end])
                ->sum('total');
                
            $data[] = [
                'hour' => sprintf('%02d:00', $hour),
                'value' => $sales,
            ];
        }
        
        return $data;
    }
    
    private function getCustomersGrowthData($tenantId, $period)
    {
        $data = [];
        $days = $period === '30d' ? 30 : 7;
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $newCustomers = Customer::where('tenant_id', $tenantId)
                ->whereDate('created_at', $date)
                ->count();
                
            $data[] = [
                'date' => $date->format('Y-m-d'),
                'label' => $date->format('d/m'),
                'value' => $newCustomers,
            ];
        }
        
        return $data;
    }
    
    private function getTopProductsData($tenantId, $period)
    {
        $days = $period === '30d' ? 30 : 7;
        $startDate = Carbon::now()->subDays($days);
        
        return DB::table('tax_document_items')
            ->join('tax_documents', 'tax_document_items.tax_document_id', '=', 'tax_documents.id')
            ->join('products', 'tax_document_items.product_id', '=', 'products.id')
            ->where('tax_documents.tenant_id', $tenantId)
            ->where('tax_documents.status', 'accepted')
            ->where('tax_documents.issue_date', '>=', $startDate)
            ->select(
                'products.name',
                DB::raw('SUM(tax_document_items.quantity) as quantity'),
                DB::raw('SUM(tax_document_items.subtotal) as revenue')
            )
            ->groupBy('products.id', 'products.name')
            ->orderBy('revenue', 'desc')
            ->limit(5)
            ->get();
    }
    
    // Métodos individuales para métricas específicas
    private function getRevenueMetric($tenantId, $now)
    {
        $startOfMonth = $now->copy()->startOfMonth();
        $lastMonth = $now->copy()->subMonth();
        
        $current = TaxDocument::where('tenant_id', $tenantId)
            ->where('status', 'accepted')
            ->whereMonth('issue_date', $now->month)
            ->whereYear('issue_date', $now->year)
            ->sum('total');
            
        $previous = TaxDocument::where('tenant_id', $tenantId)
            ->where('status', 'accepted')
            ->whereMonth('issue_date', $lastMonth->month)
            ->whereYear('issue_date', $lastMonth->year)
            ->sum('total');
            
        $growth = $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : 0;
        
        return [
            'current' => $current,
            'previous' => $previous,
            'growth' => $growth,
            'target' => 10000000, // 10M CLP
            'target_percentage' => round(($current / 10000000) * 100, 1),
        ];
    }
    
    private function getCustomersMetric($tenantId, $now)
    {
        $startOfMonth = $now->copy()->startOfMonth();
        
        return [
            'total' => Customer::where('tenant_id', $tenantId)->count(),
            'new_this_month' => Customer::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$startOfMonth, $now])
                ->count(),
            'active' => Customer::where('tenant_id', $tenantId)
                ->where('active', true)
                ->count(),
        ];
    }
    
    private function getInventoryMetric($tenantId)
    {
        return [
            'total_products' => Product::where('tenant_id', $tenantId)->count(),
            'low_stock' => Product::where('tenant_id', $tenantId)
                ->where('track_inventory', true)
                ->whereColumn('current_stock', '<=', 'minimum_stock')
                ->count(),
            'out_of_stock' => Product::where('tenant_id', $tenantId)
                ->where('track_inventory', true)
                ->where('current_stock', '<=', 0)
                ->count(),
        ];
    }
    
    private function getCashFlowMetric($tenantId, $now)
    {
        $startOfMonth = $now->copy()->startOfMonth();
        
        $income = Payment::where('tenant_id', $tenantId)
            ->whereBetween('payment_date', [$startOfMonth, $now])
            ->sum('amount');
            
        $expenses = Expense::where('tenant_id', $tenantId)
            ->whereBetween('expense_date', [$startOfMonth, $now])
            ->sum('amount');
            
        return [
            'income' => $income,
            'expenses' => $expenses,
            'balance' => $income - $expenses,
            'trend' => $income > $expenses ? 'positive' : 'negative',
        ];
    }
    
    private function getActivityMetric($tenantId)
    {
        $today = Carbon::today();
        $thisWeek = Carbon::now()->startOfWeek();
        
        return [
            'today' => Activity::where('tenant_id', $tenantId)
                ->whereDate('created_at', $today)
                ->count(),
            'this_week' => Activity::where('tenant_id', $tenantId)
                ->where('created_at', '>=', $thisWeek)
                ->count(),
            'recent' => Activity::where('tenant_id', $tenantId)
                ->latest()
                ->take(3)
                ->get(['action', 'description', 'created_at']),
        ];
    }
}