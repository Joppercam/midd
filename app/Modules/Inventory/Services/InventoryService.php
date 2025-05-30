<?php

namespace App\Modules\Inventory\Services;

use App\Models\Product;
use App\Models\InventoryMovement;
use App\Models\Category;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class InventoryService
{
    protected $productService;
    
    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    public function getInventoryOverview(): array
    {
        $tenantId = auth()->user()->tenant_id;

        // Resumen general
        $overview = Product::where('tenant_id', $tenantId)
            ->selectRaw("
                COUNT(*) as total_products,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_products,
                COUNT(CASE WHEN stock_quantity <= min_stock_level THEN 1 END) as low_stock_products,
                COUNT(CASE WHEN stock_quantity = 0 THEN 1 END) as out_of_stock_products,
                SUM(stock_quantity * purchase_price) as total_purchase_value,
                SUM(stock_quantity * sale_price) as total_sale_value
            ")
            ->first();

        // Movimientos recientes
        $recentMovements = InventoryMovement::where('tenant_id', $tenantId)
            ->with(['product', 'user'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Productos más vendidos este mes
        $topSellingProducts = $this->getTopSellingProducts(10);

        // Análisis de rotación
        $rotationAnalysis = $this->getInventoryRotationAnalysis();

        return [
            'overview' => [
                'total_products' => $overview->total_products,
                'active_products' => $overview->active_products,
                'low_stock_products' => $overview->low_stock_products,
                'out_of_stock_products' => $overview->out_of_stock_products,
                'total_purchase_value' => $overview->total_purchase_value,
                'total_sale_value' => $overview->total_sale_value,
                'potential_profit' => $overview->total_sale_value - $overview->total_purchase_value,
            ],
            'recent_movements' => $recentMovements,
            'top_selling_products' => $topSellingProducts,
            'rotation_analysis' => $rotationAnalysis,
        ];
    }

    public function getStockAlerts(): array
    {
        $tenantId = auth()->user()->tenant_id;

        // Productos con stock bajo
        $lowStockProducts = Product::where('tenant_id', $tenantId)
            ->whereRaw('stock_quantity <= min_stock_level')
            ->where('is_active', true)
            ->with('category')
            ->orderBy('stock_quantity', 'asc')
            ->get();

        // Productos sin stock
        $outOfStockProducts = Product::where('tenant_id', $tenantId)
            ->where('stock_quantity', 0)
            ->where('is_active', true)
            ->with('category')
            ->orderBy('name')
            ->get();

        // Productos con exceso de stock (más de 3 meses sin movimiento)
        $excessStockProducts = Product::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('last_movement_at')
                      ->orWhere('last_movement_at', '<', now()->subMonths(3));
            })
            ->where('stock_quantity', '>', 0)
            ->with('category')
            ->orderBy('stock_quantity', 'desc')
            ->get();

        // Productos próximos a vencer (si aplica)
        $expiringProducts = $this->getExpiringProducts();

        return [
            'low_stock' => $lowStockProducts,
            'out_of_stock' => $outOfStockProducts,
            'excess_stock' => $excessStockProducts,
            'expiring_products' => $expiringProducts,
            'summary' => [
                'low_stock_count' => $lowStockProducts->count(),
                'out_of_stock_count' => $outOfStockProducts->count(),
                'excess_stock_count' => $excessStockProducts->count(),
                'expiring_count' => $expiringProducts->count(),
            ],
        ];
    }

    public function getInventoryMovements(array $filters = [], int $perPage = 20)
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

        // Ordenamiento
        $sortField = $filters['sort'] ?? 'created_at';
        $sortDirection = $filters['direction'] ?? 'desc';
        $query->orderBy($sortField, $sortDirection);

        return $query->paginate($perPage)->withQueryString();
    }

    public function processStockAdjustment(array $adjustments, string $reason = ''): array
    {
        $results = [];
        $errors = [];

        DB::transaction(function () use ($adjustments, $reason, &$results, &$errors) {
            foreach ($adjustments as $adjustment) {
                try {
                    $product = Product::where('tenant_id', auth()->user()->tenant_id)
                        ->findOrFail($adjustment['product_id']);

                    $movement = $this->productService->adjustStock(
                        $product,
                        $adjustment['new_quantity'],
                        $reason,
                        [
                            'unit_cost' => $adjustment['unit_cost'] ?? $product->purchase_price,
                            'reference' => 'Ajuste masivo de inventario'
                        ]
                    );

                    $results[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'old_quantity' => $adjustment['old_quantity'] ?? $product->stock_quantity,
                        'new_quantity' => $adjustment['new_quantity'],
                        'movement_id' => $movement->id,
                        'success' => true,
                    ];
                } catch (\Exception $e) {
                    $errors[] = [
                        'product_id' => $adjustment['product_id'] ?? null,
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

    public function generateInventoryReport(array $filters = []): array
    {
        $tenantId = auth()->user()->tenant_id;
        $query = Product::where('tenant_id', $tenantId);

        // Aplicar filtros
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        $products = $query->with('category')->get();

        $report = [
            'products' => [],
            'summary' => [
                'total_products' => 0,
                'total_purchase_value' => 0,
                'total_sale_value' => 0,
                'total_profit' => 0,
                'average_margin' => 0,
            ],
            'categories' => [],
        ];

        $categoryStats = [];

        foreach ($products as $product) {
            $purchaseValue = $product->stock_quantity * $product->purchase_price;
            $saleValue = $product->stock_quantity * $product->sale_price;
            $profit = $saleValue - $purchaseValue;

            $productData = [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'category' => $product->category->name ?? 'Sin categoría',
                'stock_quantity' => $product->stock_quantity,
                'min_stock_level' => $product->min_stock_level,
                'purchase_price' => $product->purchase_price,
                'sale_price' => $product->sale_price,
                'purchase_value' => $purchaseValue,
                'sale_value' => $saleValue,
                'profit' => $profit,
                'profit_margin' => $product->profit_margin,
                'last_movement_at' => $product->last_movement_at,
                'is_active' => $product->is_active,
            ];

            $report['products'][] = $productData;

            // Acumular estadísticas generales
            $report['summary']['total_products']++;
            $report['summary']['total_purchase_value'] += $purchaseValue;
            $report['summary']['total_sale_value'] += $saleValue;
            $report['summary']['total_profit'] += $profit;

            // Acumular por categoría
            $categoryName = $product->category->name ?? 'Sin categoría';
            if (!isset($categoryStats[$categoryName])) {
                $categoryStats[$categoryName] = [
                    'name' => $categoryName,
                    'products_count' => 0,
                    'total_purchase_value' => 0,
                    'total_sale_value' => 0,
                    'total_profit' => 0,
                ];
            }

            $categoryStats[$categoryName]['products_count']++;
            $categoryStats[$categoryName]['total_purchase_value'] += $purchaseValue;
            $categoryStats[$categoryName]['total_sale_value'] += $saleValue;
            $categoryStats[$categoryName]['total_profit'] += $profit;
        }

        // Calcular margen promedio
        if ($report['summary']['total_purchase_value'] > 0) {
            $report['summary']['average_margin'] = 
                ($report['summary']['total_profit'] / $report['summary']['total_purchase_value']) * 100;
        }

        $report['categories'] = array_values($categoryStats);

        return $report;
    }

    public function getInventoryRotationAnalysis(): array
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = now()->subYear();

        // Obtener productos con movimientos en el último año
        $products = Product::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->with(['inventoryMovements' => function ($query) use ($startDate) {
                $query->where('created_at', '>=', $startDate)
                      ->where('type', 'sale');
            }])
            ->get();

        $rotationData = [];

        foreach ($products as $product) {
            $totalSold = $product->inventoryMovements->sum('quantity');
            $averageStock = $product->stock_quantity; // Simplificado
            
            $rotationRate = $averageStock > 0 ? $totalSold / $averageStock : 0;
            $daysCovered = $rotationRate > 0 ? 365 / $rotationRate : 0;

            $category = 'slow'; // Por defecto
            if ($rotationRate >= 12) {
                $category = 'fast';
            } elseif ($rotationRate >= 6) {
                $category = 'medium';
            }

            $rotationData[] = [
                'product' => $product,
                'total_sold' => $totalSold,
                'average_stock' => $averageStock,
                'rotation_rate' => round($rotationRate, 2),
                'days_covered' => round($daysCovered, 0),
                'category' => $category,
            ];
        }

        // Ordenar por tasa de rotación
        usort($rotationData, function ($a, $b) {
            return $b['rotation_rate'] <=> $a['rotation_rate'];
        });

        return $rotationData;
    }

    public function getTopSellingProducts(int $limit = 10): Collection
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = now()->startOfMonth();

        return Product::where('tenant_id', $tenantId)
            ->withSum(['inventoryMovements as total_sales' => function ($query) use ($startDate) {
                $query->where('type', 'sale')
                      ->where('created_at', '>=', $startDate);
            }], 'quantity')
            ->with('category')
            ->orderByDesc('total_sales')
            ->limit($limit)
            ->get();
    }

    public function getStockValuation(): array
    {
        $tenantId = auth()->user()->tenant_id;

        $valuation = Product::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->selectRaw("
                COUNT(*) as products_count,
                SUM(stock_quantity) as total_units,
                SUM(stock_quantity * purchase_price) as total_purchase_value,
                SUM(stock_quantity * sale_price) as total_sale_value,
                AVG(profit_margin) as average_margin
            ")
            ->first();

        $categoryValuation = Product::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->selectRaw("
                categories.name as category_name,
                COUNT(products.id) as products_count,
                SUM(products.stock_quantity) as total_units,
                SUM(products.stock_quantity * products.purchase_price) as total_purchase_value,
                SUM(products.stock_quantity * products.sale_price) as total_sale_value
            ")
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_purchase_value')
            ->get();

        return [
            'total' => [
                'products_count' => $valuation->products_count,
                'total_units' => $valuation->total_units,
                'total_purchase_value' => $valuation->total_purchase_value,
                'total_sale_value' => $valuation->total_sale_value,
                'potential_profit' => $valuation->total_sale_value - $valuation->total_purchase_value,
                'average_margin' => $valuation->average_margin,
            ],
            'by_category' => $categoryValuation,
        ];
    }

    public function predictReorderPoints(): array
    {
        $tenantId = auth()->user()->tenant_id;
        $products = Product::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->with(['inventoryMovements' => function ($query) {
                $query->where('type', 'sale')
                      ->where('created_at', '>=', now()->subMonths(6))
                      ->orderBy('created_at', 'desc');
            }])
            ->get();

        $predictions = [];

        foreach ($products as $product) {
            $salesHistory = $product->inventoryMovements;
            
            if ($salesHistory->count() < 3) {
                continue; // No hay suficientes datos
            }

            // Calcular promedio de ventas diarias
            $totalDays = max(1, now()->diffInDays($salesHistory->last()->created_at));
            $totalSold = $salesHistory->sum('quantity');
            $dailyAverage = $totalSold / $totalDays;

            // Calcular tiempo de reposición promedio (asumiendo 7 días)
            $leadTime = 7;

            // Calcular stock de seguridad (20% del consumo durante el lead time)
            $safetyStock = ($dailyAverage * $leadTime) * 0.2;

            // Punto de reorden = (Consumo diario × Tiempo de reposición) + Stock de seguridad
            $reorderPoint = ($dailyAverage * $leadTime) + $safetyStock;

            $predictions[] = [
                'product' => $product,
                'current_stock' => $product->stock_quantity,
                'current_min_stock' => $product->min_stock_level,
                'daily_average_sales' => round($dailyAverage, 2),
                'suggested_reorder_point' => ceil($reorderPoint),
                'safety_stock' => ceil($safetyStock),
                'needs_adjustment' => $product->min_stock_level < $reorderPoint,
            ];
        }

        return $predictions;
    }

    private function getExpiringProducts(): Collection
    {
        // Por ahora retornar colección vacía
        // En una implementación completa, esto verificaría fechas de vencimiento
        return collect([]);
    }
}