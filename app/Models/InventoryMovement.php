<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends TenantAwareModel
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'type',
        'quantity',
        'unit_cost',
        'total_cost',
        'reference_type',
        'reference_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    const TYPES = [
        'purchase' => 'Compra',
        'sale' => 'Venta',
        'return_in' => 'Devolución Entrada',
        'return_out' => 'Devolución Salida',
        'adjustment' => 'Ajuste',
        'adjustment_in' => 'Ajuste Entrada',
        'adjustment_out' => 'Ajuste Salida',
        'transfer_in' => 'Transferencia Entrada',
        'transfer_out' => 'Transferencia Salida',
        'production' => 'Producción',
        'consumption' => 'Consumo',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getFormattedTypeAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getIsIncreaseAttribute(): bool
    {
        return in_array($this->type, ['purchase', 'return_in', 'adjustment_in', 'transfer_in', 'production']);
    }

    public function getIsDecreaseAttribute(): bool
    {
        return in_array($this->type, ['sale', 'return_out', 'adjustment_out', 'transfer_out', 'consumption']);
    }

    public function getTotalValueAttribute(): float
    {
        return abs($this->quantity) * ($this->unit_cost ?? 0);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }
}