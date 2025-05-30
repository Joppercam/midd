<?php

namespace App\Modules\Ecommerce\Models;

use App\Models\TenantAwareModel;
use App\Models\Product;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends TenantAwareModel
{
    protected $fillable = [
        'cart_id',
        'product_id',
        'variant_data',
        'quantity',
        'unit_price',
        'total_price',
    ];

    protected $casts = [
        'variant_data' => 'array',
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    /**
     * The cart this item belongs to
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(ShoppingCart::class, 'cart_id');
    }

    /**
     * The product this item represents
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Update the total price when quantity or unit price changes
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($cartItem) {
            $cartItem->total_price = $cartItem->quantity * $cartItem->unit_price;
        });

        static::saved(function ($cartItem) {
            // Update cart totals when item is saved
            $cartItem->cart->calculateTotals();
        });

        static::deleted(function ($cartItem) {
            // Update cart totals when item is deleted
            $cartItem->cart->calculateTotals();
        });
    }

    /**
     * Get the formatted variant display
     */
    public function getVariantDisplayAttribute(): ?string
    {
        if (!$this->variant_data || empty($this->variant_data)) {
            return null;
        }

        $parts = [];
        foreach ($this->variant_data as $key => $value) {
            $parts[] = ucfirst($key) . ': ' . $value;
        }

        return implode(', ', $parts);
    }

    /**
     * Get the line total with currency formatting
     */
    public function getFormattedTotalAttribute(): string
    {
        return number_format($this->total_price, 0, ',', '.') . ' CLP';
    }

    /**
     * Check if this item can be increased by the given quantity
     */
    public function canIncreaseQuantity(int $amount = 1): bool
    {
        $newQuantity = $this->quantity + $amount;
        return $this->product && $this->product->quantity >= $newQuantity;
    }

    /**
     * Get the maximum quantity that can be added
     */
    public function getMaxQuantityAttribute(): int
    {
        return $this->product ? $this->product->quantity : 0;
    }
}