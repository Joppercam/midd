<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends TenantAwareModel
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'name',
        'description',
        'price',
        'cost',
        'stock_quantity',
        'min_stock_alert',
        'category_id',
        'is_service',
        'tax_rate',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'is_service' => 'boolean',
        'stock_quantity' => 'integer',
        'min_stock_alert' => 'integer',
    ];

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

    public function updateStock(int $quantity, string $type, string $reference = null): void
    {
        if ($this->is_service) return;

        $movement = $this->inventoryMovements()->create([
            'tenant_id' => $this->tenant_id,
            'movement_type' => $type,
            'quantity' => $quantity,
            'reference_type' => $reference,
            'reference_id' => null,
            'created_by' => auth()->id() ?? null,
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
}