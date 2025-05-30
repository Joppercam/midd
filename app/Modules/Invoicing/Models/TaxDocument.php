<?php

namespace App\Modules\Invoicing\Models;

use App\Models\TaxDocument as BaseTaxDocument;

// Alias para mantener compatibilidad con el modelo existente
class TaxDocument extends BaseTaxDocument
{
    // El modelo base ya tiene toda la funcionalidad necesaria
    // Solo agregamos métodos específicos del módulo si es necesario
    
    /**
     * Obtener facturas pendientes de envío al SII
     */
    public function scopePendingSII($query)
    {
        return $query->whereIn('document_type', ['factura_electronica', 'nota_credito', 'nota_debito'])
            ->where('sii_status', 'pending');
    }
    
    /**
     * Obtener documentos por estado SII
     */
    public function scopeBySIIStatus($query, string $status)
    {
        return $query->where('sii_status', $status);
    }
    
    /**
     * Obtener facturas vencidas
     */
    public function scopeOverdue($query)
    {
        return $query->where('document_type', 'factura_electronica')
            ->where('due_date', '<', now())
            ->where('payment_status', '!=', 'paid');
    }
    
    /**
     * Calcular días de vencimiento
     */
    public function getDaysOverdueAttribute(): int
    {
        if (!$this->due_date || $this->payment_status === 'paid') {
            return 0;
        }
        
        return max(0, now()->diffInDays($this->due_date, false));
    }
    
    /**
     * Verificar si puede ser modificada
     */
    public function getCanEditAttribute(): bool
    {
        return in_array($this->sii_status, ['draft', 'pending', 'failed']);
    }
    
    /**
     * Verificar si puede ser anulada
     */
    public function getCanVoidAttribute(): bool
    {
        return $this->sii_status === 'accepted' && $this->payment_status !== 'paid';
    }
}