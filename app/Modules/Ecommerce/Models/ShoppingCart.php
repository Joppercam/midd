<?php

namespace App\Modules\Ecommerce\Models;

use App\Models\TenantAwareModel;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShoppingCart extends TenantAwareModel
{
    protected $fillable = [
        'customer_id',
        'session_id',
        'subtotal',
        'tax_amount',
        'shipping_amount',
        'discount_amount',
        'total',
        'shipping_address',
        'billing_address',
        'coupon_code',
        'expires_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'shipping_address' => 'array',
        'billing_address' => 'array',
        'expires_at' => 'datetime',
    ];

    /**
     * Customer who owns this cart
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Items in this cart
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class, 'cart_id');
    }

    /**
     * Add item to cart
     */
    public function addItem(EcommerceProduct $product, int $quantity = 1, ?array $variantData = null): CartItem
    {
        // Check if item already exists with same variant
        $existingItem = $this->items()
            ->where('product_id', $product->product_id)
            ->where('variant_data', json_encode($variantData))
            ->first();

        if ($existingItem) {
            $existingItem->quantity += $quantity;
            $existingItem->total_price = $existingItem->quantity * $existingItem->unit_price;
            $existingItem->save();
            
            $this->calculateTotals();
            return $existingItem;
        }

        // Create new cart item
        $item = $this->items()->create([
            'product_id' => $product->product_id,
            'variant_data' => $variantData,
            'quantity' => $quantity,
            'unit_price' => $product->current_price,
            'total_price' => $quantity * $product->current_price,
        ]);

        $this->calculateTotals();
        return $item;
    }

    /**
     * Update item quantity
     */
    public function updateItemQuantity(CartItem $item, int $quantity): void
    {
        if ($quantity <= 0) {
            $item->delete();
        } else {
            $item->update([
                'quantity' => $quantity,
                'total_price' => $quantity * $item->unit_price,
            ]);
        }

        $this->calculateTotals();
    }

    /**
     * Remove item from cart
     */
    public function removeItem(CartItem $item): void
    {
        $item->delete();
        $this->calculateTotals();
    }

    /**
     * Clear all items from cart
     */
    public function clear(): void
    {
        $this->items()->delete();
        $this->calculateTotals();
    }

    /**
     * Calculate cart totals
     */
    public function calculateTotals(): void
    {
        $this->load('items');
        
        $subtotal = $this->items->sum('total_price');
        $taxAmount = $subtotal * 0.19; // 19% IVA in Chile
        $total = $subtotal + $taxAmount + $this->shipping_amount - $this->discount_amount;

        $this->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => max(0, $total), // Ensure total is not negative
        ]);
    }

    /**
     * Apply coupon
     */
    public function applyCoupon(Coupon $coupon): bool
    {
        if (!$coupon->isValidForCart($this)) {
            return false;
        }

        $discountAmount = $coupon->calculateDiscount($this);
        
        $this->update([
            'coupon_code' => $coupon->code,
            'discount_amount' => $discountAmount,
        ]);

        $this->calculateTotals();
        return true;
    }

    /**
     * Remove coupon
     */
    public function removeCoupon(): void
    {
        $this->update([
            'coupon_code' => null,
            'discount_amount' => 0,
        ]);

        $this->calculateTotals();
    }

    /**
     * Get total items count
     */
    public function getTotalItemsAttribute(): int
    {
        return $this->items->sum('quantity');
    }

    /**
     * Check if cart is empty
     */
    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    /**
     * Check if cart is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Extend cart expiration
     */
    public function extendExpiration(): void
    {
        $this->update([
            'expires_at' => now()->addDays(7), // Extend for 7 days
        ]);
    }

    /**
     * Convert cart to order
     */
    public function convertToOrder(): EcommerceOrder
    {
        $order = EcommerceOrder::create([
            'tenant_id' => $this->tenant_id,
            'order_number' => $this->generateOrderNumber(),
            'customer_id' => $this->customer_id,
            'customer_data' => $this->customer ? $this->customer->toArray() : [],
            'billing_address' => $this->billing_address,
            'shipping_address' => $this->shipping_address,
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'shipping_amount' => $this->shipping_amount,
            'discount_amount' => $this->discount_amount,
            'total' => $this->total,
        ]);

        // Copy cart items to order items
        foreach ($this->items as $item) {
            $order->items()->create([
                'product_id' => $item->product_id,
                'product_name' => $item->product->name,
                'product_sku' => $item->product->sku,
                'variant_data' => $item->variant_data,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total_price' => $item->total_price,
            ]);
        }

        return $order;
    }

    /**
     * Generate unique order number
     */
    protected function generateOrderNumber(): string
    {
        do {
            $number = 'EC-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (EcommerceOrder::where('order_number', $number)->exists());

        return $number;
    }

    /**
     * Scope for active carts (not expired)
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope for guest carts
     */
    public function scopeGuest($query)
    {
        return $query->whereNull('customer_id');
    }

    /**
     * Scope for customer carts
     */
    public function scopeCustomer($query)
    {
        return $query->whereNotNull('customer_id');
    }
}