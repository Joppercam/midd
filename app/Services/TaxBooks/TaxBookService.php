<?php

namespace App\Services\TaxBooks;

use App\Models\PurchaseBook;
use App\Models\SalesBook;
use App\Models\PurchaseBookEntry;
use App\Models\SalesBookEntry;
use App\Models\TaxDocument;
use App\Models\Expense;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class TaxBookService
{
    /**
     * Generate or update sales book for a period.
     */
    public function generateSalesBook(Tenant $tenant, int $year, int $month): SalesBook
    {
        return DB::transaction(function () use ($tenant, $year, $month) {
            // Find or create sales book
            $salesBook = SalesBook::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'year' => $year,
                    'month' => $month,
                ],
                [
                    'status' => 'draft',
                ]
            );

            // Only update if in draft status
            if ($salesBook->status !== 'draft') {
                throw new \Exception('El libro de ventas ya está finalizado.');
            }

            // Clear existing entries
            $salesBook->entries()->delete();

            // Get all tax documents for the period
            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            $documents = TaxDocument::where('tenant_id', $tenant->id)
                ->whereBetween('issue_date', [$startDate, $endDate])
                ->whereIn('status', ['issued', 'paid', 'partial'])
                ->get();

            // Create entries for each document
            foreach ($documents as $document) {
                $this->createSalesBookEntry($salesBook, $document);
            }

            // Calculate totals and generate summary
            $salesBook->calculateTotals();
            $salesBook->generateSummary();
            $salesBook->update(['generated_at' => now()]);

            return $salesBook->fresh('entries');
        });
    }

    /**
     * Generate or update purchase book for a period.
     */
    public function generatePurchaseBook(Tenant $tenant, int $year, int $month): PurchaseBook
    {
        return DB::transaction(function () use ($tenant, $year, $month) {
            // Find or create purchase book
            $purchaseBook = PurchaseBook::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'year' => $year,
                    'month' => $month,
                ],
                [
                    'status' => 'draft',
                ]
            );

            // Only update if in draft status
            if ($purchaseBook->status !== 'draft') {
                throw new \Exception('El libro de compras ya está finalizado.');
            }

            // Clear existing entries
            $purchaseBook->entries()->delete();

            // Get all expenses for the period
            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            $expenses = Expense::where('tenant_id', $tenant->id)
                ->whereBetween('expense_date', [$startDate, $endDate])
                ->whereNotNull('document_number')
                ->get();

            // Create entries for each expense
            foreach ($expenses as $expense) {
                $this->createPurchaseBookEntry($purchaseBook, $expense);
            }

            // Calculate totals and generate summary
            $purchaseBook->calculateTotals();
            $purchaseBook->generateSummary();
            $purchaseBook->update(['generated_at' => now()]);

            return $purchaseBook->fresh('entries');
        });
    }

    /**
     * Create sales book entry from tax document.
     */
    protected function createSalesBookEntry(SalesBook $salesBook, TaxDocument $document): SalesBookEntry
    {
        $documentType = $this->mapDocumentTypeForSales($document->type);
        
        return SalesBookEntry::create([
            'sales_book_id' => $salesBook->id,
            'tax_document_id' => $document->id,
            'document_date' => $document->issue_date,
            'document_type' => $documentType,
            'document_number' => $document->document_number,
            'customer_rut' => $document->customer->rut ?? null,
            'customer_name' => $document->customer->name ?? 'Consumidor Final',
            'exempt_amount' => $document->exempt_amount ?? 0,
            'net_amount' => $document->subtotal,
            'tax_amount' => $document->tax_amount,
            'total_amount' => $document->total_amount,
            'is_electronic' => $document->is_electronic ?? true,
            'is_export' => $document->is_export ?? false,
            'sii_track_id' => $document->sii_track_id,
            'status' => $document->status === 'cancelled' ? 'cancelled' : 'active',
            'additional_data' => [
                'payment_status' => $document->payment_status,
                'sii_status' => $document->sii_status,
            ],
        ]);
    }

    /**
     * Create purchase book entry from expense.
     */
    protected function createPurchaseBookEntry(PurchaseBook $purchaseBook, Expense $expense): PurchaseBookEntry
    {
        $documentType = $this->mapDocumentTypeForPurchase($expense->document_type);
        
        // Calculate amounts
        $netAmount = $expense->amount / 1.19; // Assuming 19% tax
        $taxAmount = $expense->amount - $netAmount;
        
        return PurchaseBookEntry::create([
            'purchase_book_id' => $purchaseBook->id,
            'expense_id' => $expense->id,
            'document_date' => $expense->expense_date,
            'document_type' => $documentType,
            'document_number' => $expense->document_number,
            'supplier_rut' => $expense->supplier->rut ?? '0-0',
            'supplier_name' => $expense->supplier->name ?? $expense->description,
            'description' => $expense->description,
            'exempt_amount' => 0,
            'net_amount' => round($netAmount, 2),
            'tax_amount' => round($taxAmount, 2),
            'total_amount' => $expense->amount,
            'withholding_amount' => 0,
            'other_taxes' => 0,
            'is_electronic' => true,
            'sii_track_id' => null,
            'additional_data' => [
                'category' => $expense->category->name ?? null,
                'payment_status' => $expense->is_paid ? 'paid' : 'pending',
            ],
        ]);
    }

    /**
     * Map document type for sales book.
     */
    protected function mapDocumentTypeForSales(string $type): string
    {
        return match($type) {
            'invoice' => 'invoice_electronic',
            'credit_note' => 'credit_note',
            'debit_note' => 'debit_note',
            'receipt' => 'receipt_electronic',
            default => 'invoice_electronic',
        };
    }

    /**
     * Map document type for purchase book.
     */
    protected function mapDocumentTypeForPurchase(?string $type): string
    {
        if (!$type) return 'invoice_electronic';
        
        return match($type) {
            'invoice' => 'invoice_electronic',
            'receipt' => 'receipt',
            'fee' => 'fee',
            default => 'invoice_electronic',
        };
    }

    /**
     * Finalize a sales book.
     */
    public function finalizeSalesBook(SalesBook $salesBook): void
    {
        if (!$salesBook->canFinalize()) {
            throw new \Exception('El libro no puede ser finalizado.');
        }

        $salesBook->update([
            'status' => 'final',
            'generated_at' => now(),
        ]);
    }

    /**
     * Finalize a purchase book.
     */
    public function finalizePurchaseBook(PurchaseBook $purchaseBook): void
    {
        if (!$purchaseBook->canFinalize()) {
            throw new \Exception('El libro no puede ser finalizado.');
        }

        $purchaseBook->update([
            'status' => 'final',
            'generated_at' => now(),
        ]);
    }

    /**
     * Export sales book to Excel.
     */
    public function exportSalesBookToExcel(SalesBook $salesBook): string
    {
        $export = new SalesBookExport($salesBook);
        $fileName = "libro_ventas_{$salesBook->year}_{$salesBook->month}.xlsx";
        $path = "tax_books/sales/{$fileName}";
        
        Excel::store($export, $path, 'local');
        
        return storage_path("app/{$path}");
    }

    /**
     * Export purchase book to Excel.
     */
    public function exportPurchaseBookToExcel(PurchaseBook $purchaseBook): string
    {
        $export = new PurchaseBookExport($purchaseBook);
        $fileName = "libro_compras_{$purchaseBook->year}_{$purchaseBook->month}.xlsx";
        $path = "tax_books/purchase/{$fileName}";
        
        Excel::store($export, $path, 'local');
        
        return storage_path("app/{$path}");
    }

    /**
     * Export sales book to PDF.
     */
    public function exportSalesBookToPdf(SalesBook $salesBook): string
    {
        $pdf = Pdf::loadView('tax-books.sales-pdf', [
            'book' => $salesBook->load('entries'),
            'tenant' => $salesBook->tenant,
        ]);

        $fileName = "libro_ventas_{$salesBook->year}_{$salesBook->month}.pdf";
        $path = storage_path("app/tax_books/sales/{$fileName}");
        
        $pdf->save($path);
        
        return $path;
    }

    /**
     * Export purchase book to PDF.
     */
    public function exportPurchaseBookToPdf(PurchaseBook $purchaseBook): string
    {
        $pdf = Pdf::loadView('tax-books.purchase-pdf', [
            'book' => $purchaseBook->load('entries'),
            'tenant' => $purchaseBook->tenant,
        ]);

        $fileName = "libro_compras_{$purchaseBook->year}_{$purchaseBook->month}.pdf";
        $path = storage_path("app/tax_books/purchase/{$fileName}");
        
        $pdf->save($path);
        
        return $path;
    }

    /**
     * Get tax summary for a period.
     */
    public function getTaxSummary(Tenant $tenant, int $year, int $month): array
    {
        $salesBook = SalesBook::where('tenant_id', $tenant->id)
            ->period($year, $month)
            ->first();

        $purchaseBook = PurchaseBook::where('tenant_id', $tenant->id)
            ->period($year, $month)
            ->first();

        $salesTax = $salesBook ? $salesBook->total_tax : 0;
        $purchaseTax = $purchaseBook ? $purchaseBook->total_tax : 0;
        $balance = $salesTax - $purchaseTax;

        return [
            'sales_tax' => $salesTax,
            'purchase_tax' => $purchaseTax,
            'balance' => $balance,
            'to_pay' => $balance > 0 ? $balance : 0,
            'credit' => $balance < 0 ? abs($balance) : 0,
        ];
    }
}