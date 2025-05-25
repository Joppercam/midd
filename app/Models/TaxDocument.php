<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxDocument extends TenantAwareModel
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'type',
        'number',
        'sii_track_id',
        'status',
        'sii_status',
        'sii_response',
        'issue_date',
        'due_date',
        'subtotal',
        'tax_amount',
        'total',
        'xml_content',
        'pdf_path',
        'paid_at',
        'sent_at',
        'generated_at',
        'checked_at',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    const TYPES = [
        'invoice' => 'Factura Electrónica',
        'receipt' => 'Boleta Electrónica',
        'credit_note' => 'Nota de Crédito',
        'debit_note' => 'Nota de Débito',
    ];

    const STATUSES = [
        'draft' => 'Borrador',
        'sent' => 'Enviado',
        'accepted' => 'Aceptado',
        'rejected' => 'Rechazado',
        'cancelled' => 'Anulado',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TaxDocumentItem::class);
    }

    public function getFormattedNumberAttribute(): string
    {
        return sprintf('%s-%s', strtoupper($this->type), $this->number);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->due_date && 
               $this->due_date->isPast() && 
               !$this->paid_at &&
               $this->status === 'accepted';
    }

    public function getDaysOverdueAttribute(): int
    {
        if (!$this->is_overdue) return 0;
        return $this->due_date->diffInDays(now());
    }

    public function calculateTotals(): void
    {
        $subtotal = $this->items->sum(function ($item) {
            return $item->quantity * $item->unit_price;
        });

        $taxAmount = $subtotal * 0.19; // IVA 19% Chile
        
        $this->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $subtotal + $taxAmount,
        ]);
    }
}