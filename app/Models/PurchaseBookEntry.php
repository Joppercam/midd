<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseBookEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_book_id',
        'expense_id',
        'document_date',
        'document_type',
        'document_number',
        'supplier_rut',
        'supplier_name',
        'description',
        'exempt_amount',
        'net_amount',
        'tax_amount',
        'total_amount',
        'withholding_amount',
        'other_taxes',
        'is_electronic',
        'sii_track_id',
        'additional_data',
    ];

    protected $casts = [
        'document_date' => 'date',
        'exempt_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'withholding_amount' => 'decimal:2',
        'other_taxes' => 'decimal:2',
        'is_electronic' => 'boolean',
        'additional_data' => 'array',
    ];

    /**
     * Get the purchase book that owns the entry.
     */
    public function purchaseBook(): BelongsTo
    {
        return $this->belongsTo(PurchaseBook::class);
    }

    /**
     * Get the expense associated with the entry.
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /**
     * Get the document type label.
     */
    public function getDocumentTypeLabelAttribute(): string
    {
        return match($this->document_type) {
            'invoice' => 'Factura',
            'invoice_electronic' => 'Factura Electrónica',
            'credit_note' => 'Nota de Crédito',
            'debit_note' => 'Nota de Débito',
            'receipt' => 'Boleta',
            'fee' => 'Honorarios',
            'import' => 'Importación',
            'other' => 'Otro',
            default => ucfirst($this->document_type),
        };
    }

    /**
     * Calculate IVA credito fiscal.
     */
    public function getTaxCreditAttribute(): float
    {
        // IVA crédito fiscal = IVA de la compra
        return $this->tax_amount;
    }

    /**
     * Check if has tax credit.
     */
    public function hasTaxCredit(): bool
    {
        return $this->tax_amount > 0;
    }

    /**
     * Check if is an import document.
     */
    public function isImport(): bool
    {
        return $this->document_type === 'import';
    }

    /**
     * Format RUT for display.
     */
    public function getFormattedRutAttribute(): string
    {
        return \App\Services\Validators\RutValidator::format($this->supplier_rut);
    }
}