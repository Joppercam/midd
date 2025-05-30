<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'description',
        'sku',
        'quantity',
        'quantity_received',
        'unit',
        'unit_price',
        'discount_percent',
        'discount_amount',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'total',
        'position',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'quantity_received' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'position' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($item) {
            $item->calculateAmounts();
        });

        static::updating(function ($item) {
            $item->calculateAmounts();
        });

        static::saved(function ($item) {
            $item->purchaseOrder->calculateTotals()->save();
        });

        static::deleted(function ($item) {
            $item->purchaseOrder->calculateTotals()->save();
        });
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function receiptItems()
    {
        return $this->hasMany(PurchaseOrderReceiptItem::class);
    }

    public function calculateAmounts()
    {
        // Calcular subtotal
        $this->subtotal = $this->quantity * $this->unit_price;
        
        // Calcular descuento
        if ($this->discount_percent > 0) {
            $this->discount_amount = $this->subtotal * ($this->discount_percent / 100);
        }
        
        // Subtotal después del descuento
        $subtotalAfterDiscount = $this->subtotal - $this->discount_amount;
        
        // Calcular impuesto
        $this->tax_amount = $subtotalAfterDiscount * ($this->tax_rate / 100);
        
        // Total
        $this->total = $subtotalAfterDiscount + $this->tax_amount;
    }

    public function getPendingQuantityAttribute(): float
    {
        return max(0, $this->quantity - $this->quantity_received);
    }

    public function getIsFullyReceivedAttribute(): bool
    {
        return $this->quantity_received >= $this->quantity;
    }

    public function getReceivedPercentageAttribute(): float
    {
        if ($this->quantity == 0) {
            return 0;
        }
        return min(100, ($this->quantity_received / $this->quantity) * 100);
    }
}