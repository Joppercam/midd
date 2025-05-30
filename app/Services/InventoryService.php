<?php

namespace App\Services;

use App\Models\Product;
use App\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Actualizar stock de un producto
     */
    public function updateStock(Product $product, int $quantity, string $type, array $options = []): InventoryMovement
    {
        if ($product->is_service) {
            throw new \Exception('No se puede actualizar stock de servicios.');
        }

        DB::beginTransaction();
        try {
            // Crear movimiento de inventario
            $movement = $product->inventoryMovements()->create([
                'tenant_id' => $product->tenant_id,
                'type' => $type,
                'quantity' => $quantity,
                'unit_cost' => $options['unit_cost'] ?? $product->cost,
                'total_cost' => ($options['unit_cost'] ?? $product->cost) * abs($quantity),
                'reference_type' => $options['reference_type'] ?? null,
                'reference_id' => $options['reference_id'] ?? null,
                'notes' => $options['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // Actualizar stock según el tipo de movimiento
            $newStock = match($type) {
                'purchase', 'return_in', 'adjustment_in' => $product->stock_quantity + abs($quantity),
                'sale', 'return_out', 'adjustment_out', 'transfer_out' => $product->stock_quantity - abs($quantity),
                'adjustment' => $quantity, // Ajuste directo al stock
                default => $product->stock_quantity
            };

            // Validar stock negativo si no está permitido
            if (!$product->allow_negative_stock && $newStock < 0) {
                throw new \Exception("Stock insuficiente. Stock actual: {$product->stock_quantity}, Cantidad requerida: " . abs($quantity));
            }

            // Actualizar costo promedio en compras
            if (in_array($type, ['purchase', 'adjustment_in']) && isset($options['unit_cost'])) {
                $newCost = $this->calculateAverageCost($product, $quantity, $options['unit_cost']);
                $product->cost = $newCost;
            }

            $product->stock_quantity = $newStock;
            $product->save();

            DB::commit();
            return $movement;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Calcular costo promedio ponderado
     */
    private function calculateAverageCost(Product $product, int $quantity, float $unitCost): float
    {
        $currentStock = $product->stock_quantity;
        $currentCost = $product->cost ?? 0;
        
        if ($currentStock <= 0) {
            return $unitCost;
        }

        $totalCurrentValue = $currentStock * $currentCost;
        $newValue = abs($quantity) * $unitCost;
        $totalNewStock = $currentStock + abs($quantity);

        return ($totalCurrentValue + $newValue) / $totalNewStock;
    }

    /**
     * Obtener productos con stock bajo
     */
    public function getLowStockProducts(int $tenantId): \Illuminate\Database\Eloquent\Collection
    {
        return Product::where('tenant_id', $tenantId)
            ->where('is_service', false)
            ->where('track_inventory', true)
            ->whereRaw('stock_quantity <= minimum_stock')
            ->with('category')
            ->orderBy('stock_quantity', 'asc')
            ->get();
    }

    /**
     * Obtener productos sin stock
     */
    public function getOutOfStockProducts(int $tenantId): \Illuminate\Database\Eloquent\Collection
    {
        return Product::where('tenant_id', $tenantId)
            ->where('is_service', false)
            ->where('track_inventory', true)
            ->where('stock_quantity', '<=', 0)
            ->with('category')
            ->orderBy('name')
            ->get();
    }

    /**
     * Obtener productos que necesitan reposición
     */
    public function getProductsForReorder(int $tenantId): \Illuminate\Database\Eloquent\Collection
    {
        return Product::where('tenant_id', $tenantId)
            ->where('is_service', false)
            ->where('track_inventory', true)
            ->where('reorder_point', '>', 0)
            ->whereRaw('stock_quantity <= reorder_point')
            ->with('category')
            ->orderBy('stock_quantity', 'asc')
            ->get();
    }

    /**
     * Generar reporte de valorización de inventario
     */
    public function getInventoryValuation(int $tenantId): array
    {
        $products = Product::where('tenant_id', $tenantId)
            ->where('is_service', false)
            ->where('track_inventory', true)
            ->where('stock_quantity', '>', 0)
            ->with('category')
            ->get();

        $totalValue = 0;
        $totalProducts = 0;
        $byCategory = [];

        foreach ($products as $product) {
            $productValue = $product->stock_quantity * ($product->cost ?? 0);
            $totalValue += $productValue;
            $totalProducts++;

            $categoryName = $product->category->name ?? 'Sin Categoría';
            if (!isset($byCategory[$categoryName])) {
                $byCategory[$categoryName] = [
                    'products' => 0,
                    'total_stock' => 0,
                    'total_value' => 0,
                ];
            }

            $byCategory[$categoryName]['products']++;
            $byCategory[$categoryName]['total_stock'] += $product->stock_quantity;
            $byCategory[$categoryName]['total_value'] += $productValue;
        }

        return [
            'total_value' => $totalValue,
            'total_products' => $totalProducts,
            'by_category' => $byCategory,
            'products' => $products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'code' => $product->code,
                    'name' => $product->name,
                    'category' => $product->category->name ?? 'Sin Categoría',
                    'stock' => $product->stock_quantity,
                    'cost' => $product->cost ?? 0,
                    'value' => $product->stock_quantity * ($product->cost ?? 0),
                ];
            })->toArray()
        ];
    }

    /**
     * Obtener movimientos de inventario con filtros
     */
    public function getInventoryMovements(int $tenantId, array $filters = [])
    {
        $query = InventoryMovement::where('tenant_id', $tenantId)
            ->with(['product:id,code,name', 'user:id,name']);

        if (isset($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (isset($filters['reference_type'])) {
            $query->where('reference_type', $filters['reference_type']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Generar código automático para producto
     */
    public function generateProductCode(int $tenantId, ?int $categoryId = null): string
    {
        $prefix = 'PROD';
        
        if ($categoryId) {
            $category = \App\Models\Category::find($categoryId);
            if ($category) {
                $prefix = strtoupper(substr($category->name, 0, 3));
            }
        }

        // Buscar el último número usado
        $lastProduct = Product::where('tenant_id', $tenantId)
            ->where('code', 'like', $prefix . '%')
            ->orderBy('code', 'desc')
            ->first();

        $nextNumber = 1;
        if ($lastProduct && preg_match('/(\d+)$/', $lastProduct->code, $matches)) {
            $nextNumber = (int)$matches[1] + 1;
        }

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Validar disponibilidad de stock para venta
     */
    public function validateStockAvailability(array $items): array
    {
        $errors = [];
        
        foreach ($items as $item) {
            $product = Product::find($item['product_id']);
            
            if (!$product) {
                $errors[] = "Producto con ID {$item['product_id']} no encontrado";
                continue;
            }

            if ($product->is_service) {
                continue; // Los servicios no requieren stock
            }

            if (!$product->track_inventory) {
                continue; // Si no se rastrea inventario, permitir venta
            }

            $requiredQuantity = $item['quantity'];
            $availableStock = $product->stock_quantity;

            if ($availableStock < $requiredQuantity) {
                if (!$product->allow_negative_stock) {
                    $errors[] = "Stock insuficiente para {$product->name}. Disponible: {$availableStock}, Requerido: {$requiredQuantity}";
                }
            }
        }

        return $errors;
    }
}