<?php

namespace App\Http\Controllers\TaxBooks;

use App\Http\Controllers\Controller;
use App\Models\PurchaseBook;
use App\Models\SalesBook;
use App\Services\TaxBooks\TaxBookService;
use App\Traits\ChecksPermissions;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class TaxBookController extends Controller
{
    use ChecksPermissions;

    protected $taxBookService;

    public function __construct(TaxBookService $taxBookService)
    {
        $this->taxBookService = $taxBookService;
    }

    /**
     * Display the tax books dashboard.
     */
    public function index(Request $request)
    {
        $this->checkPermission('tax_books.view');

        $currentYear = $request->get('year', now()->year);
        $currentMonth = $request->get('month', now()->month);

        // Get or generate books for the selected period
        $salesBook = SalesBook::where('tenant_id', auth()->user()->tenant_id)
            ->period($currentYear, $currentMonth)
            ->with('entries')
            ->first();

        $purchaseBook = PurchaseBook::where('tenant_id', auth()->user()->tenant_id)
            ->period($currentYear, $currentMonth)
            ->with('entries')
            ->first();

        // Get tax summary
        $taxSummary = $this->taxBookService->getTaxSummary(
            auth()->user()->tenant,
            $currentYear,
            $currentMonth
        );

        // Get available periods
        $availablePeriods = $this->getAvailablePeriods();

        return Inertia::render('TaxBooks/Index', [
            'salesBook' => $salesBook,
            'purchaseBook' => $purchaseBook,
            'taxSummary' => $taxSummary,
            'currentYear' => $currentYear,
            'currentMonth' => $currentMonth,
            'availablePeriods' => $availablePeriods,
            'can' => [
                'generate' => auth()->user()->can('tax_books.generate'),
                'finalize' => auth()->user()->can('tax_books.finalize'),
                'export' => auth()->user()->can('tax_books.export'),
            ],
        ]);
    }

    /**
     * Show sales book details.
     */
    public function showSales(SalesBook $salesBook)
    {
        $this->checkPermission('tax_books.view');
        $this->authorize('view', $salesBook);

        $salesBook->load(['entries.taxDocument.customer']);

        return Inertia::render('TaxBooks/SalesShow', [
            'book' => $salesBook,
            'can' => [
                'edit' => $salesBook->canEdit() && auth()->user()->can('tax_books.edit'),
                'finalize' => $salesBook->canFinalize() && auth()->user()->can('tax_books.finalize'),
                'export' => auth()->user()->can('tax_books.export'),
            ],
        ]);
    }

    /**
     * Show purchase book details.
     */
    public function showPurchase(PurchaseBook $purchaseBook)
    {
        $this->checkPermission('tax_books.view');
        $this->authorize('view', $purchaseBook);

        $purchaseBook->load(['entries.expense.supplier']);

        return Inertia::render('TaxBooks/PurchaseShow', [
            'book' => $purchaseBook,
            'can' => [
                'edit' => $purchaseBook->canEdit() && auth()->user()->can('tax_books.edit'),
                'finalize' => $purchaseBook->canFinalize() && auth()->user()->can('tax_books.finalize'),
                'export' => auth()->user()->can('tax_books.export'),
            ],
        ]);
    }

    /**
     * Generate sales book for a period.
     */
    public function generateSales(Request $request)
    {
        $this->checkPermission('tax_books.generate');

        $request->validate([
            'year' => 'required|integer|min:2020|max:' . (now()->year + 1),
            'month' => 'required|integer|min:1|max:12',
        ]);

        try {
            $salesBook = $this->taxBookService->generateSalesBook(
                auth()->user()->tenant,
                $request->year,
                $request->month
            );

            return redirect()
                ->route('tax-books.sales.show', $salesBook)
                ->with('success', 'Libro de ventas generado exitosamente.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Generate purchase book for a period.
     */
    public function generatePurchase(Request $request)
    {
        $this->checkPermission('tax_books.generate');

        $request->validate([
            'year' => 'required|integer|min:2020|max:' . (now()->year + 1),
            'month' => 'required|integer|min:1|max:12',
        ]);

        try {
            $purchaseBook = $this->taxBookService->generatePurchaseBook(
                auth()->user()->tenant,
                $request->year,
                $request->month
            );

            return redirect()
                ->route('tax-books.purchase.show', $purchaseBook)
                ->with('success', 'Libro de compras generado exitosamente.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Finalize sales book.
     */
    public function finalizeSales(SalesBook $salesBook)
    {
        $this->checkPermission('tax_books.finalize');
        $this->authorize('update', $salesBook);

        try {
            $this->taxBookService->finalizeSalesBook($salesBook);

            return redirect()
                ->route('tax-books.sales.show', $salesBook)
                ->with('success', 'Libro de ventas finalizado exitosamente.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Finalize purchase book.
     */
    public function finalizePurchase(PurchaseBook $purchaseBook)
    {
        $this->checkPermission('tax_books.finalize');
        $this->authorize('update', $purchaseBook);

        try {
            $this->taxBookService->finalizePurchaseBook($purchaseBook);

            return redirect()
                ->route('tax-books.purchase.show', $purchaseBook)
                ->with('success', 'Libro de compras finalizado exitosamente.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Export sales book to Excel.
     */
    public function exportSalesExcel(SalesBook $salesBook)
    {
        $this->checkPermission('tax_books.export');
        $this->authorize('view', $salesBook);

        $filePath = $this->taxBookService->exportSalesBookToExcel($salesBook);
        
        return response()->download($filePath)->deleteFileAfterSend();
    }

    /**
     * Export purchase book to Excel.
     */
    public function exportPurchaseExcel(PurchaseBook $purchaseBook)
    {
        $this->checkPermission('tax_books.export');
        $this->authorize('view', $purchaseBook);

        $filePath = $this->taxBookService->exportPurchaseBookToExcel($purchaseBook);
        
        return response()->download($filePath)->deleteFileAfterSend();
    }

    /**
     * Export sales book to PDF.
     */
    public function exportSalesPdf(SalesBook $salesBook)
    {
        $this->checkPermission('tax_books.export');
        $this->authorize('view', $salesBook);

        $filePath = $this->taxBookService->exportSalesBookToPdf($salesBook);
        
        return response()->download($filePath)->deleteFileAfterSend();
    }

    /**
     * Export purchase book to PDF.
     */
    public function exportPurchasePdf(PurchaseBook $purchaseBook)
    {
        $this->checkPermission('tax_books.export');
        $this->authorize('view', $purchaseBook);

        $filePath = $this->taxBookService->exportPurchaseBookToPdf($purchaseBook);
        
        return response()->download($filePath)->deleteFileAfterSend();
    }

    /**
     * Get available periods for tax books.
     */
    protected function getAvailablePeriods(): array
    {
        $periods = [];
        $currentDate = now();
        
        // Get last 12 months
        for ($i = 0; $i < 12; $i++) {
            $date = $currentDate->copy()->subMonths($i);
            $periods[] = [
                'year' => $date->year,
                'month' => $date->month,
                'label' => $date->format('F Y'),
            ];
        }

        return $periods;
    }
}