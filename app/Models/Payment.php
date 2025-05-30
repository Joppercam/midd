<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\BelongsToTenant;
use App\Traits\Auditable;

class Payment extends Model
{
    use HasFactory, BelongsToTenant, Auditable;

    protected $fillable = [
        'number',
        'tenant_id',
        'customer_id',
        'payment_date',
        'amount',
        'payment_method',
        'reference',
        'bank',
        'description',
        'status',
        'remaining_amount',
        'metadata'
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'metadata' => 'array'
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function taxDocuments()
    {
        return $this->belongsToMany(TaxDocument::class, 'payment_allocations')
            ->withPivot('amount', 'notes')
            ->withTimestamps();
    }

    // Generar número automático
    public static function generateNumber($tenantId): string
    {
        $year = now()->year;
        $lastPayment = static::where('tenant_id', $tenantId)
            ->whereYear('created_at', $year)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        $sequence = 1;
        if ($lastPayment) {
            $lastNumber = $lastPayment->number;
            if (preg_match('/PAG-\d{4}-(\d{6})/', $lastNumber, $matches)) {
                $sequence = ((int) $matches[1]) + 1;
            }
        }

        // Generar número único
        do {
            $number = 'PAG-' . $year . '-' . str_pad($sequence, 6, '0', STR_PAD_LEFT);
            $exists = static::where('number', $number)->exists();
            if ($exists) {
                $sequence++;
            }
        } while ($exists);

        return $number;
    }

    // Monto total asignado
    public function getAllocatedAmountAttribute(): float
    {
        return $this->allocations()->sum('amount');
    }

    // Verificar si está completamente asignado
    public function getIsFullyAllocatedAttribute(): bool
    {
        return $this->remaining_amount <= 0;
    }

    // Obtener etiqueta del método de pago
    public function getPaymentMethodLabelAttribute(): string
    {
        $labels = [
            'cash' => 'Efectivo',
            'bank_transfer' => 'Transferencia Bancaria',
            'check' => 'Cheque',
            'credit_card' => 'Tarjeta de Crédito',
            'debit_card' => 'Tarjeta de Débito',
            'electronic' => 'Pago Electrónico',
            'other' => 'Otro'
        ];

        return $labels[$this->payment_method] ?? $this->payment_method;
    }

    // Obtener etiqueta del estado
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'pending' => 'Pendiente',
            'confirmed' => 'Confirmado',
            'rejected' => 'Rechazado',
            'cancelled' => 'Cancelado'
        ];

        return $labels[$this->status] ?? $this->status;
    }

    // Scope para pagos confirmados
    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    // Scope para pagos pendientes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    // Actualizar monto restante después de asignaciones
    public function updateRemainingAmount(): void
    {
        $allocatedAmount = $this->allocations()->sum('amount');
        $this->update(['remaining_amount' => $this->amount - $allocatedAmount]);
    }
}