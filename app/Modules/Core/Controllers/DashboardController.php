<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TaxDocument;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\Activity;
use App\Models\User;
use App\Traits\ChecksPermissions;
use Carbon\Carbon;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    use ChecksPermissions;
    
    public function __construct()
    {
        $this->middleware(['auth', 'verified', 'check.module:core']);
    }
    
    public function index()
    {
        $this->checkPermission('dashboard.view');
        
        $user = auth()->user();
        
        // Diferentes dashboards según el rol
        if ($user->hasRole(['super-admin', 'admin'])) {
            return $this->admin();
        } elseif ($user->hasRole('accountant')) {
            return $this->contador();
        } elseif ($user->hasRole('sales')) {
            return $this->vendedor();
        }
        
        // Para otros usuarios, dashboard estándar
        return $this->standardDashboard();
    }
    
    public function admin()
    {
        $this->checkPermission('dashboard.view');
        
        $tenantId = auth()->user()->tenant_id;
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        $lastMonth = $now->copy()->subMonth();
        
        // Cache key único por tenant y mes
        $cacheKey = "dashboard:admin:{$tenantId}:" . $now->format('Y-m');
        $cacheTTL = 300; // 5 minutos
        
        // KPIs principales con caché
        $kpis = Cache::remember($cacheKey . ':kpis', $cacheTTL, function() use ($tenantId, $now, $startOfMonth, $endOfMonth, $lastMonth) {
            return [
                'revenue' => [
                    'current' => $this->getMonthlyRevenue($tenantId, $now),
                    'previous' => $this->getMonthlyRevenue($tenantId, $lastMonth),
                    'growth' => 0,
                    'target' => 10000000, // 10M CLP
                ],
                'customers' => $this->getCustomerStats($tenantId, $startOfMonth, $endOfMonth),
                'invoices' => $this->getInvoiceStats($tenantId, $startOfMonth, $endOfMonth, $now),
                'cash_flow' => $this->getCashFlowStats($tenantId, $startOfMonth, $endOfMonth),
            ];
        });
        
        // Calcular crecimiento de ingresos
        if ($kpis['revenue']['previous'] > 0) {
            $kpis['revenue']['growth'] = round((($kpis['revenue']['current'] - $kpis['revenue']['previous']) / $kpis['revenue']['previous']) * 100, 1);
        }
        
        // Balance de flujo de caja
        $kpis['cash_flow']['balance'] = $kpis['cash_flow']['income'] - $kpis['cash_flow']['expenses'];
        
        // Gráficos y tendencias con caché
        $charts = Cache::remember($cacheKey . ':charts', $cacheTTL, function() use ($tenantId) {
            return [
                'revenue_trend' => $this->getRevenueTrend($tenantId, 12),
                'sales_by_category' => $this->getSalesByCategory($tenantId),
                'payment_methods' => $this->getPaymentMethodsDistribution($tenantId),
                'hourly_activity' => $this->getHourlyActivity($tenantId),
                'top_products' => $this->getTopProducts($tenantId, 5),
                'top_customers' => $this->getTopCustomers($tenantId, 5),
                'module_usage' => $this->getModuleUsageStats($tenantId),
            ];
        });
        
        // Alertas y notificaciones con caché corto (1 minuto)
        $alerts = Cache::remember($cacheKey . ':alerts', 60, function() use ($tenantId) {
            return [
                'low_stock' => $this->getLowStockProducts($tenantId),
                'overdue_invoices' => $this->getOverdueInvoices($tenantId),
                'expiring_documents' => $this->getExpiringDocuments($tenantId),
                'system_health' => $this->getSystemHealth(),
            ];
        });
        
        // Estadísticas de usuarios
        $userStats = Cache::remember($cacheKey . ':users', $cacheTTL, function() use ($tenantId) {
            return $this->getUserStats($tenantId);
        });
        
        // Actividad reciente (sin caché para mostrar datos en tiempo real)
        $recentActivity = $this->getRecentActivity($tenantId, 10);
        
        return Inertia::render('AdminDashboard', [
            'kpis' => $kpis,
            'charts' => $charts,
            'alerts' => $alerts,
            'userStats' => $userStats,
            'recentActivity' => $recentActivity,
        ]);
    }
    
    public function gerente()
    {
        $this->checkPermission('dashboard.view');
        
        $tenantId = auth()->user()->tenant_id;
        $cacheKey = "dashboard:gerente:{$tenantId}:" . now()->format('Y-m');
        $cacheTTL = 300;
        
        $metrics = Cache::remember($cacheKey, $cacheTTL, function() use ($tenantId) {
            $now = Carbon::now();
            $startOfMonth = $now->copy()->startOfMonth();
            $endOfMonth = $now->copy()->endOfMonth();
            $lastMonth = $now->copy()->subMonth();
            
            return [
                'revenue' => [
                    'month' => $this->getMonthlyRevenue($tenantId, $now),
                    'year' => $this->getYearlyRevenue($tenantId, $now),
                    'monthly_target' => 10000000,
                    'yearly_target' => 120000000,
                ],
                'customers' => $this->getCustomerStats($tenantId, $startOfMonth, $endOfMonth),
                'products' => $this->getProductPerformance($tenantId),
                'team' => $this->getTeamPerformance($tenantId),
                'comparisons' => [
                    'current_vs_previous' => $this->getMonthComparison($tenantId, $now, $lastMonth),
                    'year_over_year' => $this->getYearOverYearComparison($tenantId, $now),
                ],
            ];
        });
        
        $charts = Cache::remember($cacheKey . ':charts', $cacheTTL, function() use ($tenantId) {
            return [
                'revenue_trend' => $this->getRevenueTrend($tenantId, 12),
                'category_performance' => $this->getCategoryPerformance($tenantId),
                'customer_acquisition' => $this->getCustomerAcquisitionTrend($tenantId, 12),
                'sales_funnel' => $this->getSalesFunnel($tenantId),
            ];
        });
        
        return Inertia::render('GerenteDashboard', [
            'metrics' => $metrics,
            'charts' => $charts,
        ]);
    }
    
    public function contador()
    {
        $this->checkPermission('dashboard.view');
        
        $tenantId = auth()->user()->tenant_id;
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        
        $cacheKey = "dashboard:contador:{$tenantId}:" . $now->format('Y-m');
        $cacheTTL = 300;
        
        $financial = Cache::remember($cacheKey . ':financial', $cacheTTL, function() use ($tenantId, $startOfMonth, $endOfMonth) {
            return [
                'income' => $this->getMonthlyIncome($tenantId, $startOfMonth, $endOfMonth),
                'expenses' => $this->getMonthlyExpenses($tenantId, $startOfMonth, $endOfMonth),
                'taxes' => $this->getTaxSummary($tenantId, $startOfMonth, $endOfMonth),
                'pending_payments' => $this->getPendingPayments($tenantId),
                'bank_reconciliation' => $this->getBankReconciliationStatus($tenantId),
            ];
        });
        
        $documents = Cache::remember($cacheKey . ':documents', $cacheTTL, function() use ($tenantId, $startOfMonth, $endOfMonth) {
            return [
                'issued' => $this->getIssuedDocuments($tenantId, $startOfMonth, $endOfMonth),
                'received' => $this->getReceivedDocuments($tenantId, $startOfMonth, $endOfMonth),
                'sii_status' => $this->getSIIStatus($tenantId),
            ];
        });
        
        $charts = Cache::remember($cacheKey . ':charts', $cacheTTL, function() use ($tenantId) {
            return [
                'cash_flow' => $this->getCashFlowChart($tenantId, 6),
                'expense_breakdown' => $this->getExpenseBreakdown($tenantId),
                'tax_calendar' => $this->getTaxCalendar($tenantId),
            ];
        });
        
        return Inertia::render('ContadorDashboard', [
            'financial' => $financial,
            'documents' => $documents,
            'charts' => $charts,
        ]);
    }
    
    public function vendedor()
    {
        $this->checkPermission('dashboard.view');
        
        $userId = auth()->id();
        $tenantId = auth()->user()->tenant_id;
        
        $cacheKey = "dashboard:vendedor:{$userId}:" . now()->format('Y-m');
        $cacheTTL = 300;
        
        $performance = Cache::remember($cacheKey, $cacheTTL, function() use ($userId, $tenantId) {
            $now = Carbon::now();
            $startOfMonth = $now->copy()->startOfMonth();
            $endOfMonth = $now->copy()->endOfMonth();
            
            return [
                'sales' => $this->getUserSales($userId, $startOfMonth, $endOfMonth),
                'targets' => $this->getUserTargets($userId, $now),
                'commissions' => $this->getUserCommissions($userId, $startOfMonth, $endOfMonth),
                'customers' => $this->getUserCustomers($userId),
                'pending_quotes' => $this->getUserPendingQuotes($userId),
            ];
        });
        
        $charts = Cache::remember($cacheKey . ':charts', $cacheTTL, function() use ($userId) {
            return [
                'daily_sales' => $this->getUserDailySales($userId, 30),
                'product_mix' => $this->getUserProductMix($userId),
            ];
        });
        
        $activities = $this->getUserRecentActivities($userId, 20);
        
        return Inertia::render('VendedorDashboard', [
            'performance' => $performance,
            'charts' => $charts,
            'activities' => $activities,
        ]);
    }
    
    // Métodos auxiliares para obtener datos
    
    private function getMonthlyRevenue($tenantId, $date)
    {
        return TaxDocument::where('tenant_id', $tenantId)
            ->whereIn('type', ['invoice', 'ticket'])
            ->whereYear('date', $date->year)
            ->whereMonth('date', $date->month)
            ->where('status', 'completed')
            ->sum('total');
    }
    
    private function getYearlyRevenue($tenantId, $date)
    {
        return TaxDocument::where('tenant_id', $tenantId)
            ->whereIn('type', ['invoice', 'ticket'])
            ->whereYear('date', $date->year)
            ->where('status', 'completed')
            ->sum('total');
    }
    
    private function getCustomerStats($tenantId, $startDate, $endDate)
    {
        $total = Customer::where('tenant_id', $tenantId)->count();
        
        $new = Customer::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
            
        $active = Customer::where('tenant_id', $tenantId)
            ->whereHas('taxDocuments', function($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            })
            ->count();
        
        return compact('total', 'new', 'active');
    }
    
    private function getInvoiceStats($tenantId, $startDate, $endDate, $now)
    {
        $query = TaxDocument::where('tenant_id', $tenantId)
            ->whereIn('type', ['invoice', 'ticket'])
            ->whereBetween('date', [$startDate, $endDate]);
        
        return [
            'total' => $query->count(),
            'pending' => (clone $query)->where('payment_status', 'pending')->count(),
            'overdue' => (clone $query)->where('payment_status', 'pending')
                ->where('due_date', '<', $now)
                ->count(),
            'paid' => (clone $query)->where('payment_status', 'paid')->count(),
        ];
    }
    
    private function getCashFlowStats($tenantId, $startDate, $endDate)
    {
        $income = Payment::where('tenant_id', $tenantId)
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->where('status', 'completed')
            ->sum('amount');
            
        $expenses = Expense::where('tenant_id', $tenantId)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->where('is_paid', true)
            ->sum('total');
        
        return compact('income', 'expenses');
    }
    
    private function getRevenueTrend($tenantId, $months)
    {
        $trend = [];
        $now = Carbon::now();
        
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = $now->copy()->subMonths($i);
            $revenue = $this->getMonthlyRevenue($tenantId, $date);
            
            $trend[] = [
                'month' => $date->format('M Y'),
                'revenue' => $revenue,
            ];
        }
        
        return $trend;
    }
    
    private function getSalesByCategory($tenantId)
    {
        return DB::table('tax_document_items')
            ->join('tax_documents', 'tax_documents.id', '=', 'tax_document_items.tax_document_id')
            ->join('products', 'products.id', '=', 'tax_document_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('tax_documents.tenant_id', $tenantId)
            ->whereIn('tax_documents.type', ['invoice', 'ticket'])
            ->where('tax_documents.status', 'completed')
            ->whereMonth('tax_documents.date', now()->month)
            ->whereYear('tax_documents.date', now()->year)
            ->select('categories.name', DB::raw('SUM(tax_document_items.total) as total'))
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();
    }
    
    private function getPaymentMethodsDistribution($tenantId)
    {
        return Payment::where('tenant_id', $tenantId)
            ->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->where('status', 'completed')
            ->select('method', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->groupBy('method')
            ->get();
    }
    
    private function getHourlyActivity($tenantId)
    {
        $activities = [];
        
        for ($hour = 0; $hour < 24; $hour++) {
            $count = Activity::where('tenant_id', $tenantId)
                ->whereDate('created_at', today())
                ->whereRaw('HOUR(created_at) = ?', [$hour])
                ->count();
                
            $activities[] = [
                'hour' => $hour,
                'count' => $count,
            ];
        }
        
        return $activities;
    }
    
    private function getTopProducts($tenantId, $limit)
    {
        return DB::table('tax_document_items')
            ->join('tax_documents', 'tax_documents.id', '=', 'tax_document_items.tax_document_id')
            ->join('products', 'products.id', '=', 'tax_document_items.product_id')
            ->where('tax_documents.tenant_id', $tenantId)
            ->whereIn('tax_documents.type', ['invoice', 'ticket'])
            ->where('tax_documents.status', 'completed')
            ->whereMonth('tax_documents.date', now()->month)
            ->whereYear('tax_documents.date', now()->year)
            ->select(
                'products.id',
                'products.name',
                'products.sku',
                DB::raw('SUM(tax_document_items.quantity) as quantity_sold'),
                DB::raw('SUM(tax_document_items.total) as revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();
    }
    
    private function getTopCustomers($tenantId, $limit)
    {
        return Customer::where('tenant_id', $tenantId)
            ->withSum(['taxDocuments as total_purchases' => function($query) {
                $query->whereIn('type', ['invoice', 'ticket'])
                    ->where('status', 'completed')
                    ->whereMonth('date', now()->month)
                    ->whereYear('date', now()->year);
            }], 'total')
            ->orderByDesc('total_purchases')
            ->limit($limit)
            ->get(['id', 'name', 'rut', 'email']);
    }
    
    private function getLowStockProducts($tenantId)
    {
        return Product::where('tenant_id', $tenantId)
            ->where('type', 'product')
            ->where('track_inventory', true)
            ->whereRaw('stock_quantity <= min_stock_level')
            ->orderBy('stock_quantity')
            ->limit(10)
            ->get(['id', 'name', 'sku', 'stock_quantity', 'min_stock_level']);
    }
    
    private function getOverdueInvoices($tenantId)
    {
        return TaxDocument::where('tenant_id', $tenantId)
            ->with('customer:id,name,rut')
            ->whereIn('type', ['invoice'])
            ->where('payment_status', 'pending')
            ->where('due_date', '<', now())
            ->orderBy('due_date')
            ->limit(10)
            ->get(['id', 'number', 'customer_id', 'total', 'due_date']);
    }
    
    private function getExpiringDocuments($tenantId)
    {
        // Documentos que vencen en los próximos 7 días
        return TaxDocument::where('tenant_id', $tenantId)
            ->with('customer:id,name,rut')
            ->whereIn('type', ['quote'])
            ->where('status', 'sent')
            ->whereBetween('valid_until', [now(), now()->addDays(7)])
            ->orderBy('valid_until')
            ->limit(5)
            ->get(['id', 'number', 'customer_id', 'total', 'valid_until']);
    }
    
    private function getSystemHealth()
    {
        return [
            'database' => $this->checkDatabaseConnection(),
            'storage' => $this->checkStorageSpace(),
            'backups' => $this->checkLastBackup(),
        ];
    }
    
    private function checkDatabaseConnection()
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'ok', 'message' => 'Conexión activa'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Error de conexión'];
        }
    }
    
    private function checkStorageSpace()
    {
        $free = disk_free_space(storage_path());
        $total = disk_total_space(storage_path());
        $used_percentage = round((($total - $free) / $total) * 100, 1);
        
        if ($used_percentage > 90) {
            return ['status' => 'error', 'message' => "Espacio crítico: {$used_percentage}% usado"];
        } elseif ($used_percentage > 75) {
            return ['status' => 'warning', 'message' => "Espacio limitado: {$used_percentage}% usado"];
        }
        
        return ['status' => 'ok', 'message' => "Espacio disponible: {$used_percentage}% usado"];
    }
    
    private function checkLastBackup()
    {
        $lastBackup = \App\Models\Backup::where('status', 'completed')
            ->latest()
            ->first();
            
        if (!$lastBackup) {
            return ['status' => 'error', 'message' => 'No hay backups'];
        }
        
        $hoursSinceBackup = $lastBackup->created_at->diffInHours(now());
        
        if ($hoursSinceBackup > 48) {
            return ['status' => 'error', 'message' => "Último backup hace {$hoursSinceBackup} horas"];
        } elseif ($hoursSinceBackup > 24) {
            return ['status' => 'warning', 'message' => "Último backup hace {$hoursSinceBackup} horas"];
        }
        
        return ['status' => 'ok', 'message' => 'Backups al día'];
    }
    
    private function getUserStats($tenantId)
    {
        $total = User::where('tenant_id', $tenantId)->count();
        
        $activeToday = Activity::where('tenant_id', $tenantId)
            ->whereDate('created_at', today())
            ->distinct('user_id')
            ->count('user_id');
            
        $activeWeek = Activity::where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subWeek())
            ->distinct('user_id')
            ->count('user_id');
            
        $activeMonth = Activity::where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subMonth())
            ->distinct('user_id')
            ->count('user_id');
            
        $newToday = User::where('tenant_id', $tenantId)
            ->whereDate('created_at', today())
            ->count();
            
        $newWeek = User::where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subWeek())
            ->count();
            
        $newMonth = User::where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subMonth())
            ->count();
            
        $byRole = User::where('tenant_id', $tenantId)
            ->select('role', DB::raw('COUNT(*) as count'))
            ->groupBy('role')
            ->pluck('count', 'role');
        
        return [
            'total' => $total,
            'active' => [
                'today' => $activeToday,
                'week' => $activeWeek,
                'month' => $activeMonth,
            ],
            'new' => [
                'today' => $newToday,
                'week' => $newWeek,
                'month' => $newMonth,
            ],
            'by_role' => $byRole,
        ];
    }
    
    private function getRecentActivity($tenantId, $limit)
    {
        return Activity::where('tenant_id', $tenantId)
            ->with('user:id,name,email')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function($activity) {
                return [
                    'id' => $activity->id,
                    'user' => $activity->user ? $activity->user->name : 'Sistema',
                    'description' => $activity->description,
                    'type' => $activity->type,
                    'icon' => $this->getActivityIcon($activity->type),
                    'color' => $this->getActivityColor($activity->type),
                    'time' => $activity->created_at->diffForHumans(),
                ];
            });
    }
    
    private function getActivityIcon($type)
    {
        return match($type) {
            'login' => 'arrow-right-on-rectangle',
            'logout' => 'arrow-left-on-rectangle',
            'create' => 'plus-circle',
            'update' => 'pencil-square',
            'delete' => 'trash',
            'invoice' => 'document-text',
            'payment' => 'currency-dollar',
            default => 'information-circle',
        };
    }
    
    private function getActivityColor($type)
    {
        return match($type) {
            'login', 'logout' => 'blue',
            'create' => 'green',
            'update' => 'yellow',
            'delete' => 'red',
            'invoice' => 'purple',
            'payment' => 'indigo',
            default => 'gray',
        };
    }
    
    private function getModuleUsageStats($tenantId)
    {
        $modules = [
            'invoicing' => ['name' => 'Facturación', 'model' => TaxDocument::class],
            'customers' => ['name' => 'Clientes', 'model' => Customer::class],
            'products' => ['name' => 'Productos', 'model' => Product::class],
            'payments' => ['name' => 'Pagos', 'model' => Payment::class],
            'expenses' => ['name' => 'Gastos', 'model' => Expense::class],
        ];
        
        $stats = [];
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();
        
        foreach ($modules as $key => $module) {
            $model = $module['model'];
            
            $thisMonth = $model::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->count();
                
            $lastMonth = $model::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [
                    $startOfMonth->copy()->subMonth(),
                    $endOfMonth->copy()->subMonth()
                ])
                ->count();
                
            $growth = $lastMonth > 0 
                ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1)
                : 0;
                
            $activeUsers = Activity::where('tenant_id', $tenantId)
                ->where('model_type', $model)
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->distinct('user_id')
                ->count('user_id');
            
            $stats[] = [
                'module' => $module['name'],
                'operations' => $thisMonth,
                'growth' => $growth,
                'active_users' => $activeUsers,
            ];
        }
        
        return $stats;
    }
    
    // Métodos adicionales para otros dashboards...
    
    private function standardDashboard()
    {
        return Inertia::render('Dashboard', [
            'message' => 'Bienvenido a CrecePyme',
        ]);
    }
    
    // Los demás métodos auxiliares necesarios para los otros dashboards...
}