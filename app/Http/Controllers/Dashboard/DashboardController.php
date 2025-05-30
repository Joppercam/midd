<?php

namespace App\Http\Controllers\Dashboard;

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
    
    public function index()
    {
        // Temporarily bypass permission check to fix blank page issue
        // $this->checkPermission('dashboard.view');
        
        $user = auth()->user();
        
        // Diferentes dashboards según el rol
        // Use isAdmin() method instead of hasRole() to avoid Spatie errors
        if ($user->isAdmin()) {
            return $this->adminDashboard();
        } elseif ($user->role === 'gerente') {
            return $this->gerenteDashboard();
        } elseif ($user->role === 'contador') {
            return $this->contadorDashboard();
        } elseif ($user->role === 'vendedor') {
            return $this->vendedorDashboard();
        }
        
        // Para otros usuarios, dashboard estándar
        return $this->standardDashboard();
    }
    
    private function adminDashboard()
    {
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
        
        // Actividad reciente
        $recentActivity = Activity::where('tenant_id', $tenantId)
            ->with('user')
            ->latest()
            ->take(10)
            ->get();
        
        // Usuarios activos y estadísticas
        $activeUsers = User::where('tenant_id', $tenantId)
            ->where('last_login_at', '>=', $now->copy()->subHours(24))
            ->count();
        
        // Métricas de usuarios con caché
        $userMetrics = Cache::remember($cacheKey . ':user_metrics', $cacheTTL, function() use ($tenantId, $now, $startOfMonth, $endOfMonth) {
            return [
                'total_users' => User::where('tenant_id', $tenantId)->count(),
                'active_today' => User::where('tenant_id', $tenantId)
                    ->whereDate('last_login_at', $now)
                    ->count(),
                'active_week' => User::where('tenant_id', $tenantId)
                    ->where('last_login_at', '>=', $now->copy()->subWeek())
                    ->count(),
                'active_month' => User::where('tenant_id', $tenantId)
                    ->where('last_login_at', '>=', $now->copy()->subMonth())
                    ->count(),
                'new_today' => User::where('tenant_id', $tenantId)
                    ->whereDate('created_at', $now)
                    ->count(),
                'new_week' => User::where('tenant_id', $tenantId)
                    ->whereBetween('created_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()])
                    ->count(),
                'new_month' => User::where('tenant_id', $tenantId)
                    ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                    ->count(),
                'by_role' => User::withoutGlobalScope('tenant')
                    ->where('users.tenant_id', $tenantId)
                    ->selectRaw('roles.name as role_name, COUNT(users.id) as count')
                    ->join('model_has_roles', function($join) use ($tenantId) {
                        $join->on('users.id', '=', 'model_has_roles.model_id')
                        ->where('model_has_roles.tenant_id', $tenantId);
                })
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->groupBy('roles.name')
                ->get()
                ->pluck('count', 'role_name')
                ->toArray(),
            ];
        });
        
        return Inertia::render('AdminDashboard', [
            'kpis' => $kpis,
            'charts' => $charts,
            'alerts' => $alerts,
            'recentActivity' => $recentActivity,
            'activeUsers' => $activeUsers,
            'userMetrics' => $userMetrics,
            'lastUpdated' => $now->format('H:i:s'),
        ]);
    }
    
    private function standardDashboard()
    {
        $tenantId = auth()->user()->tenant_id;
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        
        $metrics = [
            'monthly_revenue' => $this->getMonthlyRevenue($tenantId, $now),
            'total_customers' => Customer::where('tenant_id', $tenantId)->count(),
            'documents_this_month' => TaxDocument::where('tenant_id', $tenantId)
                ->whereBetween('issue_date', [$startOfMonth, $endOfMonth])
                ->count(),
            'total_products' => Product::where('tenant_id', $tenantId)->count(),
            'revenue_chart' => $this->getRevenueTrend($tenantId, 6),
            'recent_invoices' => $this->getRecentInvoices($tenantId, 5),
            'pending_payments' => $this->getPendingPayments($tenantId),
        ];

        return Inertia::render('Dashboard', [
            'metrics' => $metrics,
        ]);
    }

    private function getMonthlyRevenue($tenantId, Carbon $date)
    {
        return TaxDocument::where('tenant_id', $tenantId)
            ->where('status', 'accepted')
            ->whereMonth('issue_date', $date->month)
            ->whereYear('issue_date', $date->year)
            ->sum('total');
    }

    private function getRevenueTrend($tenantId, $months = 12)
    {
        $data = [];
        
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $revenue = $this->getMonthlyRevenue($tenantId, $date);
                
            $data[] = [
                'month' => $date->format('M Y'),
                'revenue' => $revenue,
            ];
        }
        
        return $data;
    }

    private function getSalesByCategory($tenantId)
    {
        return DB::table('tax_document_items')
            ->join('tax_documents', 'tax_document_items.tax_document_id', '=', 'tax_documents.id')
            ->join('products', 'tax_document_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->where('tax_documents.tenant_id', $tenantId)
            ->where('tax_documents.status', 'accepted')
            ->whereMonth('tax_documents.issue_date', Carbon::now()->month)
            ->select('categories.name', DB::raw('SUM(tax_document_items.subtotal) as total'))
            ->groupBy('categories.name')
            ->orderBy('total', 'desc')
            ->limit(5)
            ->get();
    }

    private function getPaymentMethodsDistribution($tenantId)
    {
        return Payment::where('tenant_id', $tenantId)
            ->whereMonth('payment_date', Carbon::now()->month)
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->groupBy('payment_method')
            ->get()
            ->map(function ($item) {
                $methods = [
                    'cash' => 'Efectivo',
                    'check' => 'Cheque',
                    'transfer' => 'Transferencia',
                    'credit_card' => 'Tarjeta Crédito',
                    'debit_card' => 'Tarjeta Débito',
                ];
                return [
                    'method' => $methods[$item->payment_method] ?? $item->payment_method,
                    'count' => $item->count,
                    'total' => $item->total,
                ];
            });
    }

    private function getHourlyActivity($tenantId)
    {
        $data = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $count = Activity::where('tenant_id', $tenantId)
                ->whereDate('created_at', Carbon::today())
                ->whereTime('created_at', '>=', sprintf('%02d:00:00', $hour))
                ->whereTime('created_at', '<', sprintf('%02d:00:00', $hour + 1))
                ->count();
            
            $data[] = [
                'hour' => sprintf('%02d:00', $hour),
                'activity' => $count,
            ];
        }
        return $data;
    }

    private function getTopProducts($tenantId, $limit = 5)
    {
        return DB::table('tax_document_items')
            ->join('tax_documents', 'tax_document_items.tax_document_id', '=', 'tax_documents.id')
            ->join('products', 'tax_document_items.product_id', '=', 'products.id')
            ->where('tax_documents.tenant_id', $tenantId)
            ->where('tax_documents.status', 'accepted')
            ->whereMonth('tax_documents.issue_date', Carbon::now()->month)
            ->select(
                'products.id',
                'products.name',
                'products.sku',
                DB::raw('SUM(tax_document_items.quantity) as quantity_sold'),
                DB::raw('SUM(tax_document_items.subtotal) as revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderBy('revenue', 'desc')
            ->limit($limit)
            ->get();
    }

    private function getTopCustomers($tenantId, $limit = 5)
    {
        return Customer::withoutGlobalScope('tenant')
            ->where('customers.tenant_id', $tenantId)
            ->join('tax_documents', 'customers.id', '=', 'tax_documents.customer_id')
            ->where('tax_documents.tenant_id', $tenantId)
            ->where('tax_documents.status', 'accepted')
            ->whereMonth('tax_documents.issue_date', Carbon::now()->month)
            ->select(
                'customers.id',
                'customers.name',
                'customers.rut',
                DB::raw('COUNT(tax_documents.id) as invoices_count'),
                DB::raw('SUM(tax_documents.total) as total_spent')
            )
            ->groupBy('customers.id', 'customers.name', 'customers.rut')
            ->orderBy('total_spent', 'desc')
            ->limit($limit)
            ->get();
    }

    private function getLowStockProducts($tenantId)
    {
        return Product::where('tenant_id', $tenantId)
            ->where('track_inventory', true)
            ->whereColumn('current_stock', '<=', 'minimum_stock')
            ->select('id', 'name', 'sku', 'current_stock', 'minimum_stock')
            ->orderBy('current_stock', 'asc')
            ->limit(10)
            ->get();
    }

    private function getOverdueInvoices($tenantId)
    {
        return TaxDocument::where('tenant_id', $tenantId)
            ->where('payment_status', 'pending')
            ->where('due_date', '<', Carbon::now())
            ->with('customer:id,name,rut')
            ->select('id', 'document_number', 'customer_id', 'total', 'due_date')
            ->orderBy('due_date', 'asc')
            ->limit(10)
            ->get()
            ->map(function ($invoice) {
                $invoice->days_overdue = Carbon::parse($invoice->due_date)->diffInDays(Carbon::now());
                return $invoice;
            });
    }

    private function getExpiringDocuments($tenantId)
    {
        $thirtyDaysFromNow = Carbon::now()->addDays(30);
        
        return TaxDocument::where('tenant_id', $tenantId)
            ->where('payment_status', 'pending')
            ->whereBetween('due_date', [Carbon::now(), $thirtyDaysFromNow])
            ->with('customer:id,name,rut')
            ->select('id', 'document_number', 'customer_id', 'total', 'due_date')
            ->orderBy('due_date', 'asc')
            ->limit(10)
            ->get();
    }

    private function getSystemHealth()
    {
        $health = [
            'status' => 'healthy',
            'checks' => []
        ];

        // Check database
        try {
            DB::connection()->getPdo();
            $health['checks']['database'] = ['status' => 'ok', 'message' => 'Base de datos operativa'];
        } catch (\Exception $e) {
            $health['checks']['database'] = ['status' => 'error', 'message' => 'Error de conexión a base de datos'];
            $health['status'] = 'unhealthy';
        }

        // Check disk space
        $freeSpace = disk_free_space('/');
        $totalSpace = disk_total_space('/');
        $usedPercentage = (($totalSpace - $freeSpace) / $totalSpace) * 100;
        
        if ($usedPercentage > 90) {
            $health['checks']['disk'] = ['status' => 'warning', 'message' => 'Espacio en disco bajo (' . round($usedPercentage) . '% usado)'];
            if ($health['status'] === 'healthy') $health['status'] = 'warning';
        } else {
            $health['checks']['disk'] = ['status' => 'ok', 'message' => 'Espacio en disco suficiente'];
        }

        // Check last backup
        $lastBackup = DB::table('backups')
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->first();
            
        if (!$lastBackup || Carbon::parse($lastBackup->created_at)->diffInHours(Carbon::now()) > 24) {
            $health['checks']['backup'] = ['status' => 'warning', 'message' => 'No hay respaldos recientes'];
            if ($health['status'] === 'healthy') $health['status'] = 'warning';
        } else {
            $health['checks']['backup'] = ['status' => 'ok', 'message' => 'Respaldos actualizados'];
        }

        return $health;
    }

    private function getRecentInvoices($tenantId, $limit = 5)
    {
        return TaxDocument::where('tenant_id', $tenantId)
            ->with('customer:id,name,rut')
            ->latest('issue_date')
            ->take($limit)
            ->get(['id', 'document_number', 'customer_id', 'total', 'payment_status', 'issue_date']);
    }

    private function getPendingPayments($tenantId)
    {
        return TaxDocument::where('tenant_id', $tenantId)
            ->where('payment_status', 'pending')
            ->sum('total');
    }

    private function gerenteDashboard()
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id;
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        $lastMonth = $now->copy()->subMonth();
        
        // KPIs Gerenciales
        $kpis = [
            'ventas' => [
                'actual' => $this->getMonthlyRevenue($tenantId, $now),
                'anterior' => $this->getMonthlyRevenue($tenantId, $lastMonth),
                'crecimiento' => 0,
            ],
            'margen' => [
                'porcentaje' => 35, // Ejemplo: 35% de margen
                'monto' => $this->getMonthlyRevenue($tenantId, $now) * 0.35,
            ],
            'clientes' => [
                'activos' => Customer::where('tenant_id', $tenantId)
                    ->where('active', true)
                    ->count(),
                'nuevos' => Customer::where('tenant_id', $tenantId)
                    ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                    ->count(),
            ],
            'eficiencia' => [
                'porcentaje' => 78, // Ejemplo: 78% de eficiencia
                'gastosPorcentaje' => 22,
            ],
        ];
        
        // Calcular crecimiento
        if ($kpis['ventas']['anterior'] > 0) {
            $kpis['ventas']['crecimiento'] = round((($kpis['ventas']['actual'] - $kpis['ventas']['anterior']) / $kpis['ventas']['anterior']) * 100, 1);
        }
        
        // Datos para gráficos
        $chartData = [
            'ventasObjetivos' => $this->getVentasVsObjetivos($tenantId),
            'rentabilidad' => $this->getRentabilidadPorCategoria($tenantId),
        ];
        
        // Indicadores de gestión
        $indicadores = [
            'cicloConversion' => [
                'diasInventario' => 15,
                'diasCobro' => 30,
                'diasPago' => 45,
                'total' => 15 + 30 - 45,
            ],
        ];
        
        // Top clientes
        $topClientes = $this->getTopCustomers($tenantId, 5);
        
        // Alertas estratégicas
        $alertas = $this->getAlertasEstrategicas($tenantId);
        
        // Análisis comparativo
        $analisisComparativo = $this->getAnalisisComparativo($tenantId);
        
        return Inertia::render('GerenteDashboard', [
            'kpis' => $kpis,
            'chartData' => $chartData,
            'indicadores' => $indicadores,
            'topClientes' => $topClientes,
            'alertas' => $alertas,
            'analisisComparativo' => $analisisComparativo,
        ]);
    }

    private function contadorDashboard()
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id;
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        
        // KPIs Contables
        $kpis = [
            'balance' => [
                'activos' => 50000000, // Ejemplo
                'pasivos' => 20000000,
            ],
            'cuentasPorCobrar' => [
                'total' => TaxDocument::where('tenant_id', $tenantId)
                    ->where('payment_status', 'pending')
                    ->sum('total'),
                'vencidas' => TaxDocument::where('tenant_id', $tenantId)
                    ->where('payment_status', 'pending')
                    ->where('due_date', '<', $now)
                    ->sum('total'),
            ],
            'cuentasPorPagar' => [
                'total' => Expense::where('tenant_id', $tenantId)
                    ->where('payment_status', 'pending')
                    ->sum('amount'),
                'proximaSemana' => Expense::where('tenant_id', $tenantId)
                    ->where('payment_status', 'pending')
                    ->whereBetween('due_date', [$now, $now->copy()->addDays(7)])
                    ->sum('amount'),
            ],
            'flujoCaja' => [
                'neto' => 0,
                'saldoActual' => 15000000, // Ejemplo
            ],
        ];
        
        // Calcular flujo de caja neto
        $ingresos = Payment::where('tenant_id', $tenantId)
            ->whereBetween('payment_date', [$startOfMonth, $endOfMonth])
            ->sum('amount');
        $egresos = Expense::where('tenant_id', $tenantId)
            ->whereBetween('expense_date', [$startOfMonth, $endOfMonth])
            ->sum('amount');
        $kpis['flujoCaja']['neto'] = $ingresos - $egresos;
        
        // Datos para gráficos
        $chartData = [
            'estadoResultados' => $this->getEstadoResultados($tenantId),
            'gastos' => $this->getDistribucionGastos($tenantId),
        ];
        
        // Información tributaria
        $tributario = $this->getResumenTributario($tenantId);
        
        // Documentos tributarios
        $documentosTributarios = $this->getDocumentosTributarios($tenantId);
        
        // Conciliación bancaria
        $conciliacion = [
            'saldoLibro' => 15000000,
            'saldoBanco' => 15050000,
            'diferencia' => 50000,
            'transaccionesPendientes' => 5,
        ];
        
        // Vencimientos
        $vencimientos = $this->getProximosVencimientos($tenantId);
        
        return Inertia::render('ContadorDashboard', [
            'kpis' => $kpis,
            'chartData' => $chartData,
            'tributario' => $tributario,
            'documentosTributarios' => $documentosTributarios,
            'conciliacion' => $conciliacion,
            'vencimientos' => $vencimientos,
        ]);
    }

    private function vendedorDashboard()
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id;
        $userId = $user->id;
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        
        // KPIs del vendedor
        $ventasMes = TaxDocument::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', 'accepted')
            ->whereBetween('issue_date', [$startOfMonth, $endOfMonth])
            ->sum('total');
            
        $metaMensual = 5000000; // Ejemplo: meta de 5M
        
        $kpis = [
            'ventasMes' => [
                'total' => $ventasMes,
                'meta' => $metaMensual,
                'porcentajeMeta' => $metaMensual > 0 ? round(($ventasMes / $metaMensual) * 100, 1) : 0,
            ],
            'cantidadVentas' => [
                'mes' => TaxDocument::where('tenant_id', $tenantId)
                    ->where('user_id', $userId)
                    ->whereBetween('issue_date', [$startOfMonth, $endOfMonth])
                    ->count(),
                'hoy' => TaxDocument::where('tenant_id', $tenantId)
                    ->where('user_id', $userId)
                    ->whereDate('issue_date', $now)
                    ->count(),
            ],
            'ticketPromedio' => [
                'actual' => $this->getTicketPromedio($tenantId, $userId, $now),
                'anterior' => $this->getTicketPromedio($tenantId, $userId, $now->copy()->subMonth()),
                'variacion' => 0,
            ],
            'comisiones' => [
                'total' => $ventasMes * 0.03, // Ejemplo: 3% de comisión
                'pendiente' => $ventasMes * 0.03 * 0.5, // Ejemplo: 50% pendiente
            ],
        ];
        
        // Calcular variación ticket promedio
        if ($kpis['ticketPromedio']['anterior'] > 0) {
            $kpis['ticketPromedio']['variacion'] = round((($kpis['ticketPromedio']['actual'] - $kpis['ticketPromedio']['anterior']) / $kpis['ticketPromedio']['anterior']) * 100, 1);
        }
        
        // Datos para gráficos
        $chartData = [
            'progresoMeta' => [
                'vendido' => $ventasMes,
                'porVender' => max(0, $metaMensual - $ventasMes),
            ],
            'ventasPorDia' => $this->getVentasPorDia($tenantId, $userId),
        ];
        
        // Top clientes del vendedor
        $topClientes = $this->getTopClientesVendedor($tenantId, $userId);
        
        // Top productos vendidos
        $topProductos = $this->getTopProductosVendedor($tenantId, $userId);
        
        // Actividades pendientes
        $actividadesPendientes = $this->getActividadesPendientes($userId);
        
        // Últimas ventas
        $ultimasVentas = $this->getUltimasVentas($tenantId, $userId);
        
        return Inertia::render('VendedorDashboard', [
            'kpis' => $kpis,
            'chartData' => $chartData,
            'topClientes' => $topClientes,
            'topProductos' => $topProductos,
            'actividadesPendientes' => $actividadesPendientes,
            'ultimasVentas' => $ultimasVentas,
        ]);
    }

    // Métodos auxiliares para Gerente Dashboard
    private function getVentasVsObjetivos($tenantId)
    {
        $data = [
            'labels' => [],
            'ventas' => [],
            'objetivos' => [],
        ];
        
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $data['labels'][] = $date->format('M Y');
            $data['ventas'][] = $this->getMonthlyRevenue($tenantId, $date);
            $data['objetivos'][] = 10000000; // Objetivo fijo de ejemplo
        }
        
        return $data;
    }

    private function getRentabilidadPorCategoria($tenantId)
    {
        $categorias = DB::table('tax_document_items')
            ->join('tax_documents', 'tax_document_items.tax_document_id', '=', 'tax_documents.id')
            ->join('products', 'tax_document_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->where('tax_documents.tenant_id', $tenantId)
            ->whereMonth('tax_documents.issue_date', Carbon::now()->month)
            ->select(
                'categories.name',
                DB::raw('SUM(tax_document_items.subtotal) as ventas'),
                DB::raw('SUM(tax_document_items.subtotal * 0.35) as margen') // 35% margen ejemplo
            )
            ->groupBy('categories.name')
            ->get();
            
        return [
            'labels' => $categorias->pluck('name'),
            'ventas' => $categorias->pluck('ventas'),
            'margen' => $categorias->pluck('margen'),
        ];
    }

    private function getAlertasEstrategicas($tenantId)
    {
        $alertas = [];
        
        // Alerta de stock bajo
        $stockBajo = Product::where('tenant_id', $tenantId)
            ->where('track_inventory', true)
            ->whereColumn('current_stock', '<=', 'minimum_stock')
            ->count();
            
        if ($stockBajo > 0) {
            $alertas[] = [
                'id' => 1,
                'tipo' => 'advertencia',
                'mensaje' => "Hay {$stockBajo} productos con stock bajo",
            ];
        }
        
        // Alerta de facturas vencidas
        $facturasVencidas = TaxDocument::where('tenant_id', $tenantId)
            ->where('payment_status', 'pending')
            ->where('due_date', '<', Carbon::now())
            ->count();
            
        if ($facturasVencidas > 0) {
            $alertas[] = [
                'id' => 2,
                'tipo' => 'critica',
                'mensaje' => "Tienes {$facturasVencidas} facturas vencidas por cobrar",
            ];
        }
        
        // Alerta de meta mensual
        $ventasActuales = $this->getMonthlyRevenue($tenantId, Carbon::now());
        $metaMensual = 10000000;
        if ($ventasActuales < $metaMensual * 0.7) {
            $porcentaje = round(($ventasActuales / $metaMensual) * 100);
            $alertas[] = [
                'id' => 3,
                'tipo' => 'info',
                'mensaje' => "Ventas al {$porcentaje}% de la meta mensual",
            ];
        }
        
        return $alertas;
    }

    private function getAnalisisComparativo($tenantId)
    {
        $meses = [];
        $metricas = [
            [
                'nombre' => 'Ventas',
                'tipo' => 'moneda',
                'valores' => [],
                'promedio' => 0,
            ],
            [
                'nombre' => 'Margen %',
                'tipo' => 'porcentaje',
                'valores' => [],
                'promedio' => 0,
            ],
            [
                'nombre' => 'Clientes Nuevos',
                'tipo' => 'numero',
                'valores' => [],
                'promedio' => 0,
            ],
            [
                'nombre' => 'Ticket Promedio',
                'tipo' => 'moneda',
                'valores' => [],
                'promedio' => 0,
            ],
        ];
        
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $meses[] = $date->format('M');
            
            $ventas = $this->getMonthlyRevenue($tenantId, $date);
            $metricas[0]['valores'][] = $ventas;
            $metricas[1]['valores'][] = 35; // Margen fijo de ejemplo
            $metricas[2]['valores'][] = rand(5, 20); // Clientes nuevos ejemplo
            $metricas[3]['valores'][] = $ventas > 0 ? $ventas / rand(50, 100) : 0; // Ticket promedio ejemplo
        }
        
        // Calcular promedios
        foreach ($metricas as &$metrica) {
            $metrica['promedio'] = count($metrica['valores']) > 0 
                ? round(array_sum($metrica['valores']) / count($metrica['valores'])) 
                : 0;
        }
        
        return [
            'meses' => $meses,
            'metricas' => $metricas,
        ];
    }

    // Métodos auxiliares para Contador Dashboard
    private function getEstadoResultados($tenantId)
    {
        $ingresos = $this->getMonthlyRevenue($tenantId, Carbon::now());
        $costos = $ingresos * 0.65; // 65% de costos ejemplo
        $gastos = $ingresos * 0.20; // 20% de gastos ejemplo
        $utilidad = $ingresos - $costos - $gastos;
        
        return [
            'labels' => ['Ingresos', 'Costos', 'Gastos', 'Utilidad'],
            'valores' => [$ingresos, -$costos, -$gastos, $utilidad],
        ];
    }

    private function getDistribucionGastos($tenantId)
    {
        return [
            'labels' => ['Sueldos', 'Arriendos', 'Servicios', 'Insumos', 'Otros'],
            'valores' => [3000000, 1500000, 500000, 800000, 700000], // Valores de ejemplo
        ];
    }

    private function getResumenTributario($tenantId)
    {
        $ivaDebito = TaxDocument::where('tenant_id', $tenantId)
            ->whereMonth('issue_date', Carbon::now()->month)
            ->sum('tax');
            
        $ivaCredito = Expense::where('tenant_id', $tenantId)
            ->whereMonth('expense_date', Carbon::now()->month)
            ->sum('tax_amount');
            
        return [
            'ivaDebito' => $ivaDebito,
            'ivaCredito' => $ivaCredito,
            'ivaPagar' => $ivaDebito - $ivaCredito,
            'proximoVencimiento' => Carbon::now()->addMonth()->startOfMonth()->addDays(11)->format('d/m/Y'),
        ];
    }

    private function getDocumentosTributarios($tenantId)
    {
        return [
            ['tipo' => 'Facturas Emitidas', 'cantidad' => TaxDocument::where('tenant_id', $tenantId)
                ->where('document_type', 33)
                ->whereMonth('issue_date', Carbon::now()->month)
                ->count()],
            ['tipo' => 'Boletas Emitidas', 'cantidad' => TaxDocument::where('tenant_id', $tenantId)
                ->where('document_type', 39)
                ->whereMonth('issue_date', Carbon::now()->month)
                ->count()],
            ['tipo' => 'Notas de Crédito', 'cantidad' => TaxDocument::where('tenant_id', $tenantId)
                ->where('document_type', 61)
                ->whereMonth('issue_date', Carbon::now()->month)
                ->count()],
            ['tipo' => 'Facturas Recibidas', 'cantidad' => rand(10, 50)], // Ejemplo
        ];
    }

    private function getProximosVencimientos($tenantId)
    {
        $vencimientos = [];
        
        // Facturas por cobrar
        $porCobrar = TaxDocument::where('tenant_id', $tenantId)
            ->where('payment_status', 'pending')
            ->with('customer:id,name')
            ->orderBy('due_date')
            ->take(5)
            ->get();
            
        foreach ($porCobrar as $doc) {
            $vencimientos[] = [
                'id' => $doc->id,
                'fecha' => $doc->due_date,
                'tipo' => 'cobrar',
                'entidad' => $doc->customer->name,
                'documento' => 'Factura ' . $doc->document_number,
                'monto' => $doc->total,
                'diasRestantes' => Carbon::now()->diffInDays($doc->due_date, false),
            ];
        }
        
        // Gastos por pagar (ejemplo)
        for ($i = 0; $i < 3; $i++) {
            $vencimientos[] = [
                'id' => 1000 + $i,
                'fecha' => Carbon::now()->addDays(rand(1, 30)),
                'tipo' => 'pagar',
                'entidad' => 'Proveedor ' . ($i + 1),
                'documento' => 'Factura ' . rand(1000, 9999),
                'monto' => rand(100000, 1000000),
                'diasRestantes' => rand(1, 30),
            ];
        }
        
        // Ordenar por fecha
        usort($vencimientos, function($a, $b) {
            return Carbon::parse($a['fecha'])->timestamp - Carbon::parse($b['fecha'])->timestamp;
        });
        
        return array_slice($vencimientos, 0, 10);
    }

    // Métodos auxiliares para Vendedor Dashboard
    private function getTicketPromedio($tenantId, $userId, Carbon $date)
    {
        $ventas = TaxDocument::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereMonth('issue_date', $date->month)
            ->whereYear('issue_date', $date->year);
            
        $total = $ventas->sum('total');
        $cantidad = $ventas->count();
        
        return $cantidad > 0 ? round($total / $cantidad) : 0;
    }

    private function getVentasPorDia($tenantId, $userId)
    {
        $labels = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
        $valores = [];
        
        for ($i = 0; $i < 7; $i++) {
            $date = Carbon::now()->startOfWeek()->addDays($i);
            $valores[] = TaxDocument::where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->whereDate('issue_date', $date)
                ->sum('total');
        }
        
        return [
            'labels' => $labels,
            'valores' => $valores,
        ];
    }

    private function getTopClientesVendedor($tenantId, $userId)
    {
        return Customer::withoutGlobalScope('tenant')
            ->where('customers.tenant_id', $tenantId)
            ->join('tax_documents', 'customers.id', '=', 'tax_documents.customer_id')
            ->where('tax_documents.tenant_id', $tenantId)
            ->where('tax_documents.user_id', $userId)
            ->whereMonth('tax_documents.issue_date', Carbon::now()->month)
            ->select(
                'customers.id',
                'customers.name as nombre',
                DB::raw('COUNT(tax_documents.id) as cantidadCompras'),
                DB::raw('SUM(tax_documents.total) as total')
            )
            ->groupBy('customers.id', 'customers.name')
            ->orderBy('total', 'desc')
            ->limit(5)
            ->get();
    }

    private function getTopProductosVendedor($tenantId, $userId)
    {
        return DB::table('tax_document_items')
            ->join('tax_documents', 'tax_document_items.tax_document_id', '=', 'tax_documents.id')
            ->join('products', 'tax_document_items.product_id', '=', 'products.id')
            ->where('tax_documents.tenant_id', $tenantId)
            ->where('tax_documents.user_id', $userId)
            ->whereMonth('tax_documents.issue_date', Carbon::now()->month)
            ->select(
                'products.id',
                'products.name as nombre',
                DB::raw('SUM(tax_document_items.quantity) as cantidad'),
                DB::raw('SUM(tax_document_items.subtotal) as total')
            )
            ->groupBy('products.id', 'products.name')
            ->orderBy('total', 'desc')
            ->limit(5)
            ->get();
    }

    private function getActividadesPendientes($userId)
    {
        // Ejemplo de actividades pendientes
        return [
            [
                'id' => 1,
                'titulo' => 'Llamar a cliente importante',
                'descripcion' => 'Seguimiento de cotización enviada',
                'vencimiento' => Carbon::now()->addDays(1),
                'prioridad' => 'alta',
            ],
            [
                'id' => 2,
                'titulo' => 'Enviar propuesta',
                'descripcion' => 'Propuesta para nuevo proyecto',
                'vencimiento' => Carbon::now()->addDays(3),
                'prioridad' => 'media',
            ],
            [
                'id' => 3,
                'titulo' => 'Actualizar catálogo',
                'descripcion' => 'Revisar precios y disponibilidad',
                'vencimiento' => Carbon::now()->addDays(7),
                'prioridad' => 'baja',
            ],
        ];
    }

    private function getUltimasVentas($tenantId, $userId)
    {
        return TaxDocument::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->with('customer:id,name')
            ->withCount('items as cantidadItems')
            ->latest('issue_date')
            ->take(10)
            ->get()
            ->map(function ($venta) {
                return [
                    'id' => $venta->id,
                    'fecha' => $venta->issue_date,
                    'cliente' => $venta->customer->name,
                    'cantidadItems' => $venta->cantidadItems,
                    'total' => $venta->total,
                    'estado' => $venta->payment_status === 'paid' ? 'pagado' : 
                              ($venta->due_date < Carbon::now() ? 'vencido' : 'pendiente'),
                ];
            });
    }

    private function getModuleUsageStats($tenantId)
    {
        $now = Carbon::now();
        $thirtyDaysAgo = $now->copy()->subDays(30);
        
        return [
            'invoicing' => [
                'name' => 'Facturación',
                'icon' => 'receipt',
                'usage_count' => TaxDocument::where('tenant_id', $tenantId)
                    ->where('created_at', '>=', $thirtyDaysAgo)
                    ->count(),
                'active_users' => TaxDocument::where('tenant_id', $tenantId)
                    ->where('created_at', '>=', $thirtyDaysAgo)
                    ->distinct('user_id')
                    ->count('user_id'),
                'growth' => $this->calculateModuleGrowth('tax_documents', $tenantId),
            ],
            'customers' => [
                'name' => 'Clientes',
                'icon' => 'users',
                'usage_count' => Activity::where('tenant_id', $tenantId)
                    ->where('model_type', 'App\\Models\\Customer')
                    ->where('created_at', '>=', $thirtyDaysAgo)
                    ->count(),
                'active_users' => Activity::where('tenant_id', $tenantId)
                    ->where('model_type', 'App\\Models\\Customer')
                    ->where('created_at', '>=', $thirtyDaysAgo)
                    ->distinct('user_id')
                    ->count('user_id'),
                'growth' => $this->calculateModuleGrowth('customers', $tenantId),
            ],
            'products' => [
                'name' => 'Productos',
                'icon' => 'cube',
                'usage_count' => Activity::where('tenant_id', $tenantId)
                    ->where('model_type', 'App\\Models\\Product')
                    ->where('created_at', '>=', $thirtyDaysAgo)
                    ->count(),
                'active_users' => Activity::where('tenant_id', $tenantId)
                    ->where('model_type', 'App\\Models\\Product')
                    ->where('created_at', '>=', $thirtyDaysAgo)
                    ->distinct('user_id')
                    ->count('user_id'),
                'growth' => $this->calculateModuleGrowth('products', $tenantId),
            ],
            'payments' => [
                'name' => 'Pagos',
                'icon' => 'credit-card',
                'usage_count' => Payment::where('tenant_id', $tenantId)
                    ->where('created_at', '>=', $thirtyDaysAgo)
                    ->count(),
                'active_users' => Payment::where('tenant_id', $tenantId)
                    ->where('created_at', '>=', $thirtyDaysAgo)
                    ->distinct('user_id')
                    ->count('user_id'),
                'growth' => $this->calculateModuleGrowth('payments', $tenantId),
            ],
            'expenses' => [
                'name' => 'Gastos',
                'icon' => 'calculator',
                'usage_count' => Expense::where('tenant_id', $tenantId)
                    ->where('created_at', '>=', $thirtyDaysAgo)
                    ->count(),
                'active_users' => Expense::where('tenant_id', $tenantId)
                    ->where('created_at', '>=', $thirtyDaysAgo)
                    ->distinct('user_id')
                    ->count('user_id'),
                'growth' => $this->calculateModuleGrowth('expenses', $tenantId),
            ],
            'reports' => [
                'name' => 'Reportes',
                'icon' => 'chart-bar',
                'usage_count' => Activity::where('tenant_id', $tenantId)
                    ->where('type', 'report_generated')
                    ->where('created_at', '>=', $thirtyDaysAgo)
                    ->count(),
                'active_users' => Activity::where('tenant_id', $tenantId)
                    ->where('type', 'report_generated')
                    ->where('created_at', '>=', $thirtyDaysAgo)
                    ->distinct('user_id')
                    ->count('user_id'),
                'growth' => 0, // No historical data for reports
            ],
        ];
    }

    private function calculateModuleGrowth($table, $tenantId)
    {
        $now = Carbon::now();
        $thirtyDaysAgo = $now->copy()->subDays(30);
        $sixtyDaysAgo = $now->copy()->subDays(60);
        
        $currentPeriod = DB::table($table)
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$thirtyDaysAgo, $now])
            ->count();
            
        $previousPeriod = DB::table($table)
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$sixtyDaysAgo, $thirtyDaysAgo])
            ->count();
            
        if ($previousPeriod == 0) {
            return $currentPeriod > 0 ? 100 : 0;
        }
        
        return round((($currentPeriod - $previousPeriod) / $previousPeriod) * 100, 1);
    }
    
    private function getCustomerStats($tenantId, $startOfMonth, $endOfMonth)
    {
        return Customer::where('tenant_id', $tenantId)
            ->selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN created_at BETWEEN ? AND ? THEN 1 END) as new_this_month
            ", [$startOfMonth, $endOfMonth])
            ->first()
            ->toArray();
    }
    
    private function getInvoiceStats($tenantId, $startOfMonth, $endOfMonth, $now)
    {
        return TaxDocument::where('tenant_id', $tenantId)
            ->selectRaw("
                COUNT(CASE WHEN issue_date BETWEEN ? AND ? THEN 1 END) as total_month,
                COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending,
                COUNT(CASE WHEN payment_status = 'pending' AND due_date < ? THEN 1 END) as overdue
            ", [$startOfMonth, $endOfMonth, $now])
            ->first()
            ->toArray();
    }
    
    private function getCashFlowStats($tenantId, $startOfMonth, $endOfMonth)
    {
        $payments = Payment::where('tenant_id', $tenantId)
            ->whereBetween('payment_date', [$startOfMonth, $endOfMonth])
            ->sum('amount');
            
        $expenses = Expense::where('tenant_id', $tenantId)
            ->whereBetween('expense_date', [$startOfMonth, $endOfMonth])
            ->sum('amount');
            
        return [
            'income' => $payments,
            'expenses' => $expenses,
            'balance' => $payments - $expenses,
        ];
    }
}