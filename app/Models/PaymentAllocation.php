<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'tax_document_id',
        'amount',
        'notes'
    ];

    protected $casts = [
        'amount' => 'decimal:2'
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function taxDocument(): BelongsTo
    {
        return $this->belongsTo(TaxDocument::class);
    }

    // Boot method para actualizar saldos cuando se crea/modifica/elimina una asignación
    protected static function boot()
    {
        parent::boot();

        static::created(function ($allocation) {
            $allocation->updateDocumentBalance();
            $allocation->payment->updateRemainingAmount();
        });

        static::updated(function ($allocation) {
            $allocation->updateDocumentBalance();
            $allocation->payment->updateRemainingAmount();
        });

        static::deleted(function ($allocation) {
            $allocation->updateDocumentBalance();
            $allocation->payment->updateRemainingAmount();
        });
    }

    // Actualizar saldo del documento
    private function updateDocumentBalance(): void
    {
        $document = $this->taxDocument;
        
        // Calcular total de pagos para este documento
        $totalPaid = PaymentAllocation::where('tax_document_id', $document->id)
            ->whereHas('payment', function ($query) {
                $query->where('status', 'confirmed');
            })
            ->sum('amount');

        // Actualizar saldo del documento
        $balance = $document->total_amount - $totalPaid;
        
        // Determinar nuevo estado basado en el saldo
        $newStatus = $document->status;
        if ($balance <= 0 && in_array($document->status, ['sent', 'accepted'])) {
            $newStatus = 'paid';
        } elseif ($balance > 0 && $document->status === 'paid') {
            $newStatus = 'accepted'; // Volver a estado anterior si se elimina un pago
        }

        $document->update([
            'balance' => max(0, $balance),
            'status' => $newStatus
        ]);
    }
}