<?php

namespace App\Modules\Ecommerce\Models;

use App\Models\TenantAwareModel;
use App\Models\Product;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EcommerceProduct extends TenantAwareModel
{
    protected $fillable = [
        'product_id',
        'category_id',
        'is_published',
        'is_featured',
        'online_price',
        'sale_price',
        'sale_start_date',
        'sale_end_date',
        'images',
        'variants',
        'attributes',
        'short_description',
        'full_description',
        'seo_meta',
        'views_count',
        'rating_average',
        'reviews_count',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'is_featured' => 'boolean',
        'online_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'sale_start_date' => 'datetime',
        'sale_end_date' => 'datetime',
        'images' => 'array',
        'variants' => 'array',
        'attributes' => 'array',
        'seo_meta' => 'array',
        'views_count' => 'integer',
        'rating_average' => 'decimal:2',
        'reviews_count' => 'integer',
    ];

    /**
     * Related product from main inventory
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * E-commerce category
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(EcommerceCategory::class, 'category_id');
    }

    /**
     * Product reviews
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class, 'product_id', 'product_id')
            ->where('is_approved', true)
            ->latest();
    }

    /**
     * Cart items for this product
     */
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class, 'product_id', 'product_id');
    }

    /**
     * Get current effective price
     */
    public function getCurrentPriceAttribute(): float
    {
        if ($this->isOnSale()) {
            return $this->sale_price;
        }

        return $this->online_price ?? $this->product->price;
    }

    /**
     * Check if product is currently on sale
     */
    public function isOnSale(): bool
    {
        if (!$this->sale_price || !$this->sale_start_date) {
            return false;
        }

        $now = now();
        $start = $this->sale_start_date;
        $end = $this->sale_end_date;

        return $now >= $start && (!$end || $now <= $end);
    }

    /**
     * Get discount percentage
     */
    public function getDiscountPercentageAttribute(): ?float
    {
        if (!$this->isOnSale()) {
            return null;
        }

        $originalPrice = $this->online_price ?? $this->product->price;
        if ($originalPrice <= 0) {
            return null;
        }

        return round((($originalPrice - $this->sale_price) / $originalPrice) * 100, 1);
    }

    /**
     * Get primary image URL
     */
    public function getPrimaryImageUrlAttribute(): ?string
    {
        if (!$this->images || empty($this->images)) {
            return null;
        }

        $firstImage = $this->images[0];
        return asset('storage/' . $firstImage);
    }

    /**
     * Get all image URLs
     */
    public function getImageUrlsAttribute(): array
    {
        if (!$this->images) {
            return [];
        }

        return array_map(function ($image) {
            return asset('storage/' . $image);
        }, $this->images);
    }

    /**
     * Check if product is in stock
     */
    public function isInStock(): bool
    {
        return $this->product && $this->product->quantity > 0;
    }

    /**
     * Get available quantity
     */
    public function getAvailableQuantityAttribute(): int
    {
        return $this->product ? $this->product->quantity : 0;
    }

    /**
     * Increment views count
     */
    public function incrementViews(): void
    {
        $this->increment('views_count');
    }

    /**
     * Update rating average
     */
    public function updateRating(): void
    {
        $reviews = $this->reviews()->selectRaw('AVG(rating) as avg_rating, COUNT(*) as count')->first();
        
        $this->update([
            'rating_average' => $reviews->avg_rating ? round($reviews->avg_rating, 2) : 0,
            'reviews_count' => $reviews->count ?? 0,
        ]);
    }

    /**
     * Scope for published products
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope for featured products
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true)->where('is_published', true);
    }

    /**
     * Scope for products on sale
     */
    public function scopeOnSale($query)
    {
        return $query->whereNotNull('sale_price')
            ->whereNotNull('sale_start_date')
            ->where('sale_start_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('sale_end_date')
                  ->orWhere('sale_end_date', '>=', now());
            });
    }

    /**
     * Scope for products in stock
     */
    public function scopeInStock($query)
    {
        return $query->whereHas('product', function ($q) {
            $q->where('quantity', '>', 0);
        });
    }

    /**
     * Scope for search
     */
    public function scopeSearch($query, $search)
    {
        return $query->whereHas('product', function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhere('sku', 'like', "%{$search}%");
        })->orWhere('short_description', 'like', "%{$search}%")
          ->orWhere('full_description', 'like', "%{$search}%");
    }
}