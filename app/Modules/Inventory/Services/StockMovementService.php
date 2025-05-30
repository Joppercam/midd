<?php

namespace App\Modules\Inventory\Services;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class StockMovementService
{
    protected $productService;
    
    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    public function getMovements(array $filters = [], int $perPage = 20)
    {
        $tenantId = auth()->user()->tenant_id;
        $query = InventoryMovement::where('tenant_id', $tenantId)
            ->with(['product', 'user', 'taxDocumentItem.taxDocument']);

        // Filtros
        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                  });
            });
        }

        // Ordenamiento
        $sortField = $filters['sort'] ?? 'created_at';
        $sortDirection = $filters['direction'] ?? 'desc';
        $query->orderBy($sortField, $sortDirection);

        return $query->paginate($perPage)->withQueryString();
    }

    public function createMovement(array $data): InventoryMovement
    {
        $product = Product::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($data['product_id']);

        return $this->productService->createInventoryMovement($product, $data);
    }

    public function createBulkMovements(array $movements): array
    {
        $results = [];
        $errors = [];

        DB::transaction(function () use ($movements, &$results, &$errors) {
            foreach ($movements as $movementData) {
                try {
                    $movement = $this->createMovement($movementData);
                    $results[] = [
                        'success' => true,
                        'movement_id' => $movement->id,
                        'product_id' => $movement->product_id,
                        'product_name' => $movement->product->name,
                    ];
                } catch (\Exception $e) {
                    $errors[] = [
                        'product_id' => $movementData['product_id'] ?? null,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        });

        return [
            'success' => empty($errors),
            'results' => $results,
            'errors' => $errors,
            'processed' => count($results),
            'failed' => count($errors),
        ];
    }

    public function adjustStock(int $productId, int $newQuantity, string $reason = ''): InventoryMovement
    {
        $product = Product::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($productId);

        return $this->productService->adjustStock($product, $newQuantity, $reason);
    }

    public function transferStock(int $fromProductId, int $toProductId, int $quantity, string $reason = ''): array
    {
        $fromProduct = Product::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($fromProductId);
        
        $toProduct = Product::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($toProductId);

        return $this->productService->transferStock($fromProduct, $toProduct, $quantity, $reason);
    }

    public function recordSale(int $productId, int $quantity, float $unitPrice, ?string $reference = null): InventoryMovement
    {
        $product = Product::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($productId);

        if ($product->stock_quantity < $quantity) {
            throw new \Exception('Stock insuficiente para la venta');
        }

        return $this->productService->createInventoryMovement($product, [
            'type' => 'sale',
            'quantity' => $quantity,
            'unit_cost' => $unitPrice,
            'reference' => $reference ?? 'Venta directa',
            'notes' => "Venta de {$quantity} unidades",
        ]);
    }

    public function recordPurchase(int $productId, int $quantity, float $unitCost, ?string $reference = null): InventoryMovement
    {
        $product = Product::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($productId);

        return $this->productService->createInventoryMovement($product, [
            'type' => 'purchase',
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'reference' => $reference ?? 'Compra directa',
            'notes' => "Compra de {$quantity} unidades",
        ]);
    }

    public function recordWaste(int $productId, int $quantity, string $reason): InventoryMovement
    {
        $product = Product::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($productId);

        if ($product->stock_quantity < $quantity) {
            throw new \Exception('Stock insuficiente para registrar merma');
        }

        return $this->productService->createInventoryMovement($product, [
            'type' => 'waste',
            'quantity' => $quantity,
            'unit_cost' => $product->purchase_price,
            'reference' => 'Merma/Desperdicio',
            'notes' => $reason,
        ]);
    }

    public function recordReturn(int $productId, int $quantity, string $reason, ?float $unitCost = null): InventoryMovement
    {
        $product = Product::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($productId);

        return $this->productService->createInventoryMovement($product, [
            'type' => 'return',
            'quantity' => $quantity,
            'unit_cost' => $unitCost ?? $product->purchase_price,
            'reference' => 'Devolución',
            'notes' => $reason,
        ]);
    }

    public function getMovementsSummary(array $filters = []): array
    {
        $tenantId = auth()->user()->tenant_id;
        $query = InventoryMovement::where('tenant_id', $tenantId);

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
            'by_type' => [],
            'total_value' => 0,
            'by_user' => [],
        ];

        // Agrupar por tipo
        $byType = $movements->groupBy('type');
        foreach ($byType as $type => $typeMovements) {
            $summary['by_type'][$type] = [
                'count' => $typeMovements->count(),
                'total_quantity' => $typeMovements->sum('quantity'),
                'total_value' => $typeMovements->sum('total_cost'),
            ];
        }

        // Calcular valor total
        $summary['total_value'] = $movements->sum('total_cost');

        // Agrupar por usuario
        $byUser = $movements->groupBy('user_id');
        foreach ($byUser as $userId => $userMovements) {
            $user = User::find($userId);
            $summary['by_user'][] = [
                'user_id' => $userId,
                'user_name' => $user ? $user->name : 'Usuario eliminado',
                'count' => $userMovements->count(),
                'total_value' => $userMovements->sum('total_cost'),
            ];
        }

        return $summary;
    }

    public function getStockHistory(int $productId, array $filters = []): array
    {
        $product = Product::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($productId);

        $query = $product->inventoryMovements();

        // Aplicar filtros
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $movements = $query->with('user')->orderBy('created_at')->get();

        $history = [];
        $runningStock = 0;

        foreach ($movements as $movement) {
            $multiplier = $this->getStockMultiplier($movement->type);
            $runningStock += ($movement->quantity * $multiplier);

            $history[] = [
                'movement' => $movement,
                'stock_after' => $runningStock,
                'change' => $movement->quantity * $multiplier,
            ];
        }

        return [
            'product' => $product,
            'history' => $history,
            'current_stock' => $product->stock_quantity,
        ];
    }

    public function getMovementTypes(): array
    {
        return [
            'purchase' => [
                'label' => 'Compra',
                'description' => 'Entrada por compra a proveedor',
                'affects_stock' => 'increase',
                'icon' => 'shopping-cart',
                'color' => 'green',
            ],
            'sale' => [
                'label' => 'Venta',
                'description' => 'Salida por venta a cliente',
                'affects_stock' => 'decrease',
                'icon' => 'trending-up',
                'color' => 'blue',
            ],
            'adjustment' => [
                'label' => 'Ajuste',
                'description' => 'Ajuste de inventario',
                'affects_stock' => 'variable',
                'icon' => 'edit',
                'color' => 'yellow',
            ],
            'transfer_in' => [
                'label' => 'Transferencia Entrada',
                'description' => 'Entrada por transferencia',
                'affects_stock' => 'increase',
                'icon' => 'arrow-down',
                'color' => 'green',
            ],
            'transfer_out' => [
                'label' => 'Transferencia Salida',
                'description' => 'Salida por transferencia',
                'affects_stock' => 'decrease',
                'icon' => 'arrow-up',
                'color' => 'red',
            ],
            'waste' => [
                'label' => 'Merma',
                'description' => 'Pérdida por desperdicio o daño',
                'affects_stock' => 'decrease',
                'icon' => 'trash',
                'color' => 'red',
            ],
            'theft' => [
                'label' => 'Robo',
                'description' => 'Pérdida por robo',
                'affects_stock' => 'decrease',
                'icon' => 'shield-off',
                'color' => 'red',
            ],
            'return' => [
                'label' => 'Devolución',
                'description' => 'Entrada por devolución',
                'affects_stock' => 'increase',
                'icon' => 'arrow-left',
                'color' => 'orange',
            ],
        ];
    }

    public function validateMovement(array $data): array
    {
        $errors = [];

        // Validar producto
        if (empty($data['product_id'])) {
            $errors[] = 'El producto es requerido';
        } else {
            $product = Product::where('tenant_id', auth()->user()->tenant_id)
                ->find($data['product_id']);
            
            if (!$product) {
                $errors[] = 'El producto no existe';
            } elseif (!$product->is_active) {
                $errors[] = 'El producto está inactivo';
            }
        }

        // Validar cantidad
        if (empty($data['quantity']) || $data['quantity'] <= 0) {
            $errors[] = 'La cantidad debe ser mayor a 0';
        }

        // Validar tipo de movimiento
        $validTypes = array_keys($this->getMovementTypes());
        if (empty($data['type']) || !in_array($data['type'], $validTypes)) {
            $errors[] = 'Tipo de movimiento inválido';
        }

        // Validar stock disponible para movimientos que reducen stock
        if (!empty($data['type']) && $this->getStockMultiplier($data['type']) < 0) {
            if (isset($product) && $product->stock_quantity < $data['quantity']) {
                $errors[] = 'Stock insuficiente para el movimiento';
            }
        }

        // Validar costo unitario
        if (empty($data['unit_cost']) || $data['unit_cost'] < 0) {
            $errors[] = 'El costo unitario debe ser mayor o igual a 0';
        }

        return $errors;
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
}