<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\TaxDocument;
use App\Models\Customer;
use App\Models\Product;
use App\Models\InventoryMovement;
use App\Traits\ChecksPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;

class ReportController extends Controller
{
    use ChecksPermissions;
    public function index()
    {
        // $this->checkPermission('reports.view');
        return Inertia::render('Reports/Index');
    }

    public function sales(Request $request)
    {
        // $this->checkPermission('reports.view');
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'group_by' => 'nullable|in:day,week,month,year',
            'format' => 'nullable|in:view,pdf,excel'
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();
        $groupBy = $request->group_by ?? 'day';
        $tenantId = auth()->user()->tenant_id;
        
        // Cache key único por tenant, fechas y agrupación
        $cacheKey = "reports:sales:{$tenantId}:" . md5($startDate . $endDate . $groupBy);
        $cacheTTL = 600; // 10 minutos

        // Ventas por período con caché
        $salesByPeriod = Cache::remember($cacheKey . ':by_period', $cacheTTL, function() use ($tenantId, $startDate, $endDate, $groupBy) {
            return TaxDocument::where('tenant_id', $tenantId)
            ->whereIn('type', ['invoice', 'receipt'])
            ->whereBetween('issue_date', [$startDate, $endDate])
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->select(
                DB::raw($this->getDateGrouping($groupBy) . ' as period'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(subtotal) as net_total'),
                DB::raw('SUM(tax_amount) as tax_total'),
                DB::raw('SUM(total) as total')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get();
        });

        // Top productos con caché
        $topProducts = Cache::remember($cacheKey . ':top_products', $cacheTTL, function() use ($tenantId, $startDate, $endDate) {
            return DB::table('tax_document_items')
            ->join('tax_documents', 'tax_documents.id', '=', 'tax_document_items.tax_document_id')
            ->join('products', 'products.id', '=', 'tax_document_items.product_id')
            ->where('tax_documents.tenant_id', $tenantId)
            ->whereIn('tax_documents.type', ['invoice', 'receipt'])
            ->whereBetween('tax_documents.issue_date', [$startDate, $endDate])
            ->whereNotIn('tax_documents.status', ['cancelled', 'draft'])
            ->select(
                'products.name',
                'products.sku',
                DB::raw('SUM(tax_document_items.quantity) as total_quantity'),
                DB::raw('SUM(tax_document_items.total) as total_revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderBy('total_revenue', 'desc')
            ->limit(10)
            ->get();
        });

        // Top clientes con caché
        $topCustomers = Cache::remember($cacheKey . ':top_customers', $cacheTTL, function() use ($tenantId, $startDate, $endDate) {
            return TaxDocument::where('tenant_id', $tenantId)
            ->whereIn('type', ['invoice', 'receipt'])
            ->whereBetween('issue_date', [$startDate, $endDate])
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->join('customers', 'customers.id', '=', 'tax_documents.customer_id')
            ->select(
                'customers.id',
                'customers.name',
                'customers.rut',
                DB::raw('COUNT(tax_documents.id) as document_count'),
                DB::raw('SUM(tax_documents.total) as total_amount')
            )
            ->groupBy('customers.id', 'customers.name', 'customers.rut')
            ->orderBy('total_amount', 'desc')
            ->limit(10)
            ->get();
        });

        // Resumen general con caché
        $summary = Cache::remember($cacheKey . ':summary', $cacheTTL, function() use ($tenantId, $startDate, $endDate) {
            return TaxDocument::where('tenant_id', $tenantId)
            ->whereIn('type', ['invoice', 'receipt'])
            ->whereBetween('issue_date', [$startDate, $endDate])
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->select([
                DB::raw('COUNT(*) as total_documents'),
                DB::raw('SUM(subtotal) as total_net'),
                DB::raw('SUM(tax_amount) as total_tax'),
                DB::raw('SUM(total) as total_amount'),
                DB::raw('AVG(total) as average_sale')
            ])
            ->first();
        });

        // Comparación con período anterior con caché
        $previousStartDate = $startDate->copy()->subDays($endDate->diffInDays($startDate) + 1);
        $previousEndDate = $startDate->copy()->subDay();
        
        $previousCacheKey = "reports:sales:{$tenantId}:" . md5($previousStartDate . $previousEndDate);

        $previousSummary = Cache::remember($previousCacheKey . ':previous', $cacheTTL, function() use ($tenantId, $previousStartDate, $previousEndDate) {
            return TaxDocument::where('tenant_id', $tenantId)
            ->whereIn('type', ['invoice', 'receipt'])
            ->whereBetween('issue_date', [$previousStartDate, $previousEndDate])
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->select([
                DB::raw('SUM(total) as total_amount')
            ])
            ->first();
        });

        $growthRate = 0;
        if ($previousSummary && $previousSummary->total_amount > 0) {
            $growthRate = (($summary->total_amount - $previousSummary->total_amount) / $previousSummary->total_amount) * 100;
        }

        $data = [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
                'days' => $endDate->diffInDays($startDate) + 1
            ],
            'summary' => $summary,
            'growth_rate' => round($growthRate, 2),
            'sales_by_period' => $salesByPeriod,
            'top_products' => $topProducts,
            'top_customers' => $topCustomers,
            'filters' => [
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'group_by' => $groupBy
            ]
        ];

        if ($request->format === 'pdf') {
            $pdf = Pdf::loadView('reports.sales-pdf', $data);
            return $pdf->download('reporte-ventas-' . $startDate->format('Y-m-d') . '-' . $endDate->format('Y-m-d') . '.pdf');
        }

        return Inertia::render('Reports/Sales', $data);
    }

    public function taxes(Request $request)
    {
        // $this->checkPermission('reports.view');
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'format' => 'nullable|in:view,pdf,excel'
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();
        $tenantId = auth()->user()->tenant_id;

        // IVA Ventas (Débito Fiscal)
        $salesTax = TaxDocument::where('tenant_id', $tenantId)
            ->whereIn('type', ['invoice', 'receipt', 'debit_note'])
            ->whereBetween('issue_date', [$startDate, $endDate])
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->select([
                'type',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(subtotal) as net_amount'),
                DB::raw('SUM(tax_amount) as tax_amount'),
                DB::raw('SUM(total) as total_amount')
            ])
            ->groupBy('type')
            ->get();

        // Notas de crédito (reducen el débito fiscal)
        $creditNotes = TaxDocument::where('tenant_id', $tenantId)
            ->where('type', 'credit_note')
            ->whereBetween('issue_date', [$startDate, $endDate])
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->select([
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(subtotal) as net_amount'),
                DB::raw('SUM(tax_amount) as tax_amount'),
                DB::raw('SUM(total) as total_amount')
            ])
            ->first();

        // Calcular totales
        $totalSalesTax = $salesTax->sum('tax_amount');
        $totalCreditTax = $creditNotes ? $creditNotes->tax_amount : 0;
        $netSalesTax = $totalSalesTax - $totalCreditTax;

        // TODO: Cuando se implemente el módulo de gastos, aquí se calculará el IVA Crédito Fiscal
        $purchaseTax = 0; // Por ahora es 0

        // Balance IVA
        $vatBalance = $netSalesTax - $purchaseTax;

        // Detalle mensual
        $monthlyDetail = TaxDocument::where('tenant_id', $tenantId)
            ->whereIn('type', ['invoice', 'receipt', 'debit_note', 'credit_note'])
            ->whereBetween('issue_date', [$startDate, $endDate])
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->select([
                DB::raw("strftime('%Y', issue_date) as year"),
                DB::raw("strftime('%m', issue_date) as month"),
                'type',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(subtotal) as net_amount'),
                DB::raw('SUM(tax_amount) as tax_amount'),
                DB::raw('SUM(total) as total_amount')
            ])
            ->groupBy('year', 'month', 'type')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->groupBy(function ($item) {
                return $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT);
            });

        $data = [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ],
            'sales_tax' => [
                'documents' => $salesTax,
                'total' => $totalSalesTax,
                'credit_notes' => $creditNotes,
                'net' => $netSalesTax
            ],
            'purchase_tax' => [
                'total' => $purchaseTax
            ],
            'vat_balance' => $vatBalance,
            'monthly_detail' => $monthlyDetail,
            'filters' => [
                'start_date' => $request->start_date,
                'end_date' => $request->end_date
            ]
        ];

        if ($request->format === 'pdf') {
            $pdf = Pdf::loadView('reports.taxes-pdf', $data);
            return $pdf->download('reporte-impuestos-' . $startDate->format('Y-m-d') . '-' . $endDate->format('Y-m-d') . '.pdf');
        }

        return Inertia::render('Reports/Taxes', $data);
    }

    public function inventory(Request $request)
    {
        // $this->checkPermission('reports.view');
        $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'status' => 'nullable|in:all,low_stock,out_of_stock',
            'format' => 'nullable|in:view,pdf,excel'
        ]);

        $tenantId = auth()->user()->tenant_id;
        $query = Product::where('tenant_id', $tenantId)
            ->where('is_service', false);

        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->status === 'low_stock') {
            $query->whereRaw('stock_quantity <= minimum_stock AND stock_quantity > 0');
        } elseif ($request->status === 'out_of_stock') {
            $query->where('stock_quantity', 0);
        }

        // Primero obtenemos estadísticas generales sin paginar
        $statsQuery = clone $query;
        $allProducts = $statsQuery->get();
        
        // Estadísticas generales
        $statistics = [
            'total_products' => $allProducts->count(),
            'total_value' => $allProducts->sum(function ($product) {
                return $product->stock_quantity * $product->cost;
            }),
            'low_stock_count' => $allProducts->filter(function ($product) {
                return $product->stock_quantity <= $product->minimum_stock && $product->stock_quantity > 0;
            })->count(),
            'out_of_stock_count' => $allProducts->where('stock_quantity', 0)->count()
        ];
        
        // Ahora paginamos para la tabla
        $products = $query->with('category')->paginate(20)->withQueryString();

        // Movimientos recientes de todos los productos
        $recentMovements = InventoryMovement::where('tenant_id', $tenantId)
            ->with('product')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        // Valorización por categoría usando todos los productos
        $valuationByCategory = $allProducts->groupBy('category.name')
            ->map(function ($products, $category) {
                return [
                    'category' => $category ?: 'Sin categoría',
                    'count' => $products->count(),
                    'total_stock' => $products->sum('stock_quantity'),
                    'total_value' => $products->sum(function ($product) {
                        return $product->stock_quantity * $product->cost;
                    })
                ];
            })->values();

        $data = [
            'products' => $products,
            'statistics' => $statistics,
            'recent_movements' => $recentMovements,
            'valuation_by_category' => $valuationByCategory,
            'filters' => [
                'category_id' => $request->category_id,
                'status' => $request->status ?? 'all'
            ],
            'categories' => \App\Models\Category::where('tenant_id', $tenantId)->get()
        ];

        if ($request->format === 'pdf') {
            $pdf = Pdf::loadView('reports.inventory-pdf', $data);
            return $pdf->download('reporte-inventario-' . now()->format('Y-m-d') . '.pdf');
        }

        return Inertia::render('Reports/Inventory', $data);
    }

    public function customerBalance(Request $request)
    {
        // $this->checkPermission('reports.view');
        $request->validate([
            'status' => 'nullable|in:all,with_balance,overdue',
            'format' => 'nullable|in:view,pdf,excel'
        ]);

        $tenantId = auth()->user()->tenant_id;
        $query = Customer::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->withSum(['taxDocuments as total_debt' => function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId)
                    ->whereIn('type', ['invoice', 'debit_note'])
                    ->whereNotIn('status', ['cancelled', 'draft']);
            }], 'total')
            ->withSum(['taxDocuments as total_credit' => function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId)
                    ->where('type', 'credit_note')
                    ->whereNotIn('status', ['cancelled', 'draft']);
            }], 'total');

        $customers = $query->get()->map(function ($customer) {
            $customer->balance = ($customer->total_debt ?? 0) - ($customer->total_credit ?? 0);
            
            // Calcular documentos vencidos
            $overdueAmount = TaxDocument::where('customer_id', $customer->id)
                ->where('tenant_id', auth()->user()->tenant_id)
                ->whereIn('type', ['invoice', 'debit_note'])
                ->whereNotIn('status', ['cancelled', 'draft', 'paid'])
                ->where('due_date', '<', now())
                ->sum('total');
            
            $customer->overdue_amount = $overdueAmount;
            
            return $customer;
        });

        if ($request->status === 'with_balance') {
            $customers = $customers->filter(function ($customer) {
                return $customer->balance > 0;
            });
        } elseif ($request->status === 'overdue') {
            $customers = $customers->filter(function ($customer) {
                return $customer->overdue_amount > 0;
            });
        }

        // Estadísticas
        $statistics = [
            'total_customers' => $customers->count(),
            'total_balance' => $customers->sum('balance'),
            'total_overdue' => $customers->sum('overdue_amount'),
            'customers_with_balance' => $customers->where('balance', '>', 0)->count(),
            'customers_overdue' => $customers->where('overdue_amount', '>', 0)->count()
        ];

        // Antigüedad de saldos global
        $today = now()->format('Y-m-d');
        $agingAnalysis = DB::table('tax_documents')
            ->where('tenant_id', $tenantId)
            ->whereIn('type', ['invoice', 'debit_note'])
            ->whereNotIn('status', ['cancelled', 'draft', 'paid'])
            ->where('total', '>', 0)
            ->select([
                DB::raw("SUM(CASE WHEN due_date >= date('$today') THEN total ELSE 0 END) as current"),
                DB::raw("SUM(CASE WHEN due_date < date('$today') AND due_date >= date('$today', '-30 days') THEN total ELSE 0 END) as days_30"),
                DB::raw("SUM(CASE WHEN due_date < date('$today', '-30 days') AND due_date >= date('$today', '-60 days') THEN total ELSE 0 END) as days_60"),
                DB::raw("SUM(CASE WHEN due_date < date('$today', '-60 days') AND due_date >= date('$today', '-90 days') THEN total ELSE 0 END) as days_90"),
                DB::raw("SUM(CASE WHEN due_date < date('$today', '-90 days') THEN total ELSE 0 END) as over_90")
            ])
            ->first();

        $data = [
            'customers' => $customers->values(),
            'statistics' => $statistics,
            'aging_analysis' => $agingAnalysis,
            'filters' => [
                'status' => $request->status ?? 'all'
            ]
        ];

        if ($request->format === 'pdf') {
            $pdf = Pdf::loadView('reports.customer-balance-pdf', $data);
            return $pdf->download('reporte-saldos-clientes-' . now()->format('Y-m-d') . '.pdf');
        }

        return Inertia::render('Reports/CustomerBalance', $data);
    }

    private function getDateGrouping($groupBy)
    {
        switch ($groupBy) {
            case 'day':
                return "DATE(issue_date)";
            case 'week':
                return "DATE(issue_date, 'weekday 0', '-6 days')";
            case 'month':
                return "strftime('%Y-%m-01', issue_date)";
            case 'year':
                return "strftime('%Y-01-01', issue_date)";
            default:
                return "DATE(issue_date)";
        }
    }

    public function cashFlow(Request $request)
    {
        // $this->checkPermission('reports.view');
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'format' => 'nullable|in:view,pdf,excel'
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();
        $tenantId = auth()->user()->tenant_id;

        // Ingresos por ventas
        $salesIncome = TaxDocument::where('tenant_id', $tenantId)
            ->whereIn('type', ['invoice', 'receipt'])
            ->whereBetween('issue_date', [$startDate, $endDate])
            ->whereIn('status', ['paid', 'issued'])
            ->select([
                DB::raw('DATE(issue_date) as date'),
                DB::raw('SUM(total) as amount'),
                DB::raw("'income' as type"),
                DB::raw("'sales' as category")
            ])
            ->groupBy('date')
            ->get();

        // Egresos por gastos
        $expenses = \App\Models\Expense::where('tenant_id', $tenantId)
            ->whereBetween('issue_date', [$startDate, $endDate])
            ->whereIn('status', ['paid'])
            ->select([
                DB::raw('DATE(issue_date) as date'),
                DB::raw('SUM(total_amount) as amount'),
                DB::raw("'expense' as type"),
                DB::raw("category")
            ])
            ->groupBy('date', 'category')
            ->get();

        // Pagos recibidos
        $payments = \App\Models\Payment::where('tenant_id', $tenantId)
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->where('status', 'confirmed')
            ->select([
                DB::raw('DATE(payment_date) as date'),
                DB::raw('SUM(amount) as amount'),
                DB::raw("'income' as type"),
                DB::raw("'payments' as category")
            ])
            ->groupBy('date')
            ->get();

        // Combinar todos los movimientos
        $allMovements = collect()
            ->merge($salesIncome)
            ->merge($expenses)
            ->merge($payments)
            ->sortBy('date');

        // Calcular flujo acumulado
        $runningBalance = 0;
        $cashFlowData = $allMovements->groupBy('date')->map(function ($dayMovements, $date) use (&$runningBalance) {
            $dailyIncome = $dayMovements->where('type', 'income')->sum('amount');
            $dailyExpenses = $dayMovements->where('type', 'expense')->sum('amount');
            $netFlow = $dailyIncome - $dailyExpenses;
            $runningBalance += $netFlow;

            return [
                'date' => $date,
                'income' => $dailyIncome,
                'expenses' => $dailyExpenses,
                'net_flow' => $netFlow,
                'running_balance' => $runningBalance,
                'movements' => $dayMovements
            ];
        })->values();

        // Resumen del período
        $totalIncome = $allMovements->where('type', 'income')->sum('amount');
        $totalExpenses = $allMovements->where('type', 'expense')->sum('amount');
        $netCashFlow = $totalIncome - $totalExpenses;

        // Proyección simple (próximos 30 días basado en promedio)
        $avgDailyFlow = $cashFlowData->avg('net_flow') ?? 0;
        $projectedBalance = $runningBalance + ($avgDailyFlow * 30);

        $data = [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ],
            'summary' => [
                'total_income' => $totalIncome,
                'total_expenses' => $totalExpenses,
                'net_cash_flow' => $netCashFlow,
                'final_balance' => $runningBalance,
                'projected_balance' => $projectedBalance
            ],
            'cash_flow_data' => $cashFlowData,
            'filters' => [
                'start_date' => $request->start_date,
                'end_date' => $request->end_date
            ]
        ];

        if ($request->format === 'pdf') {
            $pdf = Pdf::loadView('reports.cash-flow-pdf', $data);
            return $pdf->download('flujo-caja-' . $startDate->format('Y-m-d') . '-' . $endDate->format('Y-m-d') . '.pdf');
        }

        return Inertia::render('Reports/CashFlow', $data);
    }

    public function profitability(Request $request)
    {
        // $this->checkPermission('reports.view');
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'group_by' => 'nullable|in:product,category,customer',
            'format' => 'nullable|in:view,pdf,excel'
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();
        $groupBy = $request->group_by ?? 'product';
        $tenantId = auth()->user()->tenant_id;

        // Análisis por producto
        $productProfitability = DB::table('tax_document_items')
            ->join('tax_documents', 'tax_documents.id', '=', 'tax_document_items.tax_document_id')
            ->join('products', 'products.id', '=', 'tax_document_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('tax_documents.tenant_id', $tenantId)
            ->whereIn('tax_documents.type', ['invoice', 'receipt'])
            ->whereBetween('tax_documents.issue_date', [$startDate, $endDate])
            ->whereNotIn('tax_documents.status', ['cancelled', 'draft'])
            ->select([
                'products.id as product_id',
                'products.name as product_name',
                'products.sku',
                'products.cost',
                'categories.name as category_name',
                DB::raw('SUM(tax_document_items.quantity) as total_quantity'),
                DB::raw('SUM(tax_document_items.subtotal) as total_revenue'),
                DB::raw('SUM(tax_document_items.quantity * products.cost) as total_cost'),
                DB::raw('SUM(tax_document_items.subtotal) - SUM(tax_document_items.quantity * products.cost) as gross_profit'),
                DB::raw('ROUND(((SUM(tax_document_items.subtotal) - SUM(tax_document_items.quantity * products.cost)) / SUM(tax_document_items.subtotal)) * 100, 2) as margin_percentage')
            ])
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.cost', 'categories.name')
            ->orderBy('gross_profit', 'desc')
            ->get();

        // Análisis por categoría
        $categoryProfitability = $productProfitability->groupBy('category_name')->map(function ($products, $category) {
            return [
                'category' => $category ?: 'Sin categoría',
                'total_revenue' => $products->sum('total_revenue'),
                'total_cost' => $products->sum('total_cost'),
                'gross_profit' => $products->sum('gross_profit'),
                'margin_percentage' => $products->sum('total_revenue') > 0 
                    ? round(($products->sum('gross_profit') / $products->sum('total_revenue')) * 100, 2) 
                    : 0,
                'product_count' => $products->count()
            ];
        })->sortByDesc('gross_profit')->values();

        // Análisis por cliente
        $customerProfitability = TaxDocument::where('tenant_id', $tenantId)
            ->whereIn('type', ['invoice', 'receipt'])
            ->whereBetween('issue_date', [$startDate, $endDate])
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->join('customers', 'customers.id', '=', 'tax_documents.customer_id')
            ->select([
                'customers.id',
                'customers.name',
                'customers.rut',
                DB::raw('COUNT(tax_documents.id) as total_orders'),
                DB::raw('SUM(tax_documents.subtotal) as total_revenue'),
                DB::raw('AVG(tax_documents.subtotal) as avg_order_value')
            ])
            ->groupBy('customers.id', 'customers.name', 'customers.rut')
            ->orderBy('total_revenue', 'desc')
            ->limit(20)
            ->get();

        // Resumen general
        $totalRevenue = $productProfitability->sum('total_revenue');
        $totalCost = $productProfitability->sum('total_cost');
        $totalProfit = $totalRevenue - $totalCost;
        $overallMargin = $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 2) : 0;

        $data = [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ],
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_cost' => $totalCost,
                'total_profit' => $totalProfit,
                'overall_margin' => $overallMargin
            ],
            'product_profitability' => $productProfitability,
            'category_profitability' => $categoryProfitability,
            'customer_profitability' => $customerProfitability,
            'filters' => [
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'group_by' => $groupBy
            ]
        ];

        if ($request->format === 'pdf') {
            $pdf = Pdf::loadView('reports.profitability-pdf', $data);
            return $pdf->download('analisis-rentabilidad-' . $startDate->format('Y-m-d') . '-' . $endDate->format('Y-m-d') . '.pdf');
        }

        return Inertia::render('Reports/Profitability', $data);
    }

    public function expensesAndPurchases(Request $request)
    {
        // $this->checkPermission('reports.view');
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'category' => 'nullable|string',
            'format' => 'nullable|in:view,pdf,excel'
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();
        $tenantId = auth()->user()->tenant_id;

        // Gastos operacionales
        $expenses = \App\Models\Expense::where('tenant_id', $tenantId)
            ->whereBetween('issue_date', [$startDate, $endDate])
            ->when($request->category, function ($query, $category) {
                return $query->where('category', $category);
            })
            ->select([
                'id',
                'description',
                'category',
                'issue_date',
                'total_amount',
                'status',
                'payment_method'
            ])
            ->orderBy('issue_date', 'desc')
            ->get();

        // Compras de productos/inventario
        $purchases = \App\Models\PurchaseOrder::where('tenant_id', $tenantId)
            ->whereBetween('order_date', [$startDate, $endDate])
            ->with(['supplier', 'items.product'])
            ->select([
                'id',
                'order_number',
                'supplier_id',
                'order_date',
                'total',
                'status'
            ])
            ->orderBy('order_date', 'desc')
            ->get();

        // Gastos por categoría
        $expensesByCategory = $expenses->groupBy('category')->map(function ($categoryExpenses, $category) {
            return [
                'category' => $category ?: 'Sin categoría',
                'count' => $categoryExpenses->count(),
                'total_amount' => $categoryExpenses->sum('total_amount'),
                'avg_amount' => $categoryExpenses->avg('total_amount')
            ];
        })->sortByDesc('total_amount')->values();

        // Gastos por mes
        $expensesByMonth = $expenses->groupBy(function ($expense) {
            return Carbon::parse($expense->issue_date)->format('Y-m');
        })->map(function ($monthExpenses, $month) {
            return [
                'month' => $month,
                'count' => $monthExpenses->count(),
                'total_amount' => $monthExpenses->sum('total_amount')
            ];
        })->sortBy('month')->values();

        // Compras por proveedor
        $purchasesBySupplier = $purchases->groupBy('supplier.name')->map(function ($supplierPurchases, $supplier) {
            return [
                'supplier' => $supplier ?: 'Sin proveedor',
                'count' => $supplierPurchases->count(),
                'total_amount' => $supplierPurchases->sum('total'),
                'avg_amount' => $supplierPurchases->avg('total')
            ];
        })->sortByDesc('total_amount')->values();

        // Resumen general
        $totalExpenses = $expenses->sum('total_amount');
        $totalPurchases = $purchases->sum('total');
        $totalSpending = $totalExpenses + $totalPurchases;

        // Top 10 gastos más altos
        $topExpenses = $expenses->sortByDesc('total_amount')->take(10);

        $data = [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ],
            'summary' => [
                'total_expenses' => $totalExpenses,
                'total_purchases' => $totalPurchases,
                'total_spending' => $totalSpending,
                'expenses_count' => $expenses->count(),
                'purchases_count' => $purchases->count()
            ],
            'expenses' => $expenses,
            'purchases' => $purchases,
            'expenses_by_category' => $expensesByCategory,
            'expenses_by_month' => $expensesByMonth,
            'purchases_by_supplier' => $purchasesBySupplier,
            'top_expenses' => $topExpenses,
            'filters' => [
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'category' => $request->category
            ]
        ];

        if ($request->format === 'pdf') {
            $pdf = Pdf::loadView('reports.expenses-purchases-pdf', $data);
            return $pdf->download('gastos-compras-' . $startDate->format('Y-m-d') . '-' . $endDate->format('Y-m-d') . '.pdf');
        }

        return Inertia::render('Reports/ExpensesAndPurchases', $data);
    }

}