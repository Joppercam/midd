<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class POSController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:pos.terminal.access')->only(['index', 'terminal']);
        $this->middleware('permission:pos.sales.create')->only(['store', 'sale']);
    }

    /**
     * POS Terminal interface
     */
    public function index(Request $request)
    {
        try {
            $tenantId = auth()->user()->tenant_id;
            
            // Get products for POS
            $products = Product::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->with(['category'])
                ->orderBy('name')
                ->get()
                ->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'description' => $product->description,
                        'price' => $product->sale_price ?? $product->price,
                        'cost' => $product->cost,
                        'quantity' => $product->quantity ?? 0,
                        'barcode' => $product->barcode,
                        'category' => $product->category ? $product->category->name : null,
                        'category_id' => $product->category_id,
                        'image' => $product->image_path,
                    ];
                });

            // Get categories
            $categories = Category::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->select('id', 'name', 'color')
                ->get();

            // Get customers
            $customers = Customer::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->select('id', 'name', 'email', 'rut', 'phone')
                ->limit(50)
                ->get();

            // Get current session info
            $currentSession = [
                'id' => 1,
                'cashier' => auth()->user()->name,
                'terminal' => 'Terminal Principal',
                'opened_at' => now()->format('Y-m-d H:i:s'),
                'opening_balance' => 0,
                'current_balance' => 0,
            ];

            return Inertia::render('POS/Terminal', [
                'products' => $products,
                'categories' => $categories,
                'customers' => $customers,
                'session' => $currentSession,
                'settings' => $this->getPOSSettings(),
            ]);
        } catch (\Exception $e) {
            \Log::error('POS Terminal error: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Search products for POS
     */
    public function searchProducts(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $search = $request->get('search', '');

        $products = Product::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('barcode', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%");
            })
            ->with(['category'])
            ->limit(20)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->sale_price ?? $product->price,
                    'quantity' => $product->quantity ?? 0,
                    'barcode' => $product->barcode,
                    'category' => $product->category ? $product->category->name : null,
                ];
            });

        return response()->json($products);
    }

    /**
     * Process sale
     */
    public function sale(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|min:0',
            'customer_id' => 'nullable|exists:customers,id',
            'payment_method' => 'required|in:cash,card,transfer',
            'payment_amount' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:255',
        ]);

        try {
            $tenantId = auth()->user()->tenant_id;
            
            // Calculate totals
            $subtotal = 0;
            foreach ($request->items as $item) {
                $subtotal += $item['quantity'] * $item['price'];
            }
            
            $discount = $request->discount ?? 0;
            $total = $subtotal - $discount;
            $tax = $total * 0.19; // 19% IVA Chile
            $totalWithTax = $total + $tax;

            // Create sale record (simplified)
            $sale = [
                'id' => rand(1000, 9999), // Temporary ID
                'sale_number' => 'POS-' . now()->format('YmdHis'),
                'date' => now()->format('Y-m-d H:i:s'),
                'cashier' => auth()->user()->name,
                'customer_id' => $request->customer_id,
                'items' => $request->items,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $totalWithTax,
                'payment_method' => $request->payment_method,
                'payment_amount' => $request->payment_amount,
                'change' => max(0, $request->payment_amount - $totalWithTax),
                'notes' => $request->notes,
                'status' => 'completed',
            ];

            // TODO: Save to database
            // TODO: Update product quantities
            // TODO: Create invoice/receipt

            return response()->json([
                'success' => true,
                'sale' => $sale,
                'message' => 'Venta procesada exitosamente',
            ]);
        } catch (\Exception $e) {
            \Log::error('POS Sale error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la venta: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Search customers
     */
    public function searchCustomers(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $search = $request->get('search', '');

        $customers = Customer::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('rut', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
            })
            ->select('id', 'name', 'email', 'rut', 'phone')
            ->limit(10)
            ->get();

        return response()->json($customers);
    }

    /**
     * Get products by category
     */
    public function productsByCategory(Request $request, $categoryId = null)
    {
        $tenantId = auth()->user()->tenant_id;
        
        $query = Product::where('tenant_id', $tenantId)
            ->where('is_active', true);

        if ($categoryId && $categoryId !== 'all') {
            $query->where('category_id', $categoryId);
        }

        $products = $query->with(['category'])
            ->orderBy('name')
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->sale_price ?? $product->price,
                    'quantity' => $product->quantity ?? 0,
                    'barcode' => $product->barcode,
                    'category' => $product->category ? $product->category->name : null,
                    'image' => $product->image_path,
                ];
            });

        return response()->json($products);
    }

    /**
     * Cash register operations
     */
    public function cashRegister()
    {
        try {
            $tenantId = auth()->user()->tenant_id;
            
            // TODO: Implement real cash register data from database
            // For now, return basic structure that matches the Vue component expectations
            
            return Inertia::render('POS/CashRegister', [
                'currentSession' => [
                    'status' => 'open', // This should come from actual session data
                    'cashier' => auth()->user()->name,
                    'opened_at' => now()->format('Y-m-d H:i:s'),
                    'opening_balance' => 50000,
                    'current_balance' => 125000,
                ],
                'todayStats' => [
                    'total_sales' => 0,
                    'cash_total' => 0,
                    'card_total' => 0,
                    'transfer_total' => 0,
                ],
                'recentMovements' => [],
            ]);
        } catch (\Exception $e) {
            \Log::error('POS Cash Register error: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Sales history
     */
    public function sales()
    {
        try {
            $tenantId = auth()->user()->tenant_id;
            
            // TODO: Implement real sales data from database
            // For now, return empty data structure that matches the Vue component expectations
            
            return Inertia::render('POS/Sales', [
                'sales' => [],
                'statistics' => [
                    'today_sales_count' => 0,
                    'today_total' => 0,
                    'today_products_sold' => 0,
                    'average_sale' => 0,
                ],
                'filters' => request()->only(['date_from', 'date_to', 'payment_method']),
            ]);
        } catch (\Exception $e) {
            \Log::error('POS Sales error: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get POS settings
     */
    protected function getPOSSettings(): array
    {
        return [
            'currency' => 'CLP',
            'currency_symbol' => '$',
            'tax_rate' => 0.19,
            'tax_name' => 'IVA',
            'receipt_header' => 'Mi Empresa',
            'receipt_footer' => 'Gracias por su compra',
            'auto_print_receipt' => false,
            'barcode_scanner' => true,
            'cash_rounding' => true,
            'max_discount_percent' => 20,
        ];
    }
}