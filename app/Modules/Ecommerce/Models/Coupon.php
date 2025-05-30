<?php

namespace App\Modules\Ecommerce\Models;

use App\Models\TenantAwareModel;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Coupon extends TenantAwareModel
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'minimum_amount',
        'maximum_discount',
        'usage_limit',
        'usage_limit_per_customer',
        'used_count',
        'is_active',
        'starts_at',
        'expires_at',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'minimum_amount' => 'decimal:2',
        'maximum_discount' => 'decimal:2',
        'usage_limit' => 'integer',
        'usage_limit_per_customer' => 'integer',
        'used_count' => 'integer',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    const TYPE_PERCENTAGE = 'percentage';
    const TYPE_FIXED_AMOUNT = 'fixed_amount';
    const TYPE_FREE_SHIPPING = 'free_shipping';

    /**
     * Coupon usages
     */
    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    /**
     * Check if coupon is valid for the given cart
     */
    public function isValidForCart(ShoppingCart $cart): bool
    {
        // Check if coupon is active
        if (!$this->is_active) {
            return false;
        }

        // Check date range
        if (!$this->isWithinDateRange()) {
            return false;
        }

        // Check usage limits
        if (!$this->isWithinUsageLimits()) {
            return false;
        }

        // Check per-customer usage limit
        if ($cart->customer_id && !$this->isWithinCustomerUsageLimit($cart->customer_id)) {
            return false;
        }

        // Check minimum amount
        if ($this->minimum_amount && $cart->subtotal < $this->minimum_amount) {
            return false;
        }

        return true;
    }

    /**
     * Check if coupon is within valid date range
     */
    public function isWithinDateRange(): bool
    {
        $now = now();

        if ($this->starts_at && $now < $this->starts_at) {
            return false;
        }

        if ($this->expires_at && $now > $this->expires_at) {
            return false;
        }

        return true;
    }

    /**
     * Check if coupon is within usage limits
     */
    public function isWithinUsageLimits(): bool
    {
        if ($this->usage_limit && $this->used_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    /**
     * Check if coupon is within customer usage limit
     */
    public function isWithinCustomerUsageLimit(string $customerId): bool
    {
        if (!$this->usage_limit_per_customer) {
            return true;
        }

        $customerUsageCount = $this->usages()
            ->where('customer_id', $customerId)
            ->count();

        return $customerUsageCount < $this->usage_limit_per_customer;
    }

    /**
     * Calculate discount amount for the given cart
     */
    public function calculateDiscount(ShoppingCart $cart): float
    {
        switch ($this->type) {
            case self::TYPE_PERCENTAGE:
                $discount = $cart->subtotal * ($this->value / 100);
                break;
            case self::TYPE_FIXED_AMOUNT:
                $discount = $this->value;
                break;
            case self::TYPE_FREE_SHIPPING:
                $discount = $cart->shipping_amount;
                break;
            default:
                $discount = 0;
        }

        // Apply maximum discount limit
        if ($this->maximum_discount && $discount > $this->maximum_discount) {
            $discount = $this->maximum_discount;
        }

        // Ensure discount doesn't exceed cart subtotal
        return min($discount, $cart->subtotal);
    }

    /**
     * Apply coupon to an order (record usage)
     */
    public function applyToOrder(EcommerceOrder $order): CouponUsage
    {
        $usage = $this->usages()->create([
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'discount_amount' => $order->discount_amount,
        ]);

        $this->increment('used_count');

        return $usage;
    }

    /**
     * Get discount display text
     */
    public function getDiscountDisplayAttribute(): string
    {
        switch ($this->type) {
            case self::TYPE_PERCENTAGE:
                return "{$this->value}% off";
            case self::TYPE_FIXED_AMOUNT:
                return "$" . number_format($this->value, 0) . " off";
            case self::TYPE_FREE_SHIPPING:
                return "Free shipping";
            default:
                return "Discount";
        }
    }

    /**
     * Get status display
     */
    public function getStatusDisplayAttribute(): string
    {
        if (!$this->is_active) {
            return 'Inactive';
        }

        $now = now();

        if ($this->starts_at && $now < $this->starts_at) {
            return 'Scheduled';
        }

        if ($this->expires_at && $now > $this->expires_at) {
            return 'Expired';
        }

        if ($this->usage_limit && $this->used_count >= $this->usage_limit) {
            return 'Used up';
        }

        return 'Active';
    }

    /**
     * Get days until expiration
     */
    public function getDaysUntilExpirationAttribute(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }

        return now()->diffInDays($this->expires_at, false);
    }

    /**
     * Scope for active coupons
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for valid coupons (active and within date range)
     */
    public function scopeValid($query)
    {
        $now = now();
        
        return $query->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')
                  ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>=', $now);
            })
            ->where(function ($q) {
                $q->whereNull('usage_limit')
                  ->orWhereRaw('used_count < usage_limit');
            });
    }

    /**
     * Scope for expired coupons
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    /**
     * Scope by code
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', strtoupper($code));
    }

    /**
     * Auto-uppercase coupon codes
     */
    public function setCodeAttribute($value)
    {
        $this->attributes['code'] = strtoupper($value);
    }
}