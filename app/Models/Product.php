<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;
use App\Traits\InvalidatesCache;

class Product extends TenantAwareModel
{
    use HasFactory, Auditable, InvalidatesCache;

    protected $fillable = [
        'sku',
        'code',
        'barcode',
        'name',
        'description',
        'price',
        'cost',
        'unit',
        'weight',
        'stock_quantity',
        'min_stock_alert',
        'minimum_stock',
        'reorder_point',
        'reorder_quantity',
        'category_id',
        'is_service',
        'track_inventory',
        'allow_negative_stock',
        'location',
        'notes',
        'tax_rate',
        'last_inventory_date',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'weight' => 'decimal:3',
        'tax_rate' => 'decimal:2',
        'is_service' => 'boolean',
        'track_inventory' => 'boolean',
        'allow_negative_stock' => 'boolean',
        'stock_quantity' => 'integer',
        'min_stock_alert' => 'integer',
        'minimum_stock' => 'decimal:2',
        'reorder_point' => 'integer',
        'reorder_quantity' => 'integer',
        'last_inventory_date' => 'date',
    ];

    protected $appends = ['formatted_number'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function taxDocumentItems(): HasMany
    {
        return $this->hasMany(TaxDocumentItem::class);
    }

    public function isLowStock(): bool
    {
        return !$this->is_service && $this->stock_quantity <= $this->min_stock_alert;
    }

    public function getMarginAttribute(): float
    {
        if ($this->cost <= 0) return 0;
        return (($this->price - $this->cost) / $this->price) * 100;
    }

    public function getPriceWithTaxAttribute(): float
    {
        return $this->price * (1 + ($this->tax_rate / 100));
    }

    public function getFormattedNumberAttribute(): string
    {
        return sprintf('%s - %s', $this->code, $this->name);
    }

    public function updateStock(int $quantity, string $type, ?string $reference = null): void
    {
        if ($this->is_service) return;

        $this->inventoryMovements()->create([
            'tenant_id' => $this->tenant_id,
            'movement_type' => $type,
            'quantity' => $quantity,
            'reference_type' => $reference,
            'reference_id' => null,
            'created_by' => auth()->id(),
        ]);

        if (in_array($type, ['purchase', 'return'])) {
            $this->increment('stock_quantity', $quantity);
        } elseif (in_array($type, ['sale', 'transfer'])) {
            $this->decrement('stock_quantity', $quantity);
        } else {
            // adjustment
            $this->update(['stock_quantity' => $quantity]);
        }
    }
    
    /**
     * Boot method to handle model events
     */
    protected static function boot()
    {
        parent::boot();
        
        // Invalidate cache on create, update, or delete
        static::saved(function ($product) {
            $product->invalidateProductCache($product->tenant_id);
            $product->invalidateDashboardCache($product->tenant_id);
        });
        
        static::deleted(function ($product) {
            $product->invalidateProductCache($product->tenant_id);
            $product->invalidateDashboardCache($product->tenant_id);
        });
    }
}