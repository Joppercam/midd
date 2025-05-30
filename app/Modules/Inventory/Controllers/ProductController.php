<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Traits\ChecksPermissions;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Inventory\Services\ProductService;
use App\Modules\Inventory\Requests\ProductRequest;
use App\Modules\Inventory\Requests\StockAdjustmentRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    use ChecksPermissions;
    
    protected $inventoryService;
    protected $productService;
    
    public function __construct(
        InventoryService $inventoryService,
        ProductService $productService
    ) {
        $this->middleware(['auth', 'verified', 'check.subscription', 'check.module:inventory']);
        $this->inventoryService = $inventoryService;
        $this->productService = $productService;
    }
    
    public function index(Request $request)
    {
        $this->checkPermission('products.view');
        
        $filters = $request->only(['search', 'category_id', 'stock_status', 'is_service', 'sort', 'direction']);
        $products = $this->productService->getProductsList($filters);
        $stats = $this->productService->getProductsStatistics();
        $categories = $this->productService->getCategories();

        return Inertia::render('Inventory/Products/Index', [
            'products' => $products,
            'filters' => $filters,
            'stats' => $stats,
            'categories' => $categories,
            'stockStatusOptions' => [
                'in_stock' => 'En Stock',
                'low_stock' => 'Stock Bajo',
                'out_of_stock' => 'Sin Stock'
            ]
        ]);
    }

    public function create()
    {
        $this->checkPermission('products.create');
        
        $categories = $this->productService->getCategories();
        $units = config('inventory.units_of_measure', []);
        $valuationMethods = config('inventory.valuation_methods.available', []);

        return Inertia::render('Inventory/Products/Create', [
            'categories' => $categories,
            'units' => $units,
            'valuationMethods' => $valuationMethods,
            'barcodeFormats' => ['EAN13', 'EAN8', 'UPC', 'CODE128', 'CODE39'],
        ]);
    }

    public function store(ProductRequest $request)
    {
        $this->checkPermission('products.create');
        
        DB::beginTransaction();
        try {
            $product = $this->productService->createProduct($request->validated());
            
            // Create initial stock movement if needed
            if (!$product->is_service && $product->stock_quantity > 0) {
                $this->inventoryService->createInitialStock($product);
            }
            
            DB::commit();

            return redirect()->route('inventory.products.show', $product)
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

        $productData = $this->productService->getProductDetails($product);
        
        return Inertia::render('Inventory/Products/Show', $productData);
    }

    public function edit(Product $product)
    {
        $this->checkPermission('products.edit');
        
        if ($product->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $categories = $this->productService->getCategories();
        $units = config('inventory.units_of_measure', []);
        $valuationMethods = config('inventory.valuation_methods.available', []);

        return Inertia::render('Inventory/Products/Edit', [
            'product' => $product,
            'categories' => $categories,
            'units' => $units,
            'valuationMethods' => $valuationMethods,
            'barcodeFormats' => ['EAN13', 'EAN8', 'UPC', 'CODE128', 'CODE39'],
        ]);
    }

    public function update(ProductRequest $request, Product $product)
    {
        $this->checkPermission('products.edit');
        
        if ($product->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $product = $this->productService->updateProduct($product, $request->validated());

        return redirect()->route('inventory.products.show', $product)
            ->with('success', 'Producto actualizado exitosamente.');
    }

    public function destroy(Product $product)
    {
        $this->checkPermission('products.delete');
        
        if ($product->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        $result = $this->productService->deleteProduct($product);
        
        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        return redirect()->route('inventory.products.index')
            ->with('success', 'Producto eliminado exitosamente.');
    }

    public function updateStock(StockAdjustmentRequest $request, Product $product)
    {
        $this->checkPermission('products.update_stock');
        
        if ($product->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        try {
            $movement = $this->inventoryService->adjustStock(
                $product,
                $request->validated()
            );

            return back()->with('success', 'Stock actualizado exitosamente.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function updatePrices(Request $request, Product $product)
    {
        $this->checkPermission('products.update_prices');
        
        if ($product->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $request->validate([
            'price' => 'required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'reason' => 'required|string|max:500',
            'apply_to_existing_orders' => 'boolean'
        ]);

        $result = $this->productService->updatePrices(
            $product,
            $request->only(['price', 'cost', 'reason', 'apply_to_existing_orders'])
        );

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', 'Precios actualizados exitosamente.');
    }

    public function import(Request $request)
    {
        $this->checkPermission('products.import');
        
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls|max:10240',
            'update_existing' => 'boolean',
            'create_categories' => 'boolean'
        ]);

        $result = $this->productService->importProducts(
            $request->file('file'),
            $request->boolean('update_existing', false),
            $request->boolean('create_categories', false)
        );

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', $result['message']);
    }

    public function export(Request $request)
    {
        $this->checkPermission('products.export');
        
        $filters = $request->only(['search', 'category_id', 'stock_status', 'is_service']);
        
        return $this->productService->exportProducts($filters);
    }

    public function barcode(Product $product)
    {
        $this->checkPermission('products.barcode');
        
        if ($product->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        return $this->productService->generateBarcode($product);
    }

    public function barcodeLabels(Request $request)
    {
        $this->checkPermission('products.barcode');
        
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
            'quantities' => 'required|array',
            'quantities.*' => 'integer|min:1',
            'label_format' => 'required|in:standard,small,large'
        ]);

        return $this->productService->generateBarcodeLabels(
            $request->get('product_ids'),
            $request->get('quantities'),
            $request->get('label_format')
        );
    }

    public function inventoryReport(Request $request)
    {
        $this->checkPermission('inventory.reports');
        
        $filters = $request->only(['product_id', 'movement_type', 'date_from', 'date_to', 'location']);
        
        $report = $this->inventoryService->generateInventoryReport($filters);
        $valuationReport = $this->inventoryService->getInventoryValuation();
        $movementSummary = $this->inventoryService->getMovementSummary($filters);
        $abcAnalysis = $this->inventoryService->performABCAnalysis();

        return Inertia::render('Inventory/Reports/InventoryReport', [
            'report' => $report,
            'valuation' => $valuationReport,
            'movementSummary' => $movementSummary,
            'abcAnalysis' => $abcAnalysis,
            'filters' => $filters,
            'products' => $this->productService->getProductsForSelect(),
            'movementTypes' => config('inventory.movement_types', []),
            'locations' => $this->inventoryService->getLocations(),
        ]);
    }

    public function stockAlerts()
    {
        $this->checkPermission('inventory.alerts');
        
        $alerts = $this->inventoryService->getStockAlerts();
        
        return Inertia::render('Inventory/StockAlerts', [
            'alerts' => $alerts,
            'alertSettings' => config('inventory.inventory_alerts', [])
        ]);
    }

    public function updateMinimumStock(Request $request)
    {
        $this->checkPermission('stock.minimum_levels');
        
        $request->validate([
            'products' => 'required|array',
            'products.*.id' => 'required|exists:products,id',
            'products.*.minimum_stock' => 'required|numeric|min:0',
            'products.*.reorder_point' => 'nullable|numeric|min:0',
            'products.*.maximum_stock' => 'nullable|numeric|min:0'
        ]);

        $result = $this->productService->bulkUpdateStockLevels($request->get('products'));

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', 'Niveles de stock actualizados exitosamente.');
    }

    public function getMovementHistory(Product $product)
    {
        $this->checkPermission('inventory.movements');
        
        if ($product->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $movements = $this->inventoryService->getProductMovementHistory($product);

        return response()->json([
            'success' => true,
            'movements' => $movements
        ]);
    }
}