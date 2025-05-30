<?php

namespace App\Services;

use App\Models\TaxDocument;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\Customer;
use App\Models\Product;
use App\Models\BankAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class FinancialAnalysisService
{
    /**
     * Get comprehensive financial dashboard data
     */
    public function getFinancialDashboard(Carbon $startDate, Carbon $endDate): array
    {
        $tenant = app('currentTenant');
        $cacheKey = "financial_dashboard:{$tenant->id}:" . $startDate->format('Y-m-d') . ":" . $endDate->format('Y-m-d');
        
        return Cache::remember($cacheKey, 300, function () use ($startDate, $endDate) {
            return [
                'revenue_analysis' => $this->getRevenueAnalysis($startDate, $endDate),
                'profitability' => $this->getProfitabilityAnalysis($startDate, $endDate),
                'cash_flow' => $this->getCashFlowAnalysis($startDate, $endDate),
                'kpis' => $this->getKPIs($startDate, $endDate),
                'trends' => $this->getTrendAnalysis($startDate, $endDate),
                'forecasting' => $this->getForecasting($startDate, $endDate),
                'customer_analysis' => $this->getCustomerAnalysis($startDate, $endDate),
                'product_analysis' => $this->getProductAnalysis($startDate, $endDate)
            ];
        });
    }

    /**
     * Revenue analysis with segmentation
     */
    public function getRevenueAnalysis(Carbon $startDate, Carbon $endDate): array
    {
        $tenant = app('currentTenant');
        
        // Ingresos totales
        $totalRevenue = TaxDocument::where('tenant_id', $tenant->id)
            ->whereIn('type', ['invoice', 'exempt_invoice'])
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('status', ['issued', 'paid'])
            ->sum('total_amount');
        
        // Ingresos del período anterior para comparación
        $previousPeriod = $startDate->copy()->subDays($endDate->diffInDays($startDate));
        $previousRevenue = TaxDocument::where('tenant_id', $tenant->id)
            ->whereIn('type', ['invoice', 'exempt_invoice'])
            ->whereBetween('date', [$previousPeriod, $startDate->copy()->subDay()])
            ->whereIn('status', ['issued', 'paid'])
            ->sum('total_amount');
        
        // Crecimiento
        $growthRate = $previousRevenue > 0 ? (($totalRevenue - $previousRevenue) / $previousRevenue) * 100 : 0;
        
        // Ingresos por día
        $dailyRevenue = TaxDocument::where('tenant_id', $tenant->id)
            ->whereIn('type', ['invoice', 'exempt_invoice'])
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('status', ['issued', 'paid'])
            ->selectRaw('DATE(date) as date, SUM(total_amount) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');
        
        // Ingresos por tipo de cliente
        $revenueByCustomerType = TaxDocument::where('tax_documents.tenant_id', $tenant->id)
            ->join('customers', 'tax_documents.customer_id', '=', 'customers.id')
            ->whereIn('tax_documents.type', ['invoice', 'exempt_invoice'])
            ->whereBetween('tax_documents.date', [$startDate, $endDate])
            ->whereIn('tax_documents.status', ['issued', 'paid'])
            ->selectRaw('customers.customer_type, SUM(tax_documents.total_amount) as revenue')
            ->groupBy('customers.customer_type')
            ->get();
        
        // Ingresos por categoría de producto
        $revenueByCategory = DB::table('tax_documents')
            ->join('tax_document_items', 'tax_documents.id', '=', 'tax_document_items.tax_document_id')
            ->join('products', 'tax_document_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('tax_documents.tenant_id', $tenant->id)
            ->whereIn('tax_documents.type', ['invoice', 'exempt_invoice'])
            ->whereBetween('tax_documents.date', [$startDate, $endDate])
            ->whereIn('tax_documents.status', ['issued', 'paid'])
            ->selectRaw('COALESCE(categories.name, "Sin categoría") as category, SUM(tax_document_items.line_total) as revenue')
            ->groupBy('categories.name')
            ->orderByDesc('revenue')
            ->get();
        
        return [
            'total_revenue' => $totalRevenue,
            'previous_revenue' => $previousRevenue,
            'growth_rate' => round($growthRate, 2),
            'average_daily' => $totalRevenue / max(1, $endDate->diffInDays($startDate) + 1),
            'daily_revenue' => $dailyRevenue,
            'by_customer_type' => $revenueByCustomerType,
            'by_category' => $revenueByCategory,
            'monthly_recurring' => $this->getMonthlyRecurringRevenue($startDate, $endDate)
        ];
    }

    /**
     * Profitability analysis
     */
    public function getProfitabilityAnalysis(Carbon $startDate, Carbon $endDate): array
    {
        $tenant = app('currentTenant');
        
        // Ingresos
        $revenue = TaxDocument::where('tenant_id', $tenant->id)
            ->whereIn('type', ['invoice', 'exempt_invoice'])
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('status', ['issued', 'paid'])
            ->sum('total_amount');
        
        // Costos directos (COGS)
        $directCosts = DB::table('tax_document_items')
            ->join('tax_documents', 'tax_document_items.tax_document_id', '=', 'tax_documents.id')
            ->join('products', 'tax_document_items.product_id', '=', 'products.id')
            ->where('tax_documents.tenant_id', $tenant->id)
            ->whereIn('tax_documents.type', ['invoice', 'exempt_invoice'])
            ->whereBetween('tax_documents.date', [$startDate, $endDate])
            ->whereIn('tax_documents.status', ['issued', 'paid'])
            ->selectRaw('SUM(tax_document_items.quantity * products.unit_cost) as total_cost')
            ->value('total_cost') ?? 0;
        
        // Gastos operacionales
        $operatingExpenses = Expense::where('tenant_id', $tenant->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('status', ['approved', 'paid'])
            ->sum('total_amount');
        
        // Cálculos de márgenes
        $grossProfit = $revenue - $directCosts;
        $grossMargin = $revenue > 0 ? ($grossProfit / $revenue) * 100 : 0;
        
        $operatingProfit = $grossProfit - $operatingExpenses;
        $operatingMargin = $revenue > 0 ? ($operatingProfit / $revenue) * 100 : 0;
        
        // Margen por producto
        $productProfitability = DB::table('tax_document_items')
            ->join('tax_documents', 'tax_document_items.tax_document_id', '=', 'tax_documents.id')
            ->join('products', 'tax_document_items.product_id', '=', 'products.id')
            ->where('tax_documents.tenant_id', $tenant->id)
            ->whereIn('tax_documents.type', ['invoice', 'exempt_invoice'])
            ->whereBetween('tax_documents.date', [$startDate, $endDate])
            ->whereIn('tax_documents.status', ['issued', 'paid'])
            ->selectRaw('
                products.id,
                products.name,
                SUM(tax_document_items.line_total) as revenue,
                SUM(tax_document_items.quantity * products.unit_cost) as cost,
                SUM(tax_document_items.line_total) - SUM(tax_document_items.quantity * products.unit_cost) as profit,
                CASE 
                    WHEN SUM(tax_document_items.line_total) > 0 
                    THEN ((SUM(tax_document_items.line_total) - SUM(tax_document_items.quantity * products.unit_cost)) / SUM(tax_document_items.line_total)) * 100
                    ELSE 0 
                END as margin_percentage
            ')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('profit')
            ->limit(20)
            ->get();
        
        return [
            'revenue' => $revenue,
            'direct_costs' => $directCosts,
            'operating_expenses' => $operatingExpenses,
            'gross_profit' => $grossProfit,
            'gross_margin' => round($grossMargin, 2),
            'operating_profit' => $operatingProfit,
            'operating_margin' => round($operatingMargin, 2),
            'product_profitability' => $productProfitability,
            'expense_breakdown' => $this->getExpenseBreakdown($startDate, $endDate)
        ];
    }

    /**
     * Cash flow analysis
     */
    public function getCashFlowAnalysis(Carbon $startDate, Carbon $endDate): array
    {
        $tenant = app('currentTenant');
        
        // Flujo de entrada (pagos recibidos)
        $cashInflows = Payment::where('tenant_id', $tenant->id)
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->where('status', 'completed')
            ->sum('amount');
        
        // Flujo de salida (gastos pagados)
        $cashOutflows = Expense::where('tenant_id', $tenant->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('payment_status', 'paid')
            ->sum('total_amount');
        
        // Flujo neto
        $netCashFlow = $cashInflows - $cashOutflows;
        
        // Flujo diario
        $dailyCashFlow = collect();
        $currentDate = $startDate->copy();
        
        while ($currentDate->lte($endDate)) {
            $dayInflows = Payment::where('tenant_id', $tenant->id)
                ->whereDate('payment_date', $currentDate)
                ->where('status', 'completed')
                ->sum('amount');
            
            $dayOutflows = Expense::where('tenant_id', $tenant->id)
                ->whereDate('date', $currentDate)
                ->where('payment_status', 'paid')
                ->sum('total_amount');
            
            $dailyCashFlow->push([
                'date' => $currentDate->format('Y-m-d'),
                'inflows' => $dayInflows,
                'outflows' => $dayOutflows,
                'net' => $dayInflows - $dayOutflows
            ]);
            
            $currentDate->addDay();
        }
        
        // Cuentas por cobrar
        $accountsReceivable = TaxDocument::where('tenant_id', $tenant->id)
            ->whereIn('type', ['invoice', 'exempt_invoice'])
            ->where('payment_status', 'pending')
            ->where('status', 'issued')
            ->sum('total_amount');
        
        // Cuentas por pagar
        $accountsPayable = Expense::where('tenant_id', $tenant->id)
            ->where('payment_status', 'pending')
            ->whereIn('status', ['approved'])
            ->sum('total_amount');
        
        // Proyección de flujo de caja
        $cashFlowProjection = $this->getCashFlowProjection();
        
        return [
            'cash_inflows' => $cashInflows,
            'cash_outflows' => $cashOutflows,
            'net_cash_flow' => $netCashFlow,
            'daily_cash_flow' => $dailyCashFlow,
            'accounts_receivable' => $accountsReceivable,
            'accounts_payable' => $accountsPayable,
            'working_capital' => $accountsReceivable - $accountsPayable,
            'projection' => $cashFlowProjection,
            'cash_conversion_cycle' => $this->getCashConversionCycle($startDate, $endDate)
        ];
    }

    /**
     * Key Performance Indicators
     */
    public function getKPIs(Carbon $startDate, Carbon $endDate): array
    {
        $tenant = app('currentTenant');
        
        // Revenue KPIs
        $totalRevenue = TaxDocument::where('tenant_id', $tenant->id)
            ->whereIn('type', ['invoice', 'exempt_invoice'])
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('status', ['issued', 'paid'])
            ->sum('total_amount');
        
        $invoiceCount = TaxDocument::where('tenant_id', $tenant->id)
            ->whereIn('type', ['invoice', 'exempt_invoice'])
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('status', ['issued', 'paid'])
            ->count();
        
        // Customer KPIs
        $activeCustomers = Customer::where('tenant_id', $tenant->id)
            ->whereHas('taxDocuments', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate])
                      ->whereIn('status', ['issued', 'paid']);
            })
            ->count();
        
        $newCustomers = Customer::where('tenant_id', $tenant->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
        
        // Payment KPIs
        $averagePaymentTime = DB::table('payments')
            ->join('tax_documents', 'payments.id', '=', DB::raw('JSON_EXTRACT(tax_documents.payment_data, "$.payment_id")'))
            ->where('payments.tenant_id', $tenant->id)
            ->whereBetween('payments.payment_date', [$startDate, $endDate])
            ->where('payments.status', 'completed')
            ->selectRaw('AVG(DATEDIFF(payments.payment_date, tax_documents.date)) as avg_days')
            ->value('avg_days') ?? 0;
        
        // Efficiency KPIs
        $inventoryTurnover = $this->getInventoryTurnover($startDate, $endDate);
        $receivablesTurnover = $this->getReceivablesTurnover($startDate, $endDate);
        
        return [
            'total_revenue' => $totalRevenue,
            'average_order_value' => $invoiceCount > 0 ? $totalRevenue / $invoiceCount : 0,
            'revenue_per_customer' => $activeCustomers > 0 ? $totalRevenue / $activeCustomers : 0,
            'active_customers' => $activeCustomers,
            'new_customers' => $newCustomers,
            'customer_retention_rate' => $this->getCustomerRetentionRate($startDate, $endDate),
            'average_payment_time' => round($averagePaymentTime, 1),
            'inventory_turnover' => $inventoryTurnover,
            'receivables_turnover' => $receivablesTurnover,
            'current_ratio' => $this->getCurrentRatio(),
            'quick_ratio' => $this->getQuickRatio(),
            'debt_to_equity' => $this->getDebtToEquityRatio()
        ];
    }

    /**
     * Trend analysis
     */
    public function getTrendAnalysis(Carbon $startDate, Carbon $endDate): array
    {
        $tenant = app('currentTenant');
        
        // Análisis de tendencias mensuales
        $monthlyTrends = collect();
        $currentMonth = $startDate->copy()->startOfMonth();
        
        while ($currentMonth->lte($endDate)) {
            $monthEnd = $currentMonth->copy()->endOfMonth();
            if ($monthEnd->gt($endDate)) {
                $monthEnd = $endDate;
            }
            
            $revenue = TaxDocument::where('tenant_id', $tenant->id)
                ->whereIn('type', ['invoice', 'exempt_invoice'])
                ->whereBetween('date', [$currentMonth, $monthEnd])
                ->whereIn('status', ['issued', 'paid'])
                ->sum('total_amount');
            
            $expenses = Expense::where('tenant_id', $tenant->id)
                ->whereBetween('date', [$currentMonth, $monthEnd])
                ->whereIn('status', ['approved', 'paid'])
                ->sum('total_amount');
            
            $monthlyTrends->push([
                'month' => $currentMonth->format('Y-m'),
                'revenue' => $revenue,
                'expenses' => $expenses,
                'profit' => $revenue - $expenses
            ]);
            
            $currentMonth->addMonth();
        }
        
        // Análisis de estacionalidad
        $seasonalityData = $this->getSeasonalityAnalysis();
        
        // Predicción de tendencias
        $trendPrediction = $this->predictTrends($monthlyTrends);
        
        return [
            'monthly_trends' => $monthlyTrends,
            'seasonality' => $seasonalityData,
            'prediction' => $trendPrediction,
            'growth_metrics' => $this->getGrowthMetrics($monthlyTrends)
        ];
    }

    /**
     * Financial forecasting
     */
    public function getForecasting(Carbon $startDate, Carbon $endDate): array
    {
        // Obtener datos históricos para la predicción
        $historicalData = $this->getHistoricalData($startDate, $endDate);
        
        // Proyección de ingresos usando regresión lineal simple
        $revenueProjection = $this->projectRevenue($historicalData);
        
        // Proyección de gastos
        $expenseProjection = $this->projectExpenses($historicalData);
        
        // Análisis de escenarios
        $scenarios = $this->getScenarioAnalysis($revenueProjection, $expenseProjection);
        
        return [
            'revenue_projection' => $revenueProjection,
            'expense_projection' => $expenseProjection,
            'profit_projection' => $this->calculateProfitProjection($revenueProjection, $expenseProjection),
            'scenarios' => $scenarios,
            'assumptions' => $this->getProjectionAssumptions(),
            'confidence_intervals' => $this->getConfidenceIntervals($historicalData)
        ];
    }

    /**
     * Customer analysis
     */
    public function getCustomerAnalysis(Carbon $startDate, Carbon $endDate): array
    {
        $tenant = app('currentTenant');
        
        // Análisis RFM (Recency, Frequency, Monetary)
        $customerRFM = DB::table('customers')
            ->leftJoin('tax_documents', 'customers.id', '=', 'tax_documents.customer_id')
            ->where('customers.tenant_id', $tenant->id)
            ->select([
                'customers.id',
                'customers.name',
                DB::raw('MAX(tax_documents.date) as last_purchase'),
                DB::raw('COUNT(tax_documents.id) as frequency'),
                DB::raw('COALESCE(SUM(tax_documents.total_amount), 0) as monetary')
            ])
            ->groupBy('customers.id', 'customers.name')
            ->having('frequency', '>', 0)
            ->get();
        
        // Segmentación de clientes
        $customerSegments = $this->segmentCustomers($customerRFM);
        
        // Top customers
        $topCustomers = $customerRFM->sortByDesc('monetary')->take(10);
        
        // Customer lifetime value
        $customerLTV = $this->calculateCustomerLTV($startDate, $endDate);
        
        return [
            'rfm_analysis' => $customerRFM,
            'segments' => $customerSegments,
            'top_customers' => $topCustomers,
            'customer_ltv' => $customerLTV,
            'churn_analysis' => $this->getChurnAnalysis($startDate, $endDate)
        ];
    }

    /**
     * Product analysis
     */
    public function getProductAnalysis(Carbon $startDate, Carbon $endDate): array
    {
        $tenant = app('currentTenant');
        
        // Análisis ABC de productos
        $productSales = DB::table('tax_document_items')
            ->join('tax_documents', 'tax_document_items.tax_document_id', '=', 'tax_documents.id')
            ->join('products', 'tax_document_items.product_id', '=', 'products.id')
            ->where('tax_documents.tenant_id', $tenant->id)
            ->whereBetween('tax_documents.date', [$startDate, $endDate])
            ->whereIn('tax_documents.status', ['issued', 'paid'])
            ->select([
                'products.id',
                'products.name',
                'products.sku',
                DB::raw('SUM(tax_document_items.quantity) as total_quantity'),
                DB::raw('SUM(tax_document_items.line_total) as total_revenue'),
                DB::raw('AVG(tax_document_items.unit_price) as avg_price')
            ])
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('total_revenue')
            ->get();
        
        // Clasificación ABC
        $abcClassification = $this->classifyProductsABC($productSales);
        
        // Productos de bajo rendimiento
        $underperformingProducts = $this->getUnderperformingProducts($startDate, $endDate);
        
        return [
            'product_sales' => $productSales,
            'abc_classification' => $abcClassification,
            'underperforming' => $underperformingProducts,
            'inventory_analysis' => $this->getInventoryAnalysis(),
            'price_optimization' => $this->getPriceOptimizationSuggestions($productSales)
        ];
    }

    // Helper methods (implementaciones simplificadas)
    
    protected function getMonthlyRecurringRevenue(Carbon $startDate, Carbon $endDate): float
    {
        // Implementar lógica para MRR basada en clientes recurrentes
        return 0;
    }
    
    protected function getExpenseBreakdown(Carbon $startDate, Carbon $endDate): array
    {
        $tenant = app('currentTenant');
        
        return Expense::where('tenant_id', $tenant->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('status', ['approved', 'paid'])
            ->selectRaw('category, SUM(total_amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get()
            ->toArray();
    }
    
    protected function getCashFlowProjection(): array
    {
        // Implementar proyección de flujo de caja basada en datos históricos
        return [];
    }
    
    protected function getCashConversionCycle(Carbon $startDate, Carbon $endDate): float
    {
        // Calcular el ciclo de conversión de efectivo
        return 0;
    }
    
    protected function getInventoryTurnover(Carbon $startDate, Carbon $endDate): float
    {
        // Calcular rotación de inventario
        return 0;
    }
    
    protected function getReceivablesTurnover(Carbon $startDate, Carbon $endDate): float
    {
        // Calcular rotación de cuentas por cobrar
        return 0;
    }
    
    protected function getCurrentRatio(): float
    {
        // Calcular ratio corriente
        return 0;
    }
    
    protected function getQuickRatio(): float
    {
        // Calcular ratio rápido
        return 0;
    }
    
    protected function getDebtToEquityRatio(): float
    {
        // Calcular ratio deuda/patrimonio
        return 0;
    }
    
    protected function getCustomerRetentionRate(Carbon $startDate, Carbon $endDate): float
    {
        // Calcular tasa de retención de clientes
        return 0;
    }
    
    protected function getSeasonalityAnalysis(): array
    {
        // Analizar patrones estacionales
        return [];
    }
    
    protected function predictTrends($monthlyTrends): array
    {
        // Predecir tendencias futuras
        return [];
    }
    
    protected function getGrowthMetrics($monthlyTrends): array
    {
        // Calcular métricas de crecimiento
        return [];
    }
    
    protected function getHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        // Obtener datos históricos para forecasting
        return [];
    }
    
    protected function projectRevenue($historicalData): array
    {
        // Proyectar ingresos futuros
        return [];
    }
    
    protected function projectExpenses($historicalData): array
    {
        // Proyectar gastos futuros
        return [];
    }
    
    protected function calculateProfitProjection($revenueProjection, $expenseProjection): array
    {
        // Calcular proyección de utilidades
        return [];
    }
    
    protected function getScenarioAnalysis($revenueProjection, $expenseProjection): array
    {
        // Análisis de escenarios (optimista, pesimista, realista)
        return [];
    }
    
    protected function getProjectionAssumptions(): array
    {
        // Supuestos utilizados en las proyecciones
        return [];
    }
    
    protected function getConfidenceIntervals($historicalData): array
    {
        // Intervalos de confianza para las proyecciones
        return [];
    }
    
    protected function segmentCustomers($customerRFM): array
    {
        // Segmentar clientes basado en análisis RFM
        return [];
    }
    
    protected function calculateCustomerLTV(Carbon $startDate, Carbon $endDate): array
    {
        // Calcular valor de vida del cliente
        return [];
    }
    
    protected function getChurnAnalysis(Carbon $startDate, Carbon $endDate): array
    {
        // Analizar abandono de clientes
        return [];
    }
    
    protected function classifyProductsABC($productSales): array
    {
        // Clasificar productos usando análisis ABC
        return [];
    }
    
    protected function getUnderperformingProducts(Carbon $startDate, Carbon $endDate): array
    {
        // Identificar productos de bajo rendimiento
        return [];
    }
    
    protected function getInventoryAnalysis(): array
    {
        // Análisis detallado de inventario
        return [];
    }
    
    protected function getPriceOptimizationSuggestions($productSales): array
    {
        // Sugerencias para optimización de precios
        return [];
    }
}