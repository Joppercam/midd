<?php

namespace App\Modules\Inventory\Models;

use App\Models\Product as BaseProduct;

class Product extends BaseProduct
{
    /**
     * Obtener productos con stock bajo
     */
    public function scopeLowStock($query)
    {
        return $query->whereColumn('current_stock', '<=', 'min_stock')
            ->where('track_stock', true);
    }

    /**
     * Obtener productos sin stock
     */
    public function scopeOutOfStock($query)
    {
        return $query->where('current_stock', '<=', 0)
            ->where('track_stock', true);
    }

    /**
     * Obtener productos por categoría
     */
    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Obtener productos activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Obtener productos para venta
     */
    public function scopeForSale($query)
    {
        return $query->where('is_active', true)
            ->where('for_sale', true);
    }

    /**
     * Verificar si tiene stock bajo
     */
    public function getIsLowStockAttribute(): bool
    {
        return $this->track_stock && $this->current_stock <= $this->min_stock;
    }

    /**
     * Verificar si está sin stock
     */
    public function getIsOutOfStockAttribute(): bool
    {
        return $this->track_stock && $this->current_stock <= 0;
    }

    /**
     * Calcular valor del inventario
     */
    public function getInventoryValueAttribute(): float
    {
        return $this->current_stock * $this->cost;
    }

    /**
     * Calcular margen de ganancia
     */
    public function getProfitMarginAttribute(): float
    {
        if ($this->cost <= 0) return 0;
        
        return (($this->sale_price - $this->cost) / $this->cost) * 100;
    }

    /**
     * Obtener stock disponible para venta
     */
    public function getAvailableStockAttribute(): int
    {
        if (!$this->track_stock) return 999999;
        
        // Descontar stock reservado si existe
        $reserved = $this->reserved_stock ?? 0;
        return max(0, $this->current_stock - $reserved);
    }

    /**
     * Incrementar stock
     */
    public function increaseStock(int $quantity, string $reason = 'Ajuste manual', array $metadata = []): void
    {
        $this->increment('current_stock', $quantity);
        
        $this->movements()->create([
            'type' => 'in',
            'quantity' => $quantity,
            'unit_cost' => $this->cost,
            'total_cost' => $quantity * $this->cost,
            'reason' => $reason,
            'reference_type' => 'adjustment',
            'metadata' => $metadata,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Decrementar stock
     */
    public function decreaseStock(int $quantity, string $reason = 'Ajuste manual', array $metadata = []): void
    {
        if ($this->track_stock && $quantity > $this->current_stock) {
            throw new \Exception('Stock insuficiente. Disponible: ' . $this->current_stock);
        }

        $this->decrement('current_stock', $quantity);
        
        $this->movements()->create([
            'type' => 'out',
            'quantity' => $quantity,
            'unit_cost' => $this->cost,
            'total_cost' => $quantity * $this->cost,
            'reason' => $reason,
            'reference_type' => 'adjustment',
            'metadata' => $metadata,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Ajustar stock a cantidad específica
     */
    public function adjustStock(int $newQuantity, string $reason = 'Ajuste de inventario'): void
    {
        $difference = $newQuantity - $this->current_stock;
        
        if ($difference > 0) {
            $this->increaseStock($difference, $reason);
        } elseif ($difference < 0) {
            $this->decreaseStock(abs($difference), $reason);
        }
    }

    /**
     * Reservar stock
     */
    public function reserveStock(int $quantity, string $reference = null): void
    {
        if ($this->track_stock && $quantity > $this->available_stock) {
            throw new \Exception('Stock insuficiente para reservar');
        }

        $this->increment('reserved_stock', $quantity);
        
        // Registrar reserva
        $this->stockReservations()->create([
            'quantity' => $quantity,
            'reference' => $reference,
            'expires_at' => now()->addDays(7), // Reserva por 7 días
            'status' => 'active',
        ]);
    }

    /**
     * Liberar stock reservado
     */
    public function releaseReservedStock(int $quantity, string $reference = null): void
    {
        $this->decrement('reserved_stock', min($quantity, $this->reserved_stock));
        
        // Marcar reservas como liberadas
        $reservations = $this->stockReservations()
            ->where('status', 'active')
            ->when($reference, function ($query) use ($reference) {
                return $query->where('reference', $reference);
            })
            ->orderBy('created_at')
            ->get();
            
        $remaining = $quantity;
        foreach ($reservations as $reservation) {
            if ($remaining <= 0) break;
            
            $toRelease = min($remaining, $reservation->quantity);
            $reservation->update([
                'quantity' => $reservation->quantity - $toRelease,
                'status' => $reservation->quantity - $toRelease > 0 ? 'active' : 'released'
            ]);
            
            $remaining -= $toRelease;
        }
    }

    /**
     * Calcular punto de reorden
     */
    public function calculateReorderPoint(): int
    {
        // Fórmula: (Demanda promedio diaria * Tiempo de entrega) + Stock de seguridad
        $avgDailySales = $this->getAverageDailySales();
        $leadTimeDays = $this->lead_time_days ?? 7;
        $safetyStock = $this->safety_stock ?? 0;
        
        return ceil(($avgDailySales * $leadTimeDays) + $safetyStock);
    }

    /**
     * Obtener ventas promedio diarias
     */
    private function getAverageDailySales(): float
    {
        $sales = $this->movements()
            ->where('type', 'out')
            ->where('reason', 'sale')
            ->where('created_at', '>=', now()->subDays(30))
            ->sum('quantity');
            
        return $sales / 30;
    }

    /**
     * Verificar si necesita reabastecimiento
     */
    public function getNeedsReorderAttribute(): bool
    {
        if (!$this->track_stock) return false;
        
        $reorderPoint = $this->reorder_point ?? $this->calculateReorderPoint();
        return $this->current_stock <= $reorderPoint;
    }

    /**
     * Obtener historial de precios
     */
    public function getPriceHistory(int $days = 30): array
    {
        // TODO: Implementar tabla de historial de precios
        return [];
    }

    /**
     * Calcular rotación de inventario
     */
    public function getInventoryTurnover(int $days = 365): float
    {
        $salesQuantity = $this->movements()
            ->where('type', 'out')
            ->where('reason', 'sale')
            ->where('created_at', '>=', now()->subDays($days))
            ->sum('quantity');
            
        $avgInventory = ($this->current_stock + $this->min_stock) / 2;
        
        return $avgInventory > 0 ? $salesQuantity / $avgInventory : 0;
    }
}