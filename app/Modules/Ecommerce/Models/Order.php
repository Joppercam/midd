<?php

namespace App\Modules\Ecommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $table = 'ecommerce_orders';

    protected $fillable = [
        'store_id',
        'customer_id',
        'order_number',
        'status',
        'payment_status',
        'fulfillment_status',
        'subtotal',
        'tax_amount',
        'shipping_amount',
        'discount_amount',
        'total',
        'currency',
        'customer_email',
        'customer_phone',
        'billing_address',
        'shipping_address',
        'payment_method',
        'payment_details',
        'paid_at',
        'shipping_method',
        'tracking_number',
        'shipped_at',
        'delivered_at',
        'notes',
        'internal_notes',
        'metadata',
        'source'
    ];

    protected $casts = [
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'payment_details' => 'array',
        'metadata' => 'array',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime'
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeCompleted($query)
    {
        return $query->whereIn('status', ['delivered', 'shipped']);
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', 'pending');
    }

    public function getIsPaidAttribute(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function getIsShippedAttribute(): bool
    {
        return in_array($this->fulfillment_status, ['partially_fulfilled', 'fulfilled']);
    }

    public function getIsDeliveredAttribute(): bool
    {
        return $this->status === 'delivered';
    }

    public function getIsCancellableAttribute(): bool
    {
        return in_array($this->status, ['pending', 'processing']) 
            && $this->fulfillment_status === 'unfulfilled';
    }

    public function getIsRefundableAttribute(): bool
    {
        return $this->payment_status === 'paid' 
            && !in_array($this->status, ['cancelled', 'refunded']);
    }

    public function getFormattedTotalAttribute(): string
    {
        return '$' . number_format($this->total, 0, ',', '.');
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'yellow',
            'processing' => 'blue',
            'shipped' => 'indigo',
            'delivered' => 'green',
            'cancelled' => 'red',
            'refunded' => 'gray',
            default => 'gray'
        };
    }

    public function getPaymentStatusColorAttribute(): string
    {
        return match($this->payment_status) {
            'pending' => 'yellow',
            'paid' => 'green',
            'failed' => 'red',
            'refunded' => 'gray',
            default => 'gray'
        };
    }

    public function markAsPaid(array $paymentDetails = []): void
    {
        $this->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
            'payment_details' => array_merge($this->payment_details ?? [], $paymentDetails)
        ]);

        // Actualizar estado si estaba pendiente
        if ($this->status === 'pending') {
            $this->update(['status' => 'processing']);
        }
    }

    public function markAsShipped(string $trackingNumber = null): void
    {
        $this->update([
            'status' => 'shipped',
            'fulfillment_status' => 'fulfilled',
            'shipped_at' => now(),
            'tracking_number' => $trackingNumber
        ]);

        // Marcar todos los items como fulfilled
        $this->items()->update(['fulfillment_status' => 'fulfilled']);
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now()
        ]);
    }

    public function cancel(string $reason = null): void
    {
        if (!$this->is_cancellable) {
            throw new \Exception('Esta orden no puede ser cancelada');
        }

        $this->update([
            'status' => 'cancelled',
            'internal_notes' => $this->internal_notes . "\nCancelada: " . $reason
        ]);

        // Restaurar stock
        foreach ($this->items as $item) {
            $item->product->updateStock($item->quantity, 'increase');
        }
    }

    public function refund(float $amount = null): void
    {
        if (!$this->is_refundable) {
            throw new \Exception('Esta orden no puede ser reembolsada');
        }

        $refundAmount = $amount ?? $this->total;

        $this->update([
            'status' => 'refunded',
            'payment_status' => 'refunded',
            'internal_notes' => $this->internal_notes . "\nReembolsado: $" . number_format($refundAmount, 0, ',', '.')
        ]);

        // Restaurar stock si no estaba enviado
        if (!$this->is_shipped) {
            foreach ($this->items as $item) {
                $item->product->updateStock($item->quantity, 'increase');
            }
        }
    }

    public function calculateTotals(): void
    {
        $subtotal = $this->items->sum('total');
        
        $this->update([
            'subtotal' => $subtotal,
            'total' => $subtotal + $this->tax_amount + $this->shipping_amount - $this->discount_amount
        ]);
    }

    public function addNote(string $note, bool $isInternal = false): void
    {
        $field = $isInternal ? 'internal_notes' : 'notes';
        $currentNotes = $this->$field;
        
        $this->update([
            $field => $currentNotes . "\n[" . now()->format('d/m/Y H:i') . "] " . $note
        ]);
    }

    public function generateInvoice(): void
    {
        // TODO: Integrar con módulo de facturación
        // Crear TaxDocument desde la orden
    }

    public function sendConfirmationEmail(): void
    {
        // TODO: Implementar envío de email de confirmación
    }

    public function getTimelineAttribute(): array
    {
        $timeline = [
            ['event' => 'Orden creada', 'date' => $this->created_at, 'status' => 'completed']
        ];

        if ($this->paid_at) {
            $timeline[] = ['event' => 'Pago recibido', 'date' => $this->paid_at, 'status' => 'completed'];
        }

        if ($this->shipped_at) {
            $timeline[] = ['event' => 'Orden enviada', 'date' => $this->shipped_at, 'status' => 'completed'];
        }

        if ($this->delivered_at) {
            $timeline[] = ['event' => 'Orden entregada', 'date' => $this->delivered_at, 'status' => 'completed'];
        }

        if ($this->status === 'cancelled') {
            $timeline[] = ['event' => 'Orden cancelada', 'date' => $this->updated_at, 'status' => 'cancelled'];
        }

        return $timeline;
    }
}