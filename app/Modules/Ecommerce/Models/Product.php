<?php

namespace App\Modules\Ecommerce\Models;

use App\Models\Product as InventoryProduct;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $table = 'ecommerce_products';

    protected $fillable = [
        'store_id',
        'product_id',
        'name',
        'slug',
        'short_description',
        'description',
        'price',
        'compare_price',
        'cost',
        'track_inventory',
        'stock_quantity',
        'allow_backorder',
        'weight',
        'dimensions',
        'attributes',
        'is_active',
        'is_featured',
        'views',
        'rating',
        'rating_count',
        'meta_tags'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_price' => 'decimal:2',
        'cost' => 'decimal:2',
        'weight' => 'decimal:3',
        'dimensions' => 'array',
        'attributes' => 'array',
        'meta_tags' => 'array',
        'track_inventory' => 'boolean',
        'allow_backorder' => 'boolean',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'rating' => 'decimal:2'
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function inventoryProduct(): BelongsTo
    {
        return $this->belongsTo(InventoryProduct::class, 'product_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'ecommerce_product_categories')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function primaryCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'primary_category_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeInStock($query)
    {
        return $query->where(function ($q) {
            $q->where('track_inventory', false)
                ->orWhere(function ($q2) {
                    $q2->where('track_inventory', true)
                        ->where(function ($q3) {
                            $q3->where('stock_quantity', '>', 0)
                                ->orWhere('allow_backorder', true);
                        });
                });
        });
    }

    public function scopeOnSale($query)
    {
        return $query->whereNotNull('compare_price')
            ->whereColumn('price', '<', 'compare_price');
    }

    public function getIsOnSaleAttribute(): bool
    {
        return $this->compare_price && $this->price < $this->compare_price;
    }

    public function getDiscountPercentageAttribute(): ?int
    {
        if (!$this->is_on_sale) return null;
        
        return round((($this->compare_price - $this->price) / $this->compare_price) * 100);
    }

    public function getIsInStockAttribute(): bool
    {
        if (!$this->track_inventory) return true;
        
        return $this->stock_quantity > 0 || $this->allow_backorder;
    }

    public function getAvailableQuantityAttribute(): int
    {
        if (!$this->track_inventory) return 999999;
        
        return max(0, $this->stock_quantity);
    }

    public function getPrimaryImageAttribute(): ?ProductImage
    {
        return $this->images->where('is_primary', true)->first() 
            ?? $this->images->first();
    }

    public function getFormattedPriceAttribute(): string
    {
        return '$' . number_format($this->price, 0, ',', '.');
    }

    public function getFormattedComparePriceAttribute(): ?string
    {
        if (!$this->compare_price) return null;
        
        return '$' . number_format($this->compare_price, 0, ',', '.');
    }

    public function incrementViews(): void
    {
        $this->increment('views');
    }

    public function updateRating(): void
    {
        $reviews = $this->reviews()->where('is_approved', true);
        
        $this->update([
            'rating' => $reviews->avg('rating') ?? 0,
            'rating_count' => $reviews->count()
        ]);
    }

    public function updateStock(int $quantity, string $operation = 'decrease'): void
    {
        if (!$this->track_inventory) return;
        
        if ($operation === 'decrease') {
            $this->decrement('stock_quantity', $quantity);
        } else {
            $this->increment('stock_quantity', $quantity);
        }
    }

    public function canAddToCart(int $quantity = 1): bool
    {
        if (!$this->is_active) return false;
        
        if (!$this->track_inventory) return true;
        
        return $this->stock_quantity >= $quantity || $this->allow_backorder;
    }

    public function getRelatedProducts(int $limit = 4)
    {
        $categoryIds = $this->categories->pluck('id');
        
        return static::where('store_id', $this->store_id)
            ->where('id', '!=', $this->id)
            ->where('is_active', true)
            ->whereHas('categories', function ($query) use ($categoryIds) {
                $query->whereIn('ecommerce_categories.id', $categoryIds);
            })
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }
}