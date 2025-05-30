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
        $this->checkPermission('reports.view');
        return Inertia::render('Reports/Index');
    }

    public function sales(Request $request)
    {
        $this->checkPermission('reports.view');
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
            ->whereIn('document_type', ['invoice', 'receipt'])
            ->whereBetween('issue_date', [$startDate, $endDate])
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->select(
                DB::raw($this->getDateGrouping($groupBy) . ' as period'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(net_amount) as net_total'),
                DB::raw('SUM(tax_amount) as tax_total'),
                DB::raw('SUM(total_amount) as total')
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
            ->whereIn('tax_documents.document_type', ['invoice', 'receipt'])
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
            ->whereIn('document_type', ['invoice', 'receipt'])
            ->whereBetween('issue_date', [$startDate, $endDate])
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->join('customers', 'customers.id', '=', 'tax_documents.customer_id')
            ->select(
                'customers.id',
                'customers.name',
                'customers.rut',
                DB::raw('COUNT(tax_documents.id) as document_count'),
                DB::raw('SUM(tax_documents.total_amount) as total_amount')
            )
            ->groupBy('customers.id', 'customers.name', 'customers.rut')
            ->orderBy('total_amount', 'desc')
            ->limit(10)
            ->get();
        });

        // Resumen general con caché
        $summary = Cache::remember($cacheKey . ':summary', $cacheTTL, function() use ($tenantId, $startDate, $endDate) {
            return TaxDocument::where('tenant_id', $tenantId)
            ->whereIn('document_type', ['invoice', 'receipt'])
            ->whereBetween('issue_date', [$startDate, $endDate])
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->select([
                DB::raw('COUNT(*) as total_documents'),
                DB::raw('SUM(net_amount) as total_net'),
                DB::raw('SUM(tax_amount) as total_tax'),
                DB::raw('SUM(total_amount) as total_amount'),
                DB::raw('AVG(total_amount) as average_sale')
            ])
            ->first();
        });

        // Comparación con período anterior con caché
        $previousStartDate = $startDate->copy()->subDays($endDate->diffInDays($startDate) + 1);
        $previousEndDate = $startDate->copy()->subDay();
        
        $previousCacheKey = "reports:sales:{$tenantId}:" . md5($previousStartDate . $previousEndDate);

        $previousSummary = Cache::remember($previousCacheKey . ':previous', $cacheTTL, function() use ($tenantId, $previousStartDate, $previousEndDate) {
            return TaxDocument::where('tenant_id', $tenantId)
            ->whereIn('document_type', ['invoice', 'receipt'])
            ->whereBetween('issue_date', [$previousStartDate, $previousEndDate])
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->select([
                DB::raw('SUM(total_amount) as total_amount')
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
        $this->checkPermission('reports.view');
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
            ->whereIn('document_type', ['invoice', 'receipt', 'debit_note'])
            ->whereBetween('issue_date', [$startDate, $endDate])
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->select([
                'document_type',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(net_amount) as net_amount'),
                DB::raw('SUM(tax_amount) as tax_amount'),
                DB::raw('SUM(total_amount) as total_amount')
            ])
            ->groupBy('document_type')
            ->get();

        // Notas de crédito (reducen el débito fiscal)
        $creditNotes = TaxDocument::where('tenant_id', $tenantId)
            ->where('document_type', 'credit_note')
            ->whereBetween('issue_date', [$startDate, $endDate])
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->select([
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(net_amount) as net_amount'),
                DB::raw('SUM(tax_amount) as tax_amount'),
                DB::raw('SUM(total_amount) as total_amount')
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
            ->whereIn('document_type', ['invoice', 'receipt', 'debit_note', 'credit_note'])
            ->whereBetween('issue_date', [$startDate, $endDate])
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->select([
                DB::raw("strftime('%Y', issue_date) as year"),
                DB::raw("strftime('%m', issue_date) as month"),
                'document_type',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(net_amount) as net_amount'),
                DB::raw('SUM(tax_amount) as tax_amount'),
                DB::raw('SUM(total_amount) as total_amount')
            ])
            ->groupBy('year', 'month', 'document_type')
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
        $this->checkPermission('reports.view');
        $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'status' => 'nullable|in:all,low_stock,out_of_stock',
            'format' => 'nullable|in:view,pdf,excel'
        ]);

        $tenantId = auth()->user()->tenant_id;
        $query = Product::where('tenant_id', $tenantId)
            ->where('type', 'product');

        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->status === 'low_stock') {
            $query->whereRaw('stock <= minimum_stock AND stock > 0');
        } elseif ($request->status === 'out_of_stock') {
            $query->where('stock', 0);
        }

        // Primero obtenemos estadísticas generales sin paginar
        $statsQuery = clone $query;
        $allProducts = $statsQuery->get();
        
        // Estadísticas generales
        $statistics = [
            'total_products' => $allProducts->count(),
            'total_value' => $allProducts->sum(function ($product) {
                return $product->stock * $product->cost;
            }),
            'low_stock_count' => $allProducts->filter(function ($product) {
                return $product->stock <= $product->minimum_stock && $product->stock > 0;
            })->count(),
            'out_of_stock_count' => $allProducts->where('stock', 0)->count()
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
                    'total_stock' => $products->sum('stock'),
                    'total_value' => $products->sum(function ($product) {
                        return $product->stock * $product->cost;
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
        $this->checkPermission('reports.view');
        $request->validate([
            'status' => 'nullable|in:all,with_balance,overdue',
            'format' => 'nullable|in:view,pdf,excel'
        ]);

        $tenantId = auth()->user()->tenant_id;
        $query = Customer::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->withSum(['taxDocuments as total_debt' => function ($query) {
                $query->whereIn('document_type', ['invoice', 'debit_note'])
                    ->whereNotIn('status', ['cancelled', 'draft']);
            }], 'balance')
            ->withSum(['taxDocuments as total_credit' => function ($query) {
                $query->where('document_type', 'credit_note')
                    ->whereNotIn('status', ['cancelled', 'draft']);
            }], 'total_amount');

        $customers = $query->get()->map(function ($customer) {
            $customer->balance = ($customer->total_debt ?? 0) - ($customer->total_credit ?? 0);
            
            // Calcular documentos vencidos
            $overdueAmount = TaxDocument::where('customer_id', $customer->id)
                ->whereIn('document_type', ['invoice', 'debit_note'])
                ->whereNotIn('status', ['cancelled', 'draft', 'paid'])
                ->where('due_date', '<', now())
                ->sum('balance');
            
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
            ->whereIn('document_type', ['invoice', 'debit_note'])
            ->whereNotIn('status', ['cancelled', 'draft', 'paid'])
            ->where('balance', '>', 0)
            ->select([
                DB::raw("SUM(CASE WHEN due_date >= date('$today') THEN balance ELSE 0 END) as current"),
                DB::raw("SUM(CASE WHEN due_date < date('$today') AND due_date >= date('$today', '-30 days') THEN balance ELSE 0 END) as days_30"),
                DB::raw("SUM(CASE WHEN due_date < date('$today', '-30 days') AND due_date >= date('$today', '-60 days') THEN balance ELSE 0 END) as days_60"),
                DB::raw("SUM(CASE WHEN due_date < date('$today', '-60 days') AND due_date >= date('$today', '-90 days') THEN balance ELSE 0 END) as days_90"),
                DB::raw("SUM(CASE WHEN due_date < date('$today', '-90 days') THEN balance ELSE 0 END) as over_90")
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
}