<?php

namespace App\Http\Controllers\Exports;

use App\Http\Controllers\Controller;
use App\Services\Exports\AccountingExportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class AccountingExportController extends Controller
{
    protected AccountingExportService $exportService;

    public function __construct(AccountingExportService $exportService)
    {
        $this->middleware(['auth', 'tenant']);
        $this->exportService = $exportService;
    }

    /**
     * Display the accounting export interface
     */
    public function index()
    {
        $formats = $this->exportService->getAvailableFormats();
        
        return Inertia::render('Exports/AccountingExports', [
            'formats' => $formats,
            'currentYear' => now()->year,
            'currentMonth' => now()->month
        ]);
    }

    /**
     * Export to CONTPAq format
     */
    public function exportContpaq(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:' . (now()->year + 1),
            'month' => 'required|integer|min:1|max:12'
        ]);

        try {
            $filePath = $this->exportService->exportToContpaq(
                $request->year,
                $request->month
            );

            return response()->download($filePath)->deleteFileAfterSend();
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al generar exportación: ' . $e->getMessage()]);
        }
    }

    /**
     * Export to Mónica format
     */
    public function exportMonica(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:' . (now()->year + 1),
            'month' => 'required|integer|min:1|max:12'
        ]);

        try {
            $filePath = $this->exportService->exportToMonica(
                $request->year,
                $request->month
            );

            return response()->download($filePath)->deleteFileAfterSend();
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al generar exportación: ' . $e->getMessage()]);
        }
    }

    /**
     * Export to Tango format
     */
    public function exportTango(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:' . (now()->year + 1),
            'month' => 'required|integer|min:1|max:12'
        ]);

        try {
            $filePath = $this->exportService->exportToTango(
                $request->year,
                $request->month
            );

            return response()->download($filePath)->deleteFileAfterSend();
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al generar exportación: ' . $e->getMessage()]);
        }
    }

    /**
     * Export to SII format
     */
    public function exportSII(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:' . (now()->year + 1),
            'month' => 'required|integer|min:1|max:12',
            'type' => 'required|in:sales,purchases,both'
        ]);

        try {
            $data = $this->exportService->exportSIIFormat(
                $request->year,
                $request->month
            );

            $responseData = [];
            
            if ($request->type === 'sales' || $request->type === 'both') {
                $responseData['sales'] = $data['sales'];
            }
            
            if ($request->type === 'purchases' || $request->type === 'both') {
                $responseData['purchases'] = $data['purchases'];
            }

            $filename = sprintf(
                'sii_%s_%04d_%02d.json',
                $request->type,
                $request->year,
                $request->month
            );

            return response()->json($responseData)
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
                
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al generar exportación SII: ' . $e->getMessage()]);
        }
    }

    /**
     * Get export preview
     */
    public function preview(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:' . (now()->year + 1),
            'month' => 'required|integer|min:1|max:12',
            'format' => 'required|in:contpaq,monica,tango,sii'
        ]);

        try {
            $tenant = app('currentTenant');
            
            // Get sample data for preview
            $startDate = now()->create($request->year, $request->month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();
            
            $preview = $this->getPreviewData($request->format, $startDate, $endDate);
            
            return response()->json([
                'preview' => $preview,
                'period' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d')
                ],
                'tenant' => [
                    'name' => $tenant->name,
                    'rut' => $tenant->rut
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get available export history
     */
    public function history()
    {
        $tenant = app('currentTenant');
        $exportsPath = storage_path('app/exports/accounting');
        
        if (!is_dir($exportsPath)) {
            return response()->json(['exports' => []]);
        }
        
        $files = glob($exportsPath . '/*_' . $tenant->id . '_*.{txt,csv,json}', GLOB_BRACE);
        $exports = [];
        
        foreach ($files as $file) {
            $filename = basename($file);
            $parts = explode('_', $filename);
            
            if (count($parts) >= 4) {
                $exports[] = [
                    'filename' => $filename,
                    'format' => $parts[0],
                    'year' => $parts[2],
                    'month' => str_replace(['.txt', '.csv', '.json'], '', $parts[3]),
                    'size' => filesize($file),
                    'created_at' => date('Y-m-d H:i:s', filemtime($file))
                ];
            }
        }
        
        // Sort by creation date desc
        usort($exports, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return response()->json(['exports' => $exports]);
    }

    /**
     * Download previous export
     */
    public function download(Request $request, string $filename)
    {
        $tenant = app('currentTenant');
        $filePath = storage_path('app/exports/accounting/' . $filename);
        
        // Verify file belongs to current tenant
        if (!str_contains($filename, '_' . $tenant->id . '_')) {
            abort(403);
        }
        
        if (!file_exists($filePath)) {
            abort(404);
        }
        
        return response()->download($filePath);
    }

    /**
     * Get export statistics
     */
    public function statistics(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:' . (now()->year + 1),
            'month' => 'required|integer|min:1|max:12'
        ]);

        try {
            $tenant = app('currentTenant');
            $startDate = now()->create($request->year, $request->month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();
            
            $stats = $this->calculateExportStatistics($startDate, $endDate);
            
            return response()->json([
                'statistics' => $stats,
                'period' => [
                    'year' => $request->year,
                    'month' => $request->month,
                    'name' => $startDate->format('F Y')
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    protected function getPreviewData(string $format, $startDate, $endDate): array
    {
        $reflection = new \ReflectionClass($this->exportService);
        $method = $reflection->getMethod('getAccountingData');
        $method->setAccessible(true);
        
        $data = $method->invoke($this->exportService, $startDate, $endDate);
        
        return [
            'sales_count' => $data['sales']->count(),
            'purchases_count' => $data['purchases']->count(),
            'payments_count' => $data['payments']->count(),
            'total_sales' => $data['sales']->sum('total_amount'),
            'total_purchases' => $data['purchases']->sum('total_amount'),
            'total_payments' => $data['payments']->sum('amount'),
            'sample_records' => [
                'sales' => $data['sales']->take(3)->map(function($sale) {
                    return [
                        'date' => $sale->date->format('d/m/Y'),
                        'number' => $sale->number,
                        'customer' => $sale->customer->name ?? 'Consumidor Final',
                        'amount' => $sale->total_amount
                    ];
                }),
                'purchases' => $data['purchases']->take(3)->map(function($purchase) {
                    return [
                        'date' => $purchase->date->format('d/m/Y'),
                        'number' => $purchase->document_number,
                        'supplier' => $purchase->supplier->name,
                        'amount' => $purchase->total_amount
                    ];
                })
            ]
        ];
    }

    protected function calculateExportStatistics($startDate, $endDate): array
    {
        $tenant = app('currentTenant');
        
        $sales = \App\Models\TaxDocument::where('tenant_id', $tenant->id)
            ->where('type', 'invoice')
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('status', ['issued', 'paid'])
            ->get();
            
        $purchases = \App\Models\Expense::where('tenant_id', $tenant->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('status', ['approved', 'paid'])
            ->get();
            
        $creditNotes = \App\Models\TaxDocument::where('tenant_id', $tenant->id)
            ->where('type', 'credit_note')
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('status', ['issued', 'paid'])
            ->get();
        
        return [
            'sales' => [
                'count' => $sales->count(),
                'total_net' => $sales->sum('subtotal'),
                'total_tax' => $sales->sum('tax_amount'),
                'total_amount' => $sales->sum('total_amount')
            ],
            'purchases' => [
                'count' => $purchases->count(),
                'total_net' => $purchases->sum('net_amount'),
                'total_tax' => $purchases->sum('tax_amount'),
                'total_amount' => $purchases->sum('total_amount')
            ],
            'credit_notes' => [
                'count' => $creditNotes->count(),
                'total_amount' => $creditNotes->sum('total_amount')
            ],
            'tax_summary' => [
                'iva_debito' => $sales->sum('tax_amount'),
                'iva_credito' => $purchases->sum('tax_amount'),
                'iva_resultado' => $sales->sum('tax_amount') - $purchases->sum('tax_amount')
            ]
        ];
    }
}