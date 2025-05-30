<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\InvalidatesCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasFactory, BelongsToTenant, InvalidatesCache;

    protected $fillable = [
        'tenant_id',
        'supplier_id',
        'user_id',
        'order_number',
        'order_date',
        'expected_date',
        'status',
        'reference',
        'notes',
        'terms',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total',
        'currency',
        'exchange_rate',
        'shipping_method',
        'shipping_address',
        'billing_address',
        'sent_at',
        'confirmed_at',
        'completed_at',
        'cancelled_at',
        'cancelled_reason',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_date' => 'date',
        'sent_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
    ];

    protected $cacheKeys = [
        'purchase_orders_list',
        'purchase_orders_stats',
        'supplier_purchase_orders',
    ];

    protected static function booted()
    {
        static::creating(function ($order) {
            if (!$order->order_number) {
                $order->order_number = static::generateOrderNumber($order->tenant_id);
            }
        });

        static::updating(function ($order) {
            // Actualizar fechas según cambios de estado
            if ($order->isDirty('status')) {
                switch ($order->status) {
                    case 'sent':
                        $order->sent_at = now();
                        break;
                    case 'confirmed':
                        $order->confirmed_at = now();
                        break;
                    case 'completed':
                        $order->completed_at = now();
                        break;
                    case 'cancelled':
                        $order->cancelled_at = now();
                        break;
                }
            }
        });
    }

    public static function generateOrderNumber($tenantId)
    {
        $prefix = 'OC';
        $year = date('Y');
        $lastOrder = static::where('tenant_id', $tenantId)
            ->whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastOrder && preg_match('/OC-\d{4}-(\d+)/', $lastOrder->order_number, $matches)) {
            $sequence = intval($matches[1]) + 1;
        } else {
            $sequence = 1;
        }

        return sprintf('%s-%s-%06d', $prefix, $year, $sequence);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('position');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(PurchaseOrderReceipt::class);
    }

    public function calculateTotals()
    {
        $subtotal = 0;
        $taxAmount = 0;
        $discountAmount = 0;

        foreach ($this->items as $item) {
            $subtotal += $item->subtotal;
            $taxAmount += $item->tax_amount;
            $discountAmount += $item->discount_amount;
        }

        $this->subtotal = $subtotal;
        $this->tax_amount = $taxAmount;
        $this->discount_amount = $discountAmount;
        $this->total = $subtotal + $taxAmount - $discountAmount;
        
        return $this;
    }

    public function updateReceivedQuantities()
    {
        $allReceived = true;
        $someReceived = false;

        foreach ($this->items as $item) {
            $receivedQty = $item->receiptItems()->sum('quantity_received');
            $item->quantity_received = $receivedQty;
            $item->save();

            if ($receivedQty < $item->quantity) {
                $allReceived = false;
            }
            if ($receivedQty > 0) {
                $someReceived = true;
            }
        }

        if ($allReceived && $this->status !== 'completed') {
            $this->status = 'completed';
            $this->save();
        } elseif ($someReceived && !$allReceived && $this->status !== 'partial') {
            $this->status = 'partial';
            $this->save();
        }
    }

    public function canBeSent(): bool
    {
        return in_array($this->status, ['draft']);
    }

    public function canBeConfirmed(): bool
    {
        return in_array($this->status, ['sent']);
    }

    public function canBeReceived(): bool
    {
        return in_array($this->status, ['confirmed', 'partial']);
    }

    public function canBeCancelled(): bool
    {
        return !in_array($this->status, ['completed', 'cancelled']);
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'draft' => 'gray',
            'sent' => 'blue',
            'confirmed' => 'yellow',
            'partial' => 'orange',
            'completed' => 'green',
            'cancelled' => 'red',
            default => 'gray',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'draft' => 'Borrador',
            'sent' => 'Enviada',
            'confirmed' => 'Confirmada',
            'partial' => 'Recepción Parcial',
            'completed' => 'Completada',
            'cancelled' => 'Cancelada',
            default => 'Desconocido',
        };
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('order_number', 'like', "%{$search}%")
              ->orWhere('reference', 'like', "%{$search}%")
              ->orWhereHas('supplier', function ($q) use ($search) {
                  $q->where('name', 'like', "%{$search}%");
              });
        });
    }

    public function scopeStatus($query, $status)
    {
        if ($status && $status !== 'all') {
            return $query->where('status', $status);
        }
        return $query;
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        if ($startDate) {
            $query->whereDate('order_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('order_date', '<=', $endDate);
        }
        return $query;
    }
}