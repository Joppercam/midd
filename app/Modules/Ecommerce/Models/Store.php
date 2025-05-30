<?php

namespace App\Modules\Ecommerce\Models;

use App\Models\TenantAwareModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Store extends TenantAwareModel
{
    protected $table = 'ecommerce_stores';

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'logo',
        'favicon',
        'contact_info',
        'social_links',
        'description',
        'meta_tags',
        'currency',
        'language',
        'type',
        'is_active',
        'maintenance_mode',
        'settings'
    ];

    protected $casts = [
        'contact_info' => 'array',
        'social_links' => 'array',
        'meta_tags' => 'array',
        'settings' => 'array',
        'is_active' => 'boolean',
        'maintenance_mode' => 'boolean'
    ];

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    public function shippingMethods(): HasMany
    {
        return $this->hasMany(ShippingMethod::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function banners(): HasMany
    {
        return $this->hasMany(Banner::class);
    }

    public function newsletterSubscriptions(): HasMany
    {
        return $this->hasMany(NewsletterSubscription::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('maintenance_mode', false);
    }

    public function scopeB2C($query)
    {
        return $query->whereIn('type', ['b2c', 'both']);
    }

    public function scopeB2B($query)
    {
        return $query->whereIn('type', ['b2b', 'both']);
    }

    public function getUrlAttribute(): string
    {
        if ($this->domain) {
            return 'https://' . $this->domain;
        }
        return route('shop.home', ['store' => $this->slug]);
    }

    public function getIsB2CAttribute(): bool
    {
        return in_array($this->type, ['b2c', 'both']);
    }

    public function getIsB2BAttribute(): bool
    {
        return in_array($this->type, ['b2b', 'both']);
    }

    public function getSetting(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->update(['settings' => $settings]);
    }

    public function generateOrderNumber(): string
    {
        $prefix = $this->getSetting('order_prefix', 'ORD');
        $lastOrder = $this->orders()->latest()->first();
        
        if ($lastOrder) {
            $lastNumber = intval(substr($lastOrder->order_number, strlen($prefix)));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }
        
        return $prefix . str_pad($nextNumber, 8, '0', STR_PAD_LEFT);
    }
}