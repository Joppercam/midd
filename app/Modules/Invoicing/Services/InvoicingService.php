<?php

namespace App\Modules\Invoicing\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Modules\Invoicing\Models\Quote;
use App\Modules\Invoicing\Models\TaxDocument;
use App\Services\SII\SIIService;
use Illuminate\Support\Facades\DB;

class InvoicingService
{
    private SIIService $siiService;

    public function __construct(SIIService $siiService)
    {
        $this->siiService = $siiService;
    }

    /**
     * Crear cotización
     */
    public function createQuote(Customer $customer, array $items, array $data = []): Quote
    {
        DB::beginTransaction();
        try {
            $totals = $this->calculateTotals($items);
            
            $quote = Quote::create([
                'tenant_id' => auth()->user()->tenant_id,
                'customer_id' => $customer->id,
                'quote_number' => $this->generateQuoteNumber(),
                'issue_date' => now(),
                'valid_until' => now()->addDays($data['validity_days'] ?? 30),
                'status' => 'pending',
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'currency' => $data['currency'] ?? 'CLP',
                'payment_terms' => $data['payment_terms'] ?? 30,
                'notes' => $data['notes'] ?? null,
                'terms_conditions' => $data['terms_conditions'] ?? null,
            ]);

            $this->createQuoteItems($quote, $items);

            DB::commit();
            return $quote;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Crear factura
     */
    public function createInvoice(Customer $customer, array $items, array $data = []): TaxDocument
    {
        DB::beginTransaction();
        try {
            $totals = $this->calculateTotals($items);
            
            $invoice = TaxDocument::create([
                'tenant_id' => auth()->user()->tenant_id,
                'customer_id' => $customer->id,
                'document_type' => $data['document_type'] ?? 'factura_electronica',
                'document_number' => $this->generateDocumentNumber($data['document_type'] ?? 'factura_electronica'),
                'issue_date' => $data['issue_date'] ?? now(),
                'due_date' => $data['due_date'] ?? now()->addDays($data['payment_terms'] ?? 30),
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'currency' => $data['currency'] ?? 'CLP',
                'payment_terms' => $data['payment_terms'] ?? 30,
                'payment_status' => 'pending',
                'sii_status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'quote_id' => $data['quote_id'] ?? null,
            ]);

            $this->createInvoiceItems($invoice, $items);

            DB::commit();
            return $invoice;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Crear nota de crédito
     */
    public function createCreditNote(TaxDocument $originalInvoice, array $items, string $reason): TaxDocument
    {
        if ($originalInvoice->document_type !== 'factura_electronica') {
            throw new \Exception('Solo se pueden crear notas de crédito para facturas electrónicas');
        }

        if ($originalInvoice->sii_status !== 'accepted') {
            throw new \Exception('La factura debe estar aceptada por el SII');
        }

        DB::beginTransaction();
        try {
            $totals = $this->calculateTotals($items);
            
            $creditNote = TaxDocument::create([
                'tenant_id' => $originalInvoice->tenant_id,
                'customer_id' => $originalInvoice->customer_id,
                'document_type' => 'nota_credito',
                'document_number' => $this->generateDocumentNumber('nota_credito'),
                'issue_date' => now(),
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'currency' => $originalInvoice->currency,
                'payment_status' => 'not_applicable',
                'sii_status' => 'draft',
                'reference_document_type' => $originalInvoice->document_type,
                'reference_document_number' => $originalInvoice->document_number,
                'reference_reason' => $reason,
                'notes' => "Nota de crédito por: {$reason}",
                'original_invoice_id' => $originalInvoice->id,
            ]);

            $this->createInvoiceItems($creditNote, $items);

            DB::commit();
            return $creditNote;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Enviar documento al SII
     */
    public function sendToSII(TaxDocument $document): bool
    {
        if (!in_array($document->document_type, ['factura_electronica', 'nota_credito', 'nota_debito'])) {
            throw new \Exception('Este tipo de documento no se envía al SII');
        }

        if ($document->sii_status === 'accepted') {
            throw new \Exception('Este documento ya fue aceptado por el SII');
        }

        try {
            $result = $this->siiService->sendDocument($document);
            
            $document->update([
                'sii_status' => $result['status'],
                'sii_track_id' => $result['track_id'] ?? null,
                'sii_response' => $result['response'] ?? null,
                'sent_to_sii_at' => now(),
            ]);

            return $result['success'];
        } catch (\Exception $e) {
            $document->update([
                'sii_status' => 'failed',
                'sii_response' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Consultar estado en el SII
     */
    public function checkSIIStatus(TaxDocument $document): void
    {
        if (!$document->sii_track_id) {
            throw new \Exception('No hay track ID para consultar');
        }

        try {
            $status = $this->siiService->getDocumentStatus($document->sii_track_id);
            
            $document->update([
                'sii_status' => $status['status'],
                'sii_response' => $status['response'] ?? null,
                'sii_acceptance_date' => $status['acceptance_date'] ?? null,
            ]);
        } catch (\Exception $e) {
            $document->update([
                'sii_status' => 'failed',
                'sii_response' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Anular documento
     */
    public function voidDocument(TaxDocument $document, string $reason): void
    {
        if (!$document->can_void) {
            throw new \Exception('Este documento no puede ser anulado');
        }

        // Si ya fue enviado al SII, crear nota de crédito
        if ($document->sii_status === 'accepted') {
            $this->createCreditNote($document, $document->items->toArray(), $reason);
        } else {
            // Si no fue enviado, simplemente marcarlo como anulado
            $document->update([
                'status' => 'voided',
                'void_reason' => $reason,
                'voided_at' => now(),
                'voided_by' => auth()->id(),
            ]);
        }
    }

    /**
     * Registrar pago
     */
    public function recordPayment(TaxDocument $document, float $amount, string $method, array $details = []): void
    {
        if ($document->document_type !== 'factura_electronica') {
            throw new \Exception('Solo se pueden registrar pagos para facturas');
        }

        $remainingAmount = $document->total - $document->paid_amount;
        
        if ($amount > $remainingAmount) {
            throw new \Exception('El monto excede la deuda pendiente');
        }

        DB::beginTransaction();
        try {
            // Crear registro de pago
            $document->payments()->create([
                'amount' => $amount,
                'payment_method' => $method,
                'payment_date' => $details['payment_date'] ?? now(),
                'reference' => $details['reference'] ?? null,
                'notes' => $details['notes'] ?? null,
            ]);

            // Actualizar estado de pago
            $totalPaid = $document->payments()->sum('amount');
            
            if ($totalPaid >= $document->total) {
                $paymentStatus = 'paid';
            } elseif ($totalPaid > 0) {
                $paymentStatus = 'partial';
            } else {
                $paymentStatus = 'pending';
            }

            $document->update([
                'payment_status' => $paymentStatus,
                'paid_amount' => $totalPaid,
                'paid_at' => $paymentStatus === 'paid' ? now() : null,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Calcular totales
     */
    private function calculateTotals(array $items): array
    {
        $subtotal = 0;
        $taxAmount = 0;

        foreach ($items as $item) {
            $itemSubtotal = $item['quantity'] * $item['unit_price'];
            $itemDiscount = $itemSubtotal * ($item['discount_percentage'] ?? 0) / 100;
            $itemNet = $itemSubtotal - $itemDiscount;
            $itemTax = $itemNet * ($item['tax_rate'] ?? 0.19);

            $subtotal += $itemNet;
            $taxAmount += $itemTax;
        }

        return [
            'subtotal' => round($subtotal, 2),
            'tax_amount' => round($taxAmount, 2),
            'total' => round($subtotal + $taxAmount, 2),
        ];
    }

    /**
     * Crear items de cotización
     */
    private function createQuoteItems(Quote $quote, array $items): void
    {
        foreach ($items as $item) {
            $product = Product::find($item['product_id']);
            
            $quote->items()->create([
                'product_id' => $item['product_id'],
                'description' => $item['description'] ?? $product?->name,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'discount_percentage' => $item['discount_percentage'] ?? 0,
                'tax_rate' => $item['tax_rate'] ?? 0.19,
                'total' => $this->calculateItemTotal($item),
            ]);
        }
    }

    /**
     * Crear items de factura
     */
    private function createInvoiceItems(TaxDocument $invoice, array $items): void
    {
        foreach ($items as $item) {
            $product = Product::find($item['product_id']);
            
            $invoice->items()->create([
                'product_id' => $item['product_id'],
                'description' => $item['description'] ?? $product?->name,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'discount_percentage' => $item['discount_percentage'] ?? 0,
                'tax_rate' => $item['tax_rate'] ?? 0.19,
                'total' => $this->calculateItemTotal($item),
            ]);

            // Actualizar stock si es necesario
            if ($product && $product->track_stock) {
                $product->decrement('current_stock', $item['quantity']);
            }
        }
    }

    /**
     * Calcular total de item
     */
    private function calculateItemTotal(array $item): float
    {
        $subtotal = $item['quantity'] * $item['unit_price'];
        $discount = $subtotal * ($item['discount_percentage'] ?? 0) / 100;
        $net = $subtotal - $discount;
        $tax = $net * ($item['tax_rate'] ?? 0.19);
        
        return round($net + $tax, 2);
    }

    /**
     * Generar número de cotización
     */
    private function generateQuoteNumber(): string
    {
        $tenantId = auth()->user()->tenant_id;
        $year = now()->year;
        
        $lastQuote = Quote::where('tenant_id', $tenantId)
            ->whereYear('created_at', $year)
            ->orderBy('quote_number', 'desc')
            ->first();
        
        if ($lastQuote && preg_match('/COT-' . $year . '-(\d+)/', $lastQuote->quote_number, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }
        
        return 'COT-' . $year . '-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generar número de documento
     */
    private function generateDocumentNumber(string $documentType): string
    {
        $tenantId = auth()->user()->tenant_id;
        
        $prefix = match($documentType) {
            'factura_electronica' => 'F',
            'nota_credito' => 'NC',
            'nota_debito' => 'ND',
            'boleta_electronica' => 'B',
            default => 'DOC',
        };
        
        $lastDocument = TaxDocument::where('tenant_id', $tenantId)
            ->where('document_type', $documentType)
            ->whereNotNull('document_number')
            ->orderBy('document_number', 'desc')
            ->first();
        
        if ($lastDocument && preg_match('/' . $prefix . '(\d+)/', $lastDocument->document_number, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }
        
        return $prefix . str_pad($nextNumber, 10, '0', STR_PAD_LEFT);
    }
}