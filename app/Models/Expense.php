<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToTenant;
use App\Traits\Auditable;
use Carbon\Carbon;

class Expense extends Model
{
    use HasFactory, BelongsToTenant, Auditable;

    protected $fillable = [
        'number',
        'tenant_id',
        'supplier_id',
        'document_type',
        'supplier_document_number',
        'issue_date',
        'due_date',
        'net_amount',
        'tax_amount',
        'other_taxes',
        'total_amount',
        'balance',
        'payment_method',
        'status',
        'category',
        'description',
        'reference',
        'metadata'
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'net_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'other_taxes' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'metadata' => 'array'
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    // Generar número automático
    public static function generateNumber($tenantId): string
    {
        $year = now()->year;
        $lastExpense = static::where('tenant_id', $tenantId)
            ->whereYear('created_at', $year)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        $sequence = 1;
        if ($lastExpense) {
            $lastNumber = $lastExpense->number;
            if (preg_match('/GAS-\d{4}-(\d{6})/', $lastNumber, $matches)) {
                $sequence = ((int) $matches[1]) + 1;
            }
        }

        // Generar número único
        do {
            $number = 'GAS-' . $year . '-' . str_pad($sequence, 6, '0', STR_PAD_LEFT);
            $exists = static::where('number', $number)->exists();
            if ($exists) {
                $sequence++;
            }
        } while ($exists);

        return $number;
    }

    // Obtener etiqueta del tipo de documento
    public function getDocumentTypeLabelAttribute(): string
    {
        $labels = [
            'invoice' => 'Factura de Compra',
            'receipt' => 'Boleta',
            'expense_note' => 'Nota de Gasto',
            'petty_cash' => 'Caja Chica',
            'bank_charge' => 'Cargo Bancario',
            'other' => 'Otro'
        ];

        return $labels[$this->document_type] ?? $this->document_type;
    }

    // Obtener etiqueta del estado
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'draft' => 'Borrador',
            'pending' => 'Pendiente',
            'paid' => 'Pagado',
            'cancelled' => 'Cancelado'
        ];

        return $labels[$this->status] ?? $this->status;
    }

    // Obtener etiqueta del método de pago
    public function getPaymentMethodLabelAttribute(): string
    {
        if (!$this->payment_method) return '-';

        $labels = [
            'cash' => 'Efectivo',
            'bank_transfer' => 'Transferencia',
            'check' => 'Cheque',
            'credit_card' => 'Tarjeta Crédito',
            'debit_card' => 'Tarjeta Débito',
            'electronic' => 'Electrónico',
            'credit_account' => 'Cuenta Corriente',
            'other' => 'Otro'
        ];

        return $labels[$this->payment_method] ?? $this->payment_method;
    }

    // Verificar si está vencido
    public function getIsOverdueAttribute(): bool
    {
        return $this->due_date && 
               $this->status === 'pending' && 
               $this->due_date->isPast();
    }

    // Días hasta vencimiento
    public function getDaysToExpirationAttribute(): int
    {
        if (!$this->due_date || $this->status !== 'pending') {
            return 0;
        }

        return $this->due_date->diffInDays(now(), false);
    }

    // Verificar si está pagado
    public function getIsPaidAttribute(): bool
    {
        return $this->status === 'paid' || $this->balance <= 0;
    }

    // Monto pagado
    public function getPaidAmountAttribute(): float
    {
        return $this->total_amount - $this->balance;
    }

    // Porcentaje pagado
    public function getPaidPercentageAttribute(): float
    {
        if ($this->total_amount <= 0) return 0;
        return ($this->paid_amount / $this->total_amount) * 100;
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
                    ->whereNotNull('due_date')
                    ->where('due_date', '<', now());
    }

    public function scopeBySupplier($query, $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByDocumentType($query, $type)
    {
        return $query->where('document_type', $type);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('issue_date', [$startDate, $endDate]);
    }

    // Boot method para auto-calcular totales
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($expense) {
            // Auto-calcular total si no está definido
            if (!$expense->total_amount) {
                $expense->total_amount = $expense->net_amount + $expense->tax_amount + $expense->other_taxes;
            }

            // Inicializar balance si no está definido
            if (!$expense->balance && $expense->balance !== 0) {
                $expense->balance = $expense->total_amount;
            }

            // Auto-calcular fecha de vencimiento si no está definida
            if (!$expense->due_date && $expense->supplier) {
                $days = match($expense->supplier->payment_terms) {
                    'immediate' => 0,
                    '15_days' => 15,
                    '30_days' => 30,
                    '60_days' => 60,
                    '90_days' => 90,
                    default => 30
                };
                $expense->due_date = Carbon::parse($expense->issue_date)->addDays($days);
            }
        });
    }

    // Marcar como pagado
    public function markAsPaid($paymentMethod = null, $reference = null): void
    {
        $this->update([
            'status' => 'paid',
            'balance' => 0,
            'payment_method' => $paymentMethod ?: $this->payment_method,
            'reference' => $reference ?: $this->reference
        ]);
    }

    // Registrar pago parcial
    public function registerPayment(float $amount, $paymentMethod = null, $reference = null): void
    {
        $newBalance = max(0, $this->balance - $amount);
        $status = $newBalance <= 0 ? 'paid' : 'pending';

        $this->update([
            'balance' => $newBalance,
            'status' => $status,
            'payment_method' => $paymentMethod ?: $this->payment_method,
            'reference' => $reference ?: $this->reference
        ]);
    }
}