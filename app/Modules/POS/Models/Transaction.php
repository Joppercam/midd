<?php

namespace App\Modules\POS\Models;

use App\Models\Customer;
use App\Models\TaxDocument;
use App\Models\TenantAwareModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends TenantAwareModel
{
    protected $table = 'pos_transactions';

    protected $fillable = [
        'terminal_id',
        'cash_session_id',
        'user_id',
        'customer_id',
        'transaction_number',
        'type',
        'status',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total',
        'amount_paid',
        'change_amount',
        'invoice_id',
        'original_transaction_id',
        'discount_details',
        'notes',
        'metadata',
        'is_synced',
        'synced_at'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'discount_details' => 'array',
        'metadata' => 'array',
        'is_synced' => 'boolean',
        'synced_at' => 'datetime'
    ];

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(TaxDocument::class, 'invoice_id');
    }

    public function originalTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'original_transaction_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Transaction::class, 'original_transaction_id')
            ->where('type', 'refund');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(TransactionPayment::class);
    }

    public function scopeSales($query)
    {
        return $query->where('type', 'sale');
    }

    public function scopeRefunds($query)
    {
        return $query->where('type', 'refund');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeVoided($query)
    {
        return $query->where('status', 'voided');
    }

    public function scopeSynced($query)
    {
        return $query->where('is_synced', true);
    }

    public function scopeUnsynced($query)
    {
        return $query->where('is_synced', false);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function getIsVoidableAttribute(): bool
    {
        return $this->status === 'completed' 
            && $this->type === 'sale'
            && $this->created_at->isToday()
            && !$this->refunds()->exists();
    }

    public function getIsRefundableAttribute(): bool
    {
        return $this->status === 'completed'
            && $this->type === 'sale'
            && $this->total > $this->getRefundedAmount();
    }

    public function getRefundedAmountAttribute(): float
    {
        return $this->refunds()
            ->where('status', 'completed')
            ->sum('total');
    }

    public function getRefundableAmountAttribute(): float
    {
        return $this->total - $this->refunded_amount;
    }

    public function getFormattedTotalAttribute(): string
    {
        return '$' . number_format($this->total, 0, ',', '.');
    }

    public function getPaymentSummaryAttribute(): array
    {
        return $this->payments->groupBy('paymentMethod.name')
            ->map(function ($payments) {
                return $payments->sum('amount');
            })
            ->toArray();
    }

    public function void(string $reason = null): void
    {
        if (!$this->is_voidable) {
            throw new \Exception('Esta transacción no puede ser anulada');
        }

        $this->update([
            'status' => 'voided',
            'notes' => $this->notes . "\nAnulada: " . $reason
        ]);

        // Restaurar inventario
        foreach ($this->items as $item) {
            $item->product->increment('current_stock', $item->quantity);
        }

        // Anular factura si existe
        if ($this->invoice) {
            // TODO: Implementar anulación de factura
        }
    }

    public function refund(array $items = null, float $amount = null): Transaction
    {
        if (!$this->is_refundable) {
            throw new \Exception('Esta transacción no puede ser devuelta');
        }

        // Si no se especifican items, devolver todo
        if (!$items) {
            $items = $this->items->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'amount' => $item->total
                ];
            })->toArray();
        }

        // Crear transacción de devolución
        $refund = Transaction::create([
            'tenant_id' => $this->tenant_id,
            'terminal_id' => $this->terminal_id,
            'cash_session_id' => CashSession::current($this->terminal_id)->id,
            'user_id' => auth()->id(),
            'customer_id' => $this->customer_id,
            'transaction_number' => $this->terminal->generateTransactionNumber(),
            'type' => 'refund',
            'status' => 'completed',
            'original_transaction_id' => $this->id,
            'subtotal' => -abs($amount ?? $this->subtotal),
            'tax_amount' => -abs($amount ? ($amount * 0.19 / 1.19) : $this->tax_amount),
            'total' => -abs($amount ?? $this->total),
            'amount_paid' => -abs($amount ?? $this->total),
        ]);

        // Crear items de devolución
        foreach ($items as $itemData) {
            $originalItem = $this->items->where('product_id', $itemData['product_id'])->first();
            
            $refund->items()->create([
                'product_id' => $itemData['product_id'],
                'product_name' => $originalItem->product_name,
                'product_sku' => $originalItem->product_sku,
                'quantity' => -abs($itemData['quantity']),
                'unit_price' => $originalItem->unit_price,
                'total' => -abs($itemData['amount'] ?? ($originalItem->unit_price * $itemData['quantity'])),
            ]);

            // Restaurar inventario
            $originalItem->product->increment('current_stock', $itemData['quantity']);
        }

        // Copiar métodos de pago como negativos
        foreach ($this->payments as $payment) {
            $refund->payments()->create([
                'payment_method_id' => $payment->payment_method_id,
                'amount' => -abs($payment->amount),
                'status' => 'completed'
            ]);
        }

        return $refund;
    }

    public function generateInvoice(): void
    {
        if ($this->invoice_id) {
            throw new \Exception('Esta transacción ya tiene una factura asociada');
        }

        // TODO: Implementar generación de factura
        // Integrar con módulo de facturación
    }

    public function printReceipt(Printer $printer = null): void
    {
        if (!$printer) {
            $printer = $this->terminal->getDefaultPrinter('receipt');
        }

        if (!$printer) {
            throw new \Exception('No hay impresora configurada');
        }

        // TODO: Implementar impresión de recibo
    }

    public function updateLoyaltyPoints(): void
    {
        if (!$this->customer || $this->type !== 'sale') {
            return;
        }

        $loyaltyCard = $this->customer->loyaltyCard;
        if (!$loyaltyCard) {
            return;
        }

        // Calcular puntos (1 punto por cada $1000)
        $points = floor($this->total / 1000);
        
        if ($points > 0) {
            $loyaltyCard->addPoints($points, 'Compra #' . $this->transaction_number, $this);
        }
    }
}