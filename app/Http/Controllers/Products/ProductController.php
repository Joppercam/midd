<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Services\InventoryService;
use App\Traits\ChecksPermissions;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    use ChecksPermissions;
    public function index(Request $request)
    {
        $this->checkPermission('products.view');
        $query = Product::with(['category'])
            ->where('tenant_id', auth()->user()->tenant_id);

        // Búsqueda
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filtro por categoría
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filtro por estado de stock
        if ($request->filled('stock_status')) {
            switch ($request->stock_status) {
                case 'in_stock':
                    $query->where('stock_quantity', '>', 0);
                    break;
                case 'low_stock':
                    $query->whereRaw('stock_quantity <= minimum_stock AND stock_quantity > 0');
                    break;
                case 'out_of_stock':
                    $query->where('stock_quantity', '<=', 0);
                    break;
            }
        }

        // Filtro por tipo
        if ($request->filled('is_service')) {
            $query->where('is_service', $request->boolean('is_service'));
        }

        // Ordenamiento
        $sortField = $request->get('sort', 'name');
        $sortDirection = $request->get('direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        $products = $query->paginate(15)->withQueryString();
        
        $tenantId = auth()->user()->tenant_id;
        
        // Cache key para estadísticas de productos
        $cacheKey = "products:stats:{$tenantId}";
        $cacheTTL = 300; // 5 minutos

        // Estadísticas optimizadas con caché
        $stats = Cache::remember($cacheKey, $cacheTTL, function() use ($tenantId) {
            return Product::where('tenant_id', $tenantId)
                ->selectRaw("
                    COUNT(*) as total_products,
                    COUNT(CASE WHEN is_service = 1 THEN 1 END) as total_services,
                    COUNT(CASE WHEN stock_quantity <= minimum_stock AND stock_quantity > 0 AND is_service = 0 THEN 1 END) as low_stock,
                    COUNT(CASE WHEN stock_quantity <= 0 AND is_service = 0 THEN 1 END) as out_of_stock,
                    SUM(CASE WHEN is_service = 0 THEN stock_quantity * cost ELSE 0 END) as total_value
                ")
                ->first()
                ->toArray();
        });

        // Categorías con caché
        $categories = Cache::remember("categories:list:{$tenantId}", $cacheTTL, function() use ($tenantId) {
            return Category::where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get();
        });

        return Inertia::render('Products/Index', [
            'products' => $products,
            'filters' => $request->only(['search', 'category_id', 'stock_status', 'is_service', 'sort', 'direction']),
            'stats' => $stats,
            'categories' => $categories,
        ]);
    }

    public function create()
    {
        $this->checkPermission('products.create');
        $categories = Category::where('tenant_id', auth()->user()->tenant_id)
            ->orderBy('name')
            ->get();

        return Inertia::render('Products/Create', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $this->checkPermission('products.create');
        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'is_service' => 'required|boolean',
            'price' => 'required|numeric|min:0',
            'cost' => 'required|numeric|min:0',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'stock_quantity' => 'required_if:is_service,false|numeric|min:0',
            'minimum_stock' => 'required_if:is_service,false|numeric|min:0',
            'unit' => 'required|string|max:50',
        ]);

        // Verificar código único dentro del tenant
        $exists = Product::where('tenant_id', auth()->user()->tenant_id)
            ->where('code', $validated['code'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['code' => 'El código ya existe en tu empresa.']);
        }

        DB::beginTransaction();
        try {
            $product = Product::create([
                'tenant_id' => auth()->user()->tenant_id,
                ...$validated,
                'stock_quantity' => $validated['is_service'] ? 0 : $validated['stock_quantity'],
                'minimum_stock' => $validated['is_service'] ? 0 : $validated['minimum_stock'],
            ]);

            // Si hay stock inicial, crear movimiento de inventario
            if (!$product->is_service && $product->stock_quantity > 0) {
                $product->inventoryMovements()->create([
                    'tenant_id' => auth()->user()->tenant_id,
                    'movement_type' => 'adjustment',
                    'quantity' => $product->stock_quantity,
                    'unit_cost' => $product->cost,
                    'reference_type' => 'initial_stock',
                    'notes' => 'Stock inicial',
                    'created_by' => auth()->id(),
                ]);
            }

            DB::commit();

            return redirect()->route('products.index')
                ->with('success', 'Producto creado exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al crear el producto: ' . $e->getMessage());
        }
    }

    public function show(Product $product)
    {
        $this->checkPermission('products.view');
        if ($product->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $product->load(['category', 'inventoryMovements' => function ($query) {
            $query->orderBy('created_at', 'desc')->limit(10);
        }]);

        // Estadísticas de movimientos
        $movementStats = [
            'total_purchases' => $product->inventoryMovements()
                ->where('movement_type', 'purchase')->sum('quantity'),
            'total_sales' => $product->inventoryMovements()
                ->where('movement_type', 'sale')->sum('quantity'),
            'total_adjustments' => $product->inventoryMovements()
                ->where('movement_type', 'adjustment')->count(),
            'last_movement' => $product->inventoryMovements()
                ->latest()->first(),
        ];

        return Inertia::render('Products/Show', [
            'product' => $product,
            'movementStats' => $movementStats,
        ]);
    }

    public function edit(Product $product)
    {
        $this->checkPermission('products.edit');
        if ($product->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $categories = Category::where('tenant_id', auth()->user()->tenant_id)
            ->orderBy('name')
            ->get();

        return Inertia::render('Products/Edit', [
            'product' => $product,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $this->checkPermission('products.edit');
        if ($product->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'is_service' => 'required|boolean',
            'price' => 'required|numeric|min:0',
            'cost' => 'required|numeric|min:0',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'minimum_stock' => 'required_if:is_service,false|numeric|min:0',
            'unit' => 'required|string|max:50',
        ]);

        // Verificar código único dentro del tenant
        $exists = Product::where('tenant_id', auth()->user()->tenant_id)
            ->where('code', $validated['code'])
            ->where('id', '!=', $product->id)
            ->exists();

        if ($exists) {
            return back()->withErrors(['code' => 'El código ya existe en tu empresa.']);
        }

        $product->update($validated);

        return redirect()->route('products.show', $product)
            ->with('success', 'Producto actualizado exitosamente.');
    }

    public function destroy(Product $product)
    {
        $this->checkPermission('products.delete');
        if ($product->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        // Verificar si tiene documentos asociados
        $hasDocuments = $product->taxDocumentItems()->exists();
        if ($hasDocuments) {
            return back()->with('error', 'No se puede eliminar el producto porque tiene documentos asociados.');
        }

        $product->delete();

        return redirect()->route('products.index')
            ->with('success', 'Producto eliminado exitosamente.');
    }

    public function updateStock(Request $request, Product $product, InventoryService $inventoryService)
    {
        $this->checkPermission('inventory.manage');
        if ($product->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $validated = $request->validate([
            'type' => 'required|in:purchase,sale,adjustment,adjustment_in,adjustment_out,return_in,return_out',
            'quantity' => 'required|numeric|min:0.01',
            'unit_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $movement = $inventoryService->updateStock(
                $product,
                (int) $validated['quantity'],
                $validated['type'],
                [
                    'unit_cost' => $validated['unit_cost'],
                    'notes' => $validated['notes'],
                ]
            );

            return back()->with('success', 'Stock actualizado exitosamente.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function inventoryReport(Request $request, InventoryService $inventoryService)
    {
        $this->checkPermission('reports.view');
        $tenantId = auth()->user()->tenant_id;
        
        $filters = $request->only(['product_id', 'type', 'date_from', 'date_to', 'per_page']);
        
        // Los movimientos no se cachean porque pueden tener paginación
        $movements = $inventoryService->getInventoryMovements($tenantId, $filters);
        
        // Cache key para el reporte de inventario
        $cacheKey = "inventory:report:{$tenantId}";
        $cacheTTL = 300; // 5 minutos
        
        // Productos con bajo stock con caché
        $lowStockProducts = Cache::remember($cacheKey . ':low_stock', $cacheTTL, function() use ($inventoryService, $tenantId) {
            return $inventoryService->getLowStockProducts($tenantId);
        });
        
        // Productos sin stock con caché
        $outOfStockProducts = Cache::remember($cacheKey . ':out_of_stock', $cacheTTL, function() use ($inventoryService, $tenantId) {
            return $inventoryService->getOutOfStockProducts($tenantId);
        });
        
        // Productos para reordenar con caché
        $reorderProducts = Cache::remember($cacheKey . ':reorder', $cacheTTL, function() use ($inventoryService, $tenantId) {
            return $inventoryService->getProductsForReorder($tenantId);
        });
        
        // Valuación del inventario con caché
        $valuation = Cache::remember($cacheKey . ':valuation', $cacheTTL, function() use ($inventoryService, $tenantId) {
            return $inventoryService->getInventoryValuation($tenantId);
        });

        return Inertia::render('Products/InventoryReport', [
            'movements' => $movements,
            'lowStockProducts' => $lowStockProducts,
            'outOfStockProducts' => $outOfStockProducts,
            'reorderProducts' => $reorderProducts,
            'valuation' => $valuation,
            'filters' => $filters,
            'products' => Product::where('tenant_id', $tenantId)
                ->where('is_service', false)
                ->select('id', 'code', 'name')
                ->orderBy('name')
                ->get(),
        ]);
    }
}