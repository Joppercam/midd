<?php

namespace App\Http\Controllers;

use App\Services\FinancialAnalysisService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Inertia\Inertia;

class FinancialAnalysisController extends Controller
{
    protected FinancialAnalysisService $analysisService;

    public function __construct(FinancialAnalysisService $analysisService)
    {
        $this->middleware(['auth', 'tenant']);
        $this->analysisService = $analysisService;
    }

    /**
     * Display financial analysis dashboard
     */
    public function index(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'period' => 'nullable|in:week,month,quarter,year,custom'
        ]);

        $period = $request->get('period', 'month');
        [$startDate, $endDate] = $this->getPeriodDates($period, $request);

        $analysis = $this->analysisService->getFinancialDashboard($startDate, $endDate);

        return Inertia::render('FinancialAnalysis/Dashboard', [
            'analysis' => $analysis,
            'period' => $period,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
            'availablePeriods' => $this->getAvailablePeriods()
        ]);
    }

    /**
     * Get revenue analysis
     */
    public function revenue(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        $revenueAnalysis = $this->analysisService->getRevenueAnalysis($startDate, $endDate);

        return Inertia::render('FinancialAnalysis/Revenue', [
            'analysis' => $revenueAnalysis,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d')
        ]);
    }

    /**
     * Get profitability analysis
     */
    public function profitability(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        $profitabilityAnalysis = $this->analysisService->getProfitabilityAnalysis($startDate, $endDate);

        return Inertia::render('FinancialAnalysis/Profitability', [
            'analysis' => $profitabilityAnalysis,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d')
        ]);
    }

    /**
     * Get cash flow analysis
     */
    public function cashFlow(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        $cashFlowAnalysis = $this->analysisService->getCashFlowAnalysis($startDate, $endDate);

        return Inertia::render('FinancialAnalysis/CashFlow', [
            'analysis' => $cashFlowAnalysis,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d')
        ]);
    }

    /**
     * Get KPIs dashboard
     */
    public function kpis(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'compare_period' => 'nullable|boolean'
        ]);

        $startDate = $request->start_date ? Carbon::parse($request->start_date) : now()->startOfMonth();
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : now()->endOfMonth();

        $kpis = $this->analysisService->getKPIs($startDate, $endDate);

        // Comparar con período anterior si se solicita
        $comparison = null;
        if ($request->boolean('compare_period')) {
            $periodLength = $endDate->diffInDays($startDate);
            $previousStart = $startDate->copy()->subDays($periodLength + 1);
            $previousEnd = $startDate->copy()->subDay();
            
            $comparison = $this->analysisService->getKPIs($previousStart, $previousEnd);
        }

        return Inertia::render('FinancialAnalysis/KPIs', [
            'kpis' => $kpis,
            'comparison' => $comparison,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d')
        ]);
    }

    /**
     * Get trend analysis
     */
    public function trends(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        $trendAnalysis = $this->analysisService->getTrendAnalysis($startDate, $endDate);

        return Inertia::render('FinancialAnalysis/Trends', [
            'analysis' => $trendAnalysis,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d')
        ]);
    }

    /**
     * Get forecasting analysis
     */
    public function forecasting(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'forecast_months' => 'nullable|integer|min:1|max:24'
        ]);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $forecastMonths = $request->get('forecast_months', 6);

        $forecasting = $this->analysisService->getForecasting($startDate, $endDate);

        return Inertia::render('FinancialAnalysis/Forecasting', [
            'analysis' => $forecasting,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
            'forecastMonths' => $forecastMonths
        ]);
    }

    /**
     * Get customer analysis
     */
    public function customers(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        $customerAnalysis = $this->analysisService->getCustomerAnalysis($startDate, $endDate);

        return Inertia::render('FinancialAnalysis/Customers', [
            'analysis' => $customerAnalysis,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d')
        ]);
    }

    /**
     * Get product analysis
     */
    public function products(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        $productAnalysis = $this->analysisService->getProductAnalysis($startDate, $endDate);

        return Inertia::render('FinancialAnalysis/Products', [
            'analysis' => $productAnalysis,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d')
        ]);
    }

    /**
     * Export analysis report
     */
    public function export(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'format' => 'required|in:pdf,excel',
            'sections' => 'required|array'
        ]);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        $analysis = $this->analysisService->getFinancialDashboard($startDate, $endDate);

        if ($request->format === 'pdf') {
            return $this->exportToPdf($analysis, $request->sections, $startDate, $endDate);
        } else {
            return $this->exportToExcel($analysis, $request->sections, $startDate, $endDate);
        }
    }

    /**
     * Get comparison data for two periods
     */
    public function compare(Request $request)
    {
        $request->validate([
            'period1_start' => 'required|date',
            'period1_end' => 'required|date|after_or_equal:period1_start',
            'period2_start' => 'required|date',
            'period2_end' => 'required|date|after_or_equal:period2_start'
        ]);

        $period1Start = Carbon::parse($request->period1_start);
        $period1End = Carbon::parse($request->period1_end);
        $period2Start = Carbon::parse($request->period2_start);
        $period2End = Carbon::parse($request->period2_end);

        $period1Analysis = $this->analysisService->getFinancialDashboard($period1Start, $period1End);
        $period2Analysis = $this->analysisService->getFinancialDashboard($period2Start, $period2End);

        return response()->json([
            'period1' => $period1Analysis,
            'period2' => $period2Analysis,
            'comparison' => $this->calculateComparison($period1Analysis, $period2Analysis)
        ]);
    }

    /**
     * Get period dates based on selection
     */
    protected function getPeriodDates(string $period, Request $request): array
    {
        $now = now();
        
        switch ($period) {
            case 'week':
                return [$now->startOfWeek(), $now->endOfWeek()];
            case 'month':
                return [$now->startOfMonth(), $now->endOfMonth()];
            case 'quarter':
                return [$now->startOfQuarter(), $now->endOfQuarter()];
            case 'year':
                return [$now->startOfYear(), $now->endOfYear()];
            case 'custom':
                $startDate = $request->start_date ? Carbon::parse($request->start_date) : $now->startOfMonth();
                $endDate = $request->end_date ? Carbon::parse($request->end_date) : $now->endOfMonth();
                return [$startDate, $endDate];
            default:
                return [$now->startOfMonth(), $now->endOfMonth()];
        }
    }

    /**
     * Get available periods for selection
     */
    protected function getAvailablePeriods(): array
    {
        return [
            'week' => 'Esta Semana',
            'month' => 'Este Mes',
            'quarter' => 'Este Trimestre',
            'year' => 'Este Año',
            'custom' => 'Período Personalizado'
        ];
    }

    /**
     * Export analysis to PDF
     */
    protected function exportToPdf($analysis, $sections, $startDate, $endDate)
    {
        // Implementar exportación a PDF
        // Por ahora retornamos una respuesta JSON
        return response()->json([
            'message' => 'PDF export not implemented yet',
            'data' => compact('analysis', 'sections', 'startDate', 'endDate')
        ]);
    }

    /**
     * Export analysis to Excel
     */
    protected function exportToExcel($analysis, $sections, $startDate, $endDate)
    {
        // Implementar exportación a Excel
        // Por ahora retornamos una respuesta JSON
        return response()->json([
            'message' => 'Excel export not implemented yet',
            'data' => compact('analysis', 'sections', 'startDate', 'endDate')
        ]);
    }

    /**
     * Calculate comparison between two periods
     */
    protected function calculateComparison($period1, $period2): array
    {
        $comparison = [];
        
        // Comparar KPIs principales
        if (isset($period1['kpis']) && isset($period2['kpis'])) {
            $kpis1 = $period1['kpis'];
            $kpis2 = $period2['kpis'];
            
            foreach ($kpis1 as $key => $value1) {
                if (isset($kpis2[$key]) && is_numeric($value1) && is_numeric($kpis2[$key])) {
                    $value2 = $kpis2[$key];
                    $change = $value2 != 0 ? (($value1 - $value2) / $value2) * 100 : 0;
                    
                    $comparison[$key] = [
                        'period1' => $value1,
                        'period2' => $value2,
                        'change' => round($change, 2),
                        'direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'stable')
                    ];
                }
            }
        }
        
        return $comparison;
    }
}