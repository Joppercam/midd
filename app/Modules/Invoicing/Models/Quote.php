<?php

namespace App\Modules\Invoicing\Models;

use App\Models\Quote as BaseQuote;

class Quote extends BaseQuote
{
    /**
     * Obtener cotizaciones pendientes de aprobación
     */
    public function scopePendingApproval($query)
    {
        return $query->where('status', 'pending');
    }
    
    /**
     * Obtener cotizaciones por vencer
     */
    public function scopeExpiringSoon($query, int $days = 7)
    {
        return $query->where('status', 'pending')
            ->where('valid_until', '>', now())
            ->where('valid_until', '<=', now()->addDays($days));
    }
    
    /**
     * Obtener cotizaciones vencidas
     */
    public function scopeExpired($query)
    {
        return $query->where('valid_until', '<', now())
            ->where('status', 'pending');
    }
    
    /**
     * Verificar si está vencida
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->valid_until && $this->valid_until->isPast() && $this->status === 'pending';
    }
    
    /**
     * Verificar si puede ser convertida a factura
     */
    public function getCanConvertToInvoiceAttribute(): bool
    {
        return $this->status === 'approved' && !$this->is_expired;
    }
    
    /**
     * Días hasta vencimiento
     */
    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->valid_until || $this->status !== 'pending') {
            return null;
        }
        
        return now()->diffInDays($this->valid_until, false);
    }
    
    /**
     * Convertir a factura
     */
    public function convertToInvoice(array $additionalData = []): TaxDocument
    {
        if (!$this->can_convert_to_invoice) {
            throw new \Exception('Esta cotización no puede ser convertida a factura');
        }
        
        $invoiceData = [
            'tenant_id' => $this->tenant_id,
            'customer_id' => $this->customer_id,
            'document_type' => 'factura_electronica',
            'document_number' => null, // Se generará automáticamente
            'issue_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'total' => $this->total,
            'currency' => $this->currency,
            'payment_terms' => $this->payment_terms ?? 30,
            'notes' => $this->notes,
            'quote_id' => $this->id,
            ...$additionalData
        ];
        
        $invoice = TaxDocument::create($invoiceData);
        
        // Copiar items
        foreach ($this->items as $item) {
            $invoice->items()->create([
                'product_id' => $item->product_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount_percentage' => $item->discount_percentage,
                'tax_rate' => $item->tax_rate,
                'total' => $item->total,
            ]);
        }
        
        // Marcar cotización como convertida
        $this->update([
            'status' => 'converted',
            'invoice_id' => $invoice->id
        ]);
        
        return $invoice;
    }
}