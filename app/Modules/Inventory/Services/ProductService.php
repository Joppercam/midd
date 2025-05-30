<?php

namespace App\Modules\Inventory\Services;

use App\Models\Product;
use App\Models\InventoryMovement;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class ProductService
{
    public function getProductsList(array $filters = [], int $perPage = 15)
    {
        $query = Product::where('tenant_id', auth()->user()->tenant_id);

        // Búsqueda
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filtro por categoría
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // Filtro por tipo
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Filtro por stock bajo
        if (!empty($filters['low_stock'])) {
            $query->whereRaw('stock_quantity <= min_stock_level');
        }

        // Filtro por activos/inactivos
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        // Ordenamiento
        $sortField = $filters['sort'] ?? 'name';
        $sortDirection = $filters['direction'] ?? 'asc';
        $query->orderBy($sortField, $sortDirection);

        // Incluir relaciones
        $query->with(['category']);

        return $query->paginate($perPage)->withQueryString();
    }

    public function getProductsStats(): array
    {
        $tenantId = auth()->user()->tenant_id;
        
        $productStats = Product::where('tenant_id', $tenantId)
            ->selectRaw("
                COUNT(*) as total_products,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_products,
                COUNT(CASE WHEN stock_quantity <= min_stock_level THEN 1 END) as low_stock_products,
                COUNT(CASE WHEN stock_quantity = 0 THEN 1 END) as out_of_stock_products,
                SUM(stock_quantity * purchase_price) as total_inventory_value,
                AVG(profit_margin) as average_margin
            ")
            ->first();

        $topProducts = Product::where('tenant_id', $tenantId)
            ->withSum(['inventoryMovements as total_sales' => function ($query) {
                $query->where('type', 'sale')
                      ->where('created_at', '>=', now()->startOfMonth());
            }], 'quantity')
            ->orderByDesc('total_sales')
            ->limit(5)
            ->get();

        return [
            'total_products' => $productStats->total_products,
            'active_products' => $productStats->active_products,
            'low_stock_products' => $productStats->low_stock_products,
            'out_of_stock_products' => $productStats->out_of_stock_products,
            'total_inventory_value' => $productStats->total_inventory_value,
            'average_margin' => $productStats->average_margin,
            'top_products' => $topProducts,
        ];
    }

    public function createProduct(array $data): Product
    {
        // Verificar código único dentro del tenant
        if (!empty($data['code'])) {
            $exists = Product::where('tenant_id', auth()->user()->tenant_id)
                ->where('code', $data['code'])
                ->exists();

            if ($exists) {
                throw new \Exception('El código del producto ya existe.');
            }
        }

        // Generar código automático si no se proporciona
        if (empty($data['code'])) {
            $data['code'] = $this->generateProductCode($data['category_id'] ?? null);
        }

        // Calcular margen de ganancia
        if (isset($data['purchase_price']) && isset($data['sale_price'])) {
            $data['profit_margin'] = $this->calculateProfitMargin(
                $data['purchase_price'], 
                $data['sale_price']
            );
        }

        $product = Product::create([
            'tenant_id' => auth()->user()->tenant_id,
            ...$data,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => auth()->id(),
        ]);

        // Crear movimiento inicial de inventario si hay stock inicial
        if (!empty($data['initial_stock']) && $data['initial_stock'] > 0) {
            $this->createInventoryMovement($product, [
                'type' => 'adjustment',
                'quantity' => $data['initial_stock'],
                'unit_cost' => $data['purchase_price'] ?? 0,
                'reference' => 'Stock inicial',
                'notes' => 'Stock inicial del producto'
            ]);
        }

        return $product;
    }

    public function updateProduct(Product $product, array $data): Product
    {
        // Verificar código único dentro del tenant
        if (!empty($data['code'])) {
            $exists = Product::where('tenant_id', auth()->user()->tenant_id)
                ->where('code', $data['code'])
                ->where('id', '!=', $product->id)
                ->exists();

            if ($exists) {
                throw new \Exception('El código del producto ya existe.');
            }
        }

        // Calcular margen de ganancia
        if (isset($data['purchase_price']) && isset($data['sale_price'])) {
            $data['profit_margin'] = $this->calculateProfitMargin(
                $data['purchase_price'], 
                $data['sale_price']
            );
        }

        $data['updated_by'] = auth()->id();
        $product->update($data);
        
        return $product->fresh();
    }

    public function deleteProduct(Product $product): array
    {
        // Verificar si tiene movimientos de inventario
        if ($product->inventoryMovements()->exists()) {
            return [
                'success' => false,
                'message' => 'No se puede eliminar el producto porque tiene movimientos de inventario.'
            ];
        }

        // Verificar si está en documentos tributarios
        if ($product->taxDocumentItems()->exists()) {
            return [
                'success' => false,
                'message' => 'No se puede eliminar el producto porque está en documentos tributarios.'
            ];
        }

        $product->delete();

        return [
            'success' => true,
            'message' => 'Producto eliminado exitosamente.'
        ];
    }

    public function adjustStock(Product $product, int $newQuantity, string $reason = '', array $options = []): InventoryMovement
    {
        $currentStock = $product->stock_quantity;
        $difference = $newQuantity - $currentStock;

        if ($difference == 0) {
            throw new \Exception('La cantidad nueva es igual a la actual.');
        }

        $movementData = [
            'type' => 'adjustment',
            'quantity' => abs($difference),
            'unit_cost' => $options['unit_cost'] ?? $product->purchase_price,
            'reference' => $options['reference'] ?? 'Ajuste de inventario',
            'notes' => $reason,
            'adjustment_type' => $difference > 0 ? 'increase' : 'decrease',
        ];

        return $this->createInventoryMovement($product, $movementData);
    }

    public function transferStock(Product $fromProduct, Product $toProduct, int $quantity, string $reason = ''): array
    {
        if ($fromProduct->stock_quantity < $quantity) {
            throw new \Exception('Stock insuficiente para la transferencia.');
        }

        DB::transaction(function () use ($fromProduct, $toProduct, $quantity, $reason) {
            // Movimiento de salida
            $this->createInventoryMovement($fromProduct, [
                'type' => 'transfer_out',
                'quantity' => $quantity,
                'unit_cost' => $fromProduct->purchase_price,
                'reference' => "Transferencia a {$toProduct->code}",
                'notes' => $reason,
            ]);

            // Movimiento de entrada
            $this->createInventoryMovement($toProduct, [
                'type' => 'transfer_in',
                'quantity' => $quantity,
                'unit_cost' => $fromProduct->purchase_price,
                'reference' => "Transferencia desde {$fromProduct->code}",
                'notes' => $reason,
            ]);
        });

        return [
            'success' => true,
            'message' => 'Transferencia realizada exitosamente.'
        ];
    }

    public function getProductMovements(Product $product, array $filters = []): array
    {
        $query = $product->inventoryMovements()
            ->with(['user', 'taxDocumentItem.taxDocument']);

        // Filtro por tipo
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Filtro por fecha
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        $movements = $query->orderBy('created_at', 'desc')->paginate(20);

        return [
            'movements' => $movements,
            'summary' => $this->getMovementsSummary($product, $filters),
        ];
    }

    public function getLowStockProducts(): array
    {
        return Product::where('tenant_id', auth()->user()->tenant_id)
            ->whereRaw('stock_quantity <= min_stock_level')
            ->where('is_active', true)
            ->with('category')
            ->orderBy('stock_quantity', 'asc')
            ->get()
            ->toArray();
    }

    public function getInventoryValuation(): array
    {
        $products = Product::where('tenant_id', auth()->user()->tenant_id)
            ->where('is_active', true)
            ->get();

        $totalPurchaseValue = 0;
        $totalSaleValue = 0;
        $totalProfit = 0;

        foreach ($products as $product) {
            $purchaseValue = $product->stock_quantity * $product->purchase_price;
            $saleValue = $product->stock_quantity * $product->sale_price;
            $profit = $saleValue - $purchaseValue;

            $totalPurchaseValue += $purchaseValue;
            $totalSaleValue += $saleValue;
            $totalProfit += $profit;
        }

        return [
            'total_purchase_value' => $totalPurchaseValue,
            'total_sale_value' => $totalSaleValue,
            'total_profit' => $totalProfit,
            'profit_margin' => $totalPurchaseValue > 0 ? ($totalProfit / $totalPurchaseValue) * 100 : 0,
            'products_count' => $products->count(),
        ];
    }

    public function createInventoryMovement(Product $product, array $data): InventoryMovement
    {
        $movement = InventoryMovement::create([
            'tenant_id' => auth()->user()->tenant_id,
            'product_id' => $product->id,
            'user_id' => auth()->id(),
            'quantity' => $data['quantity'],
            'unit_cost' => $data['unit_cost'],
            'total_cost' => $data['quantity'] * $data['unit_cost'],
            'type' => $data['type'],
            'reference' => $data['reference'] ?? '',
            'notes' => $data['notes'] ?? '',
            'tax_document_item_id' => $data['tax_document_item_id'] ?? null,
        ]);

        // Actualizar stock del producto
        $this->updateProductStock($product, $movement);

        return $movement;
    }

    private function updateProductStock(Product $product, InventoryMovement $movement): void
    {
        $multiplier = $this->getStockMultiplier($movement->type);
        $newStock = $product->stock_quantity + ($movement->quantity * $multiplier);

        $product->update([
            'stock_quantity' => max(0, $newStock),
            'last_movement_at' => now(),
        ]);
    }

    private function getStockMultiplier(string $movementType): int
    {
        $increases = ['purchase', 'adjustment', 'transfer_in', 'return'];
        $decreases = ['sale', 'transfer_out', 'waste', 'theft'];

        if (in_array($movementType, $increases)) {
            return 1;
        } elseif (in_array($movementType, $decreases)) {
            return -1;
        }

        return 0;
    }

    private function calculateProfitMargin(float $purchasePrice, float $salePrice): float
    {
        if ($purchasePrice <= 0) {
            return 0;
        }

        return (($salePrice - $purchasePrice) / $purchasePrice) * 100;
    }

    private function generateProductCode(?int $categoryId = null): string
    {
        $prefix = 'PROD';
        
        if ($categoryId) {
            $category = Category::find($categoryId);
            if ($category) {
                $prefix = strtoupper(substr($category->name, 0, 3));
            }
        }

        $lastCode = Product::where('tenant_id', auth()->user()->tenant_id)
            ->where('code', 'like', $prefix . '%')
            ->orderBy('code', 'desc')
            ->value('code');

        if ($lastCode) {
            $number = (int) substr($lastCode, strlen($prefix)) + 1;
        } else {
            $number = 1;
        }

        return $prefix . str_pad($number, 4, '0', STR_PAD_LEFT);
    }

    private function getMovementsSummary(Product $product, array $filters = []): array
    {
        $query = $product->inventoryMovements();

        // Aplicar filtros de fecha si existen
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        $movements = $query->get();

        $summary = [
            'total_movements' => $movements->count(),
            'total_increases' => 0,
            'total_decreases' => 0,
            'total_cost' => 0,
        ];

        foreach ($movements as $movement) {
            $multiplier = $this->getStockMultiplier($movement->type);
            
            if ($multiplier > 0) {
                $summary['total_increases'] += $movement->quantity;
            } elseif ($multiplier < 0) {
                $summary['total_decreases'] += $movement->quantity;
            }

            $summary['total_cost'] += $movement->total_cost;
        }

        return $summary;
    }
}