<?php

namespace App\Http\Controllers\PurchaseOrders;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = session('tenant_id');
        
        $query = PurchaseOrder::where('tenant_id', $tenantId)
            ->with(['supplier', 'user', 'items'])
            ->search($request->search)
            ->status($request->status)
            ->dateRange($request->start_date, $request->end_date);

        if ($request->supplier_id) {
            $query->where('supplier_id', $request->supplier_id);
        }

        $orders = $query->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        $stats = PurchaseOrder::where('tenant_id', $tenantId)
            ->selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'draft' THEN 1 END) as total_draft,
                COUNT(CASE WHEN status = 'sent' THEN 1 END) as total_sent,
                COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as total_confirmed,
                COUNT(CASE WHEN status = 'partial' THEN 1 END) as total_partial,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as total_completed,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as total_cancelled,
                COALESCE(SUM(CASE WHEN status NOT IN ('cancelled') THEN total END), 0) as total_amount,
                COALESCE(SUM(CASE WHEN status = 'confirmed' THEN total END), 0) as pending_amount
            ")
            ->first();

        $suppliers = Supplier::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name', 'rut']);

        return Inertia::render('PurchaseOrders/Index', [
            'orders' => $orders,
            'stats' => $stats,
            'suppliers' => $suppliers,
            'filters' => [
                'search' => $request->search,
                'status' => $request->status,
                'supplier_id' => $request->supplier_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
            ],
        ]);
    }

    public function create()
    {
        $tenantId = session('tenant_id');
        
        $suppliers = Supplier::where('tenant_id', $tenantId)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'rut', 'email', 'phone']);

        $products = Product::where('tenant_id', $tenantId)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'price', 'stock']);

        return Inertia::render('PurchaseOrders/Create', [
            'suppliers' => $suppliers,
            'products' => $products,
            'defaultTerms' => 'Términos y condiciones estándar de compra.',
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'order_date' => 'required|date',
            'expected_date' => 'nullable|date|after_or_equal:order_date',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'currency' => 'required|string|size:3',
            'exchange_rate' => 'required|numeric|min:0.0001',
            'shipping_method' => 'nullable|string|max:255',
            'shipping_address' => 'nullable|string',
            'billing_address' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.description' => 'required|string|max:500',
            'items.*.sku' => 'nullable|string|max:100',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit' => 'required|string|max:50',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_rate' => 'required|numeric|min:0|max:100',
        ]);

        try {
            DB::beginTransaction();

            $order = PurchaseOrder::create([
                'tenant_id' => session('tenant_id'),
                'supplier_id' => $validated['supplier_id'],
                'user_id' => auth()->id(),
                'order_date' => $validated['order_date'],
                'expected_date' => $validated['expected_date'],
                'reference' => $validated['reference'],
                'notes' => $validated['notes'],
                'terms' => $validated['terms'],
                'currency' => $validated['currency'],
                'exchange_rate' => $validated['exchange_rate'],
                'shipping_method' => $validated['shipping_method'],
                'shipping_address' => $validated['shipping_address'],
                'billing_address' => $validated['billing_address'],
                'status' => 'draft',
            ]);

            foreach ($validated['items'] as $index => $itemData) {
                $order->items()->create([
                    'product_id' => $itemData['product_id'],
                    'description' => $itemData['description'],
                    'sku' => $itemData['sku'] ?? null,
                    'quantity' => $itemData['quantity'],
                    'unit' => $itemData['unit'],
                    'unit_price' => $itemData['unit_price'],
                    'discount_percent' => $itemData['discount_percent'] ?? 0,
                    'tax_rate' => $itemData['tax_rate'],
                    'position' => $index + 1,
                ]);
            }

            $order->calculateTotals()->save();

            DB::commit();

            return redirect()->route('purchase-orders.show', $order)
                ->with('success', 'Orden de compra creada exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al crear la orden de compra: ' . $e->getMessage());
        }
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('view', $purchaseOrder);
        
        $purchaseOrder->load(['supplier', 'user', 'items.product', 'receipts.items']);

        return Inertia::render('PurchaseOrders/Show', [
            'order' => $purchaseOrder,
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('update', $purchaseOrder);
        
        if (!in_array($purchaseOrder->status, ['draft'])) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->with('error', 'Solo se pueden editar órdenes en estado borrador.');
        }

        $purchaseOrder->load(['supplier', 'items']);
        
        $tenantId = session('tenant_id');
        $suppliers = Supplier::where('tenant_id', $tenantId)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'rut', 'email', 'phone']);

        $products = Product::where('tenant_id', $tenantId)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'price', 'stock']);

        return Inertia::render('PurchaseOrders/Edit', [
            'order' => $purchaseOrder,
            'suppliers' => $suppliers,
            'products' => $products,
        ]);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorize('update', $purchaseOrder);
        
        if (!in_array($purchaseOrder->status, ['draft'])) {
            return back()->with('error', 'Solo se pueden editar órdenes en estado borrador.');
        }

        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'order_date' => 'required|date',
            'expected_date' => 'nullable|date|after_or_equal:order_date',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'currency' => 'required|string|size:3',
            'exchange_rate' => 'required|numeric|min:0.0001',
            'shipping_method' => 'nullable|string|max:255',
            'shipping_address' => 'nullable|string',
            'billing_address' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|exists:purchase_order_items,id',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.description' => 'required|string|max:500',
            'items.*.sku' => 'nullable|string|max:100',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit' => 'required|string|max:50',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_rate' => 'required|numeric|min:0|max:100',
        ]);

        try {
            DB::beginTransaction();

            $purchaseOrder->update([
                'supplier_id' => $validated['supplier_id'],
                'order_date' => $validated['order_date'],
                'expected_date' => $validated['expected_date'],
                'reference' => $validated['reference'],
                'notes' => $validated['notes'],
                'terms' => $validated['terms'],
                'currency' => $validated['currency'],
                'exchange_rate' => $validated['exchange_rate'],
                'shipping_method' => $validated['shipping_method'],
                'shipping_address' => $validated['shipping_address'],
                'billing_address' => $validated['billing_address'],
            ]);

            // Obtener IDs de items existentes
            $existingItemIds = collect($validated['items'])
                ->pluck('id')
                ->filter()
                ->toArray();

            // Eliminar items que no están en la lista
            $purchaseOrder->items()
                ->whereNotIn('id', $existingItemIds)
                ->delete();

            // Actualizar o crear items
            foreach ($validated['items'] as $index => $itemData) {
                if (isset($itemData['id'])) {
                    $purchaseOrder->items()
                        ->where('id', $itemData['id'])
                        ->update([
                            'product_id' => $itemData['product_id'],
                            'description' => $itemData['description'],
                            'sku' => $itemData['sku'] ?? null,
                            'quantity' => $itemData['quantity'],
                            'unit' => $itemData['unit'],
                            'unit_price' => $itemData['unit_price'],
                            'discount_percent' => $itemData['discount_percent'] ?? 0,
                            'tax_rate' => $itemData['tax_rate'],
                            'position' => $index + 1,
                        ]);
                } else {
                    $purchaseOrder->items()->create([
                        'product_id' => $itemData['product_id'],
                        'description' => $itemData['description'],
                        'sku' => $itemData['sku'] ?? null,
                        'quantity' => $itemData['quantity'],
                        'unit' => $itemData['unit'],
                        'unit_price' => $itemData['unit_price'],
                        'discount_percent' => $itemData['discount_percent'] ?? 0,
                        'tax_rate' => $itemData['tax_rate'],
                        'position' => $index + 1,
                    ]);
                }
            }

            $purchaseOrder->calculateTotals()->save();

            DB::commit();

            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->with('success', 'Orden de compra actualizada exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al actualizar la orden de compra: ' . $e->getMessage());
        }
    }

    public function send(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('update', $purchaseOrder);
        
        if (!$purchaseOrder->canBeSent()) {
            return back()->with('error', 'Esta orden no puede ser enviada en su estado actual.');
        }

        $purchaseOrder->update(['status' => 'sent']);

        // TODO: Implementar envío por email

        return back()->with('success', 'Orden de compra enviada exitosamente.');
    }

    public function confirm(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('update', $purchaseOrder);
        
        if (!$purchaseOrder->canBeConfirmed()) {
            return back()->with('error', 'Esta orden no puede ser confirmada en su estado actual.');
        }

        $purchaseOrder->update(['status' => 'confirmed']);

        return back()->with('success', 'Orden de compra confirmada exitosamente.');
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorize('update', $purchaseOrder);
        
        if (!$purchaseOrder->canBeCancelled()) {
            return back()->with('error', 'Esta orden no puede ser cancelada en su estado actual.');
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $purchaseOrder->update([
            'status' => 'cancelled',
            'cancelled_reason' => $validated['reason'],
        ]);

        return back()->with('success', 'Orden de compra cancelada exitosamente.');
    }

    public function pdf(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('view', $purchaseOrder);
        
        $purchaseOrder->load(['supplier', 'items', 'user']);

        $pdf = Pdf::loadView('purchase-orders.pdf', [
            'order' => $purchaseOrder,
        ]);

        return $pdf->download("orden-compra-{$purchaseOrder->order_number}.pdf");
    }

    public function duplicate(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('create', PurchaseOrder::class);
        
        try {
            DB::beginTransaction();

            $newOrder = $purchaseOrder->replicate([
                'order_number',
                'status',
                'sent_at',
                'confirmed_at',
                'completed_at',
                'cancelled_at',
                'cancelled_reason',
            ]);

            $newOrder->status = 'draft';
            $newOrder->order_date = now();
            $newOrder->user_id = auth()->id();
            $newOrder->save();

            foreach ($purchaseOrder->items as $item) {
                $newItem = $item->replicate(['quantity_received']);
                $newItem->purchase_order_id = $newOrder->id;
                $newItem->save();
            }

            DB::commit();

            return redirect()->route('purchase-orders.edit', $newOrder)
                ->with('success', 'Orden de compra duplicada exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al duplicar la orden: ' . $e->getMessage());
        }
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('delete', $purchaseOrder);
        
        if ($purchaseOrder->status !== 'draft') {
            return back()->with('error', 'Solo se pueden eliminar órdenes en estado borrador.');
        }

        if ($purchaseOrder->receipts()->exists()) {
            return back()->with('error', 'No se puede eliminar una orden con recepciones.');
        }

        $purchaseOrder->delete();

        return redirect()->route('purchase-orders.index')
            ->with('success', 'Orden de compra eliminada exitosamente.');
    }
}