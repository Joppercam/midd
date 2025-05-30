<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="Product",
 *     type="object",
 *     required={"id", "sku", "name", "unit_price", "tenant_id"},
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="tenant_id", type="integer"),
 *     @OA\Property(property="sku", type="string", example="PROD-001"),
 *     @OA\Property(property="name", type="string", example="Producto de Ejemplo"),
 *     @OA\Property(property="description", type="string"),
 *     @OA\Property(property="category_id", type="integer"),
 *     @OA\Property(property="unit_price", type="number", format="float", example=15000),
 *     @OA\Property(property="cost_price", type="number", format="float", example=10000),
 *     @OA\Property(property="tax_rate", type="number", format="float", example=19),
 *     @OA\Property(property="currency", type="string", example="CLP"),
 *     @OA\Property(property="unit_of_measure", type="string", example="UN"),
 *     @OA\Property(property="barcode", type="string"),
 *     @OA\Property(property="track_inventory", type="boolean", example=true),
 *     @OA\Property(property="current_stock", type="number", format="float", example=100),
 *     @OA\Property(property="minimum_stock", type="number", format="float", example=10),
 *     @OA\Property(property="maximum_stock", type="number", format="float", example=500),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 * 
 * @OA\Schema(
 *     schema="InventoryMovement",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="product_id", type="integer"),
 *     @OA\Property(property="type", type="string", enum={"purchase", "sale", "adjustment", "return", "damage", "transfer"}),
 *     @OA\Property(property="quantity", type="number", format="float"),
 *     @OA\Property(property="balance_before", type="number", format="float"),
 *     @OA\Property(property="balance_after", type="number", format="float"),
 *     @OA\Property(property="unit_cost", type="number", format="float"),
 *     @OA\Property(property="total_cost", type="number", format="float"),
 *     @OA\Property(property="reference_type", type="string"),
 *     @OA\Property(property="reference_id", type="integer"),
 *     @OA\Property(property="notes", type="string"),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 */
class ProductApiController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/api/v1/products",
     *     summary="Listar productos",
     *     description="Obtiene una lista paginada de productos con filtros opcionales",
     *     operationId="getProducts",
     *     tags={"Productos"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Buscar por nombre, SKU o descripción",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filtrar por categoría",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Filtrar por estado activo",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="track_inventory",
     *         in="query",
     *         description="Filtrar productos con control de inventario",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="low_stock",
     *         in="query",
     *         description="Solo productos con stock bajo",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de productos",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Product")),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::where('tenant_id', $this->getTenantId($request))
            ->with('category');
        
        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        
        // Filter by status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }
        
        // Filter by inventory tracking
        if ($request->has('track_inventory')) {
            $query->where('track_inventory', $request->boolean('track_inventory'));
        }
        
        // Filter by low stock
        if ($request->boolean('low_stock')) {
            $query->whereRaw('current_stock <= minimum_stock');
        }
        
        $query = $this->applyFilters($request, $query);
        
        $products = $query->paginate($request->get('per_page', 15));
        
        return $this->paginated($products);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $product = Product::where('tenant_id', $this->getTenantId($request))
            ->with(['category', 'inventoryMovements' => function ($query) {
                $query->latest()->limit(10);
            }])
            ->find($id);
            
        if (!$product) {
            return $this->notFound('Producto no encontrado');
        }
        
        return $this->success($product);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => [
                'required',
                'string',
                'max:50',
                Rule::unique('products')->where(function ($query) use ($request) {
                    return $query->where('tenant_id', $this->getTenantId($request));
                })
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category_id' => 'nullable|exists:categories,id',
            'unit_price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'track_inventory' => 'boolean',
            'current_stock' => 'required_if:track_inventory,true|numeric|min:0',
            'minimum_stock' => 'nullable|numeric|min:0',
            'is_active' => 'boolean'
        ]);
        
        // Verify category belongs to tenant
        if (isset($validated['category_id'])) {
            $category = Category::where('tenant_id', $this->getTenantId($request))
                ->find($validated['category_id']);
                
            if (!$category) {
                return $this->forbidden('Categoría no pertenece a su empresa');
            }
        }
        
        $validated['tenant_id'] = $this->getTenantId($request);
        
        $product = Product::create($validated);
        
        // Record initial stock if tracking inventory
        if ($product->track_inventory && $product->current_stock > 0) {
            $product->recordMovement(
                $product->current_stock,
                'initial',
                'Stock inicial'
            );
        }
        
        return $this->created($product->load('category'), 'Producto creado exitosamente');
    }

    public function update(Request $request, $id): JsonResponse
    {
        $product = Product::where('tenant_id', $this->getTenantId($request))
            ->find($id);
            
        if (!$product) {
            return $this->notFound('Producto no encontrado');
        }
        
        $validated = $request->validate([
            'sku' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('products')->where(function ($query) use ($request) {
                    return $query->where('tenant_id', $this->getTenantId($request));
                })->ignore($product->id)
            ],
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category_id' => 'nullable|exists:categories,id',
            'unit_price' => 'sometimes|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'track_inventory' => 'boolean',
            'minimum_stock' => 'nullable|numeric|min:0',
            'is_active' => 'boolean'
        ]);
        
        // Verify category belongs to tenant
        if (isset($validated['category_id'])) {
            $category = Category::where('tenant_id', $this->getTenantId($request))
                ->find($validated['category_id']);
                
            if (!$category) {
                return $this->forbidden('Categoría no pertenece a su empresa');
            }
        }
        
        $product->update($validated);
        
        return $this->updated($product->load('category'), 'Producto actualizado exitosamente');
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $product = Product::where('tenant_id', $this->getTenantId($request))
            ->find($id);
            
        if (!$product) {
            return $this->notFound('Producto no encontrado');
        }
        
        // Check if product has been used in invoices
        if ($product->taxDocumentItems()->exists()) {
            return $this->error('No se puede eliminar un producto que ha sido utilizado en facturas', 409);
        }
        
        $product->delete();
        
        return $this->deleted('Producto eliminado exitosamente');
    }

    public function updateStock(Request $request, $id): JsonResponse
    {
        $product = Product::where('tenant_id', $this->getTenantId($request))
            ->find($id);
            
        if (!$product) {
            return $this->notFound('Producto no encontrado');
        }
        
        if (!$product->track_inventory) {
            return $this->error('Este producto no tiene control de inventario', 409);
        }
        
        $validated = $request->validate([
            'quantity' => 'required|numeric|not_in:0',
            'type' => 'required|in:adjustment,purchase,return,damage,other',
            'reason' => 'required|string|max:255',
            'reference' => 'nullable|string|max:100'
        ]);
        
        $movement = $product->recordMovement(
            $validated['quantity'],
            $validated['type'],
            $validated['reason'],
            $validated['reference'] ?? null
        );
        
        return $this->success([
            'product_id' => $product->id,
            'new_stock' => $product->fresh()->current_stock,
            'movement' => $movement
        ], 'Stock actualizado exitosamente');
    }

    public function stockMovements(Request $request, $id): JsonResponse
    {
        $product = Product::where('tenant_id', $this->getTenantId($request))
            ->find($id);
            
        if (!$product) {
            return $this->notFound('Producto no encontrado');
        }
        
        $query = $product->inventoryMovements()
            ->orderBy('created_at', 'desc');
            
        if ($request->has('type')) {
            $query->where('movement_type', $request->type);
        }
        
        $movements = $query->paginate($request->get('per_page', 15));
        
        return $this->paginated($movements);
    }

    public function lowStock(Request $request): JsonResponse
    {
        $products = Product::where('tenant_id', $this->getTenantId($request))
            ->where('track_inventory', true)
            ->whereRaw('current_stock <= minimum_stock')
            ->with('category')
            ->get();
            
        return $this->success($products);
    }
}