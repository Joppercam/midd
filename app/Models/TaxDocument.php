<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;
use App\Traits\InvalidatesCache;

class TaxDocument extends TenantAwareModel
{
    use HasFactory, Auditable, InvalidatesCache;

    protected $fillable = [
        'customer_id',
        'user_id',
        'type',
        'number',
        'folio', // Campo crítico para SII
        'document_type', // Tipo de documento SII (33, 34, 39, 56, 61)
        'sii_document_type', // Mapeo adicional para SII
        'sii_track_id',
        'status',
        'payment_status',
        'sii_status',
        'sii_status_detail',
        'sii_response',
        'sii_acceptance_status',
        'sii_dte_id',
        'sii_send_attempts',
        'sii_send_errors',
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
        'dte_generated_at',
        'sii_accepted_at',
        'sii_rejected_at',
        'sii_last_check',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'sent_at' => 'datetime',
        'generated_at' => 'datetime',
        'checked_at' => 'datetime',
        'dte_generated_at' => 'datetime',
        'sii_accepted_at' => 'datetime',
        'sii_rejected_at' => 'datetime',
        'sii_last_check' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'sii_response' => 'array',
        'sii_send_errors' => 'array',
        'sii_send_attempts' => 'integer',
        'folio' => 'integer',
        'document_type' => 'integer',
        'sii_document_type' => 'integer',
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

    // Mapeo de tipos de documento SII
    const SII_DOCUMENT_TYPES = [
        33 => 'Factura Electrónica',
        34 => 'Factura No Afecta o Exenta Electrónica',
        39 => 'Boleta Electrónica',
        56 => 'Nota de Débito Electrónica',
        61 => 'Nota de Crédito Electrónica',
    ];

    // Mapeo de tipos internos a códigos SII
    const TYPE_TO_SII_CODE = [
        'invoice' => 33,
        'invoice_exempt' => 34,
        'receipt' => 39,
        'debit_note' => 56,
        'credit_note' => 61,
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    /**
     * SII Status helpers
     */
    public function getIsSiiAcceptedAttribute(): bool
    {
        return $this->sii_status === 'accepted' || $this->sii_acceptance_status === '0';
    }

    public function getIsSiiRejectedAttribute(): bool
    {
        return $this->sii_status === 'rejected' || !empty($this->sii_rejected_at);
    }

    public function getIsSiiPendingAttribute(): bool
    {
        return in_array($this->sii_status, ['sent', 'processing', 'generated']);
    }

    public function getCanResendToSiiAttribute(): bool
    {
        return $this->sii_status === 'rejected' || 
               $this->sii_status === 'error' ||
               ($this->sii_status === 'generated' && !$this->sii_track_id);
    }

    public function getSiiStatusLabelAttribute(): string
    {
        $labels = [
            'draft' => 'Borrador',
            'generated' => 'Generado',
            'sent' => 'Enviado',
            'processing' => 'Procesando',
            'accepted' => 'Aceptado',
            'accepted_with_discrepancies' => 'Aceptado con reparos',
            'rejected' => 'Rechazado',
            'error' => 'Error',
            'error_schema' => 'Error de esquema',
            'error_signature' => 'Error de firma',
            'error_system' => 'Error del sistema',
            'error_authentication' => 'Error de autenticación',
            'error_authorization' => 'No autorizado',
            'unknown' => 'Desconocido',
        ];

        return $labels[$this->sii_status] ?? $this->sii_status;
    }

    public function getSiiStatusColorAttribute(): string
    {
        $colors = [
            'draft' => 'gray',
            'generated' => 'blue',
            'sent' => 'yellow',
            'processing' => 'yellow',
            'accepted' => 'green',
            'accepted_with_discrepancies' => 'green',
            'rejected' => 'red',
            'error' => 'red',
            'error_schema' => 'red',
            'error_signature' => 'red',
            'error_system' => 'red',
            'error_authentication' => 'red',
            'error_authorization' => 'red',
            'unknown' => 'gray',
        ];

        return $colors[$this->sii_status] ?? 'gray';
    }

    /**
     * Get SII event logs for this document
     */
    public function siiEventLogs()
    {
        return $this->hasMany(\App\Models\SiiEventLog::class);
    }

    /**
     * Get the latest SII event log
     */
    public function getLatestSiiEventAttribute()
    {
        return $this->siiEventLogs()->latest()->first();
    }
    
    /**
     * Boot method to handle model events
     */
    protected static function boot()
    {
        parent::boot();
        
        // Invalidate cache on create, update, or delete
        static::saved(function ($document) {
            $document->invalidateDashboardCache($document->tenant_id);
            $document->invalidateSalesReportCache($document->tenant_id);
        });
        
        static::deleted(function ($document) {
            $document->invalidateDashboardCache($document->tenant_id);
            $document->invalidateSalesReportCache($document->tenant_id);
        });
    }
}