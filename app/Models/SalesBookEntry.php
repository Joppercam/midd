<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesBookEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_book_id',
        'tax_document_id',
        'document_date',
        'document_type',
        'document_number',
        'customer_rut',
        'customer_name',
        'exempt_amount',
        'net_amount',
        'tax_amount',
        'total_amount',
        'is_electronic',
        'is_export',
        'sii_track_id',
        'status',
        'additional_data',
    ];

    protected $casts = [
        'document_date' => 'date',
        'exempt_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'is_electronic' => 'boolean',
        'is_export' => 'boolean',
        'additional_data' => 'array',
    ];

    /**
     * Get the sales book that owns the entry.
     */
    public function salesBook(): BelongsTo
    {
        return $this->belongsTo(SalesBook::class);
    }

    /**
     * Get the tax document associated with the entry.
     */
    public function taxDocument(): BelongsTo
    {
        return $this->belongsTo(TaxDocument::class);
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
            'receipt_electronic' => 'Boleta Electrónica',
            'export_invoice' => 'Factura de Exportación',
            'exempt_invoice' => 'Factura Exenta',
            default => ucfirst($this->document_type),
        };
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'active' => 'Activo',
            'cancelled' => 'Anulado',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get the status color.
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'active' => 'green',
            'cancelled' => 'red',
            default => 'gray',
        };
    }

    /**
     * Calculate IVA debito fiscal.
     */
    public function getTaxDebitAttribute(): float
    {
        // IVA débito fiscal = IVA de la venta
        return $this->status === 'active' ? $this->tax_amount : 0;
    }

    /**
     * Check if has tax debit.
     */
    public function hasTaxDebit(): bool
    {
        return $this->status === 'active' && $this->tax_amount > 0;
    }

    /**
     * Check if is a credit document.
     */
    public function isCreditDocument(): bool
    {
        return in_array($this->document_type, ['credit_note']);
    }

    /**
     * Check if is a debit document.
     */
    public function isDebitDocument(): bool
    {
        return in_array($this->document_type, ['debit_note']);
    }

    /**
     * Format RUT for display.
     */
    public function getFormattedRutAttribute(): string
    {
        if (!$this->customer_rut) {
            return 'Sin RUT';
        }
        return \App\Services\Validators\RutValidator::format($this->customer_rut);
    }

    /**
     * Scope for active entries.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for cancelled entries.
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }
}