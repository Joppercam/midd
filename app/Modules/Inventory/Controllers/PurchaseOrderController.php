<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Product;
use App\Traits\ChecksPermissions;
use App\Modules\Inventory\Services\PurchaseOrderService;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Inventory\Requests\PurchaseOrderRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class PurchaseOrderController extends Controller
{
    use ChecksPermissions;
    
    protected $purchaseOrderService;
    protected $inventoryService;
    
    public function __construct(
        PurchaseOrderService $purchaseOrderService,
        InventoryService $inventoryService
    ) {
        $this->middleware(['auth', 'verified', 'check.subscription', 'check.module:inventory']);
        $this->purchaseOrderService = $purchaseOrderService;
        $this->inventoryService = $inventoryService;
    }

    public function index(Request $request)
    {
        $this->checkPermission('purchase_orders.view');
        
        $filters = $request->only(['search', 'status', 'supplier_id', 'start_date', 'end_date']);
        $orders = $this->purchaseOrderService->getPurchaseOrdersList($filters);
        $stats = $this->purchaseOrderService->getPurchaseOrdersStatistics();
        $suppliers = $this->purchaseOrderService->getActiveSuppliers();

        return Inertia::render('Inventory/PurchaseOrders/Index', [
            'orders' => $orders,
            'stats' => $stats,
            'suppliers' => $suppliers,
            'filters' => $filters,
            'statusOptions' => config('inventory.purchase_order_settings.status_workflow', []),
        ]);
    }

    public function create()
    {
        $this->checkPermission('purchase_orders.create');
        
        $suppliers = $this->purchaseOrderService->getActiveSuppliers();
        $products = $this->purchaseOrderService->getActiveProducts();
        $paymentTerms = config('inventory.purchase_order_settings.payment_terms_options', []);
        $currencies = $this->purchaseOrderService->getAvailableCurrencies();

        return Inertia::render('Inventory/PurchaseOrders/Create', [
            'suppliers' => $suppliers,
            'products' => $products,
            'paymentTerms' => $paymentTerms,
            'currencies' => $currencies,
            'defaultTerms' => $this->purchaseOrderService->getDefaultTerms(),
            'approvalThresholds' => config('inventory.purchase_order_settings.approval_thresholds', []),
        ]);
    }

    public function store(PurchaseOrderRequest $request)
    {
        $this->checkPermission('purchase_orders.create');
        
        try {
            DB::beginTransaction();
            
            $order = $this->purchaseOrderService->createPurchaseOrder($request->validated());
            
            DB::commit();

            return redirect()->route('inventory.purchase-orders.show', $order)
                ->with('success', 'Orden de compra creada exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al crear la orden de compra: ' . $e->getMessage());
        }
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $this->checkPermission('purchase_orders.view');
        
        if ($purchaseOrder->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        $orderData = $this->purchaseOrderService->getPurchaseOrderDetails($purchaseOrder);
        
        return Inertia::render('Inventory/PurchaseOrders/Show', $orderData);
    }

    public function edit(PurchaseOrder $purchaseOrder)
    {
        $this->checkPermission('purchase_orders.edit');
        
        if ($purchaseOrder->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        if (!$this->purchaseOrderService->canEdit($purchaseOrder)) {
            return redirect()->route('inventory.purchase-orders.show', $purchaseOrder)
                ->with('error', 'Solo se pueden editar órdenes en estado borrador.');
        }

        $suppliers = $this->purchaseOrderService->getActiveSuppliers();
        $products = $this->purchaseOrderService->getActiveProducts();
        $paymentTerms = config('inventory.purchase_order_settings.payment_terms_options', []);
        $currencies = $this->purchaseOrderService->getAvailableCurrencies();

        return Inertia::render('Inventory/PurchaseOrders/Edit', [
            'order' => $purchaseOrder->load(['supplier', 'items.product']),
            'suppliers' => $suppliers,
            'products' => $products,
            'paymentTerms' => $paymentTerms,
            'currencies' => $currencies,
        ]);
    }

    public function update(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder)
    {
        $this->checkPermission('purchase_orders.edit');
        
        if ($purchaseOrder->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        if (!$this->purchaseOrderService->canEdit($purchaseOrder)) {
            return back()->with('error', 'Solo se pueden editar órdenes en estado borrador.');
        }

        try {
            DB::beginTransaction();
            
            $order = $this->purchaseOrderService->updatePurchaseOrder($purchaseOrder, $request->validated());
            
            DB::commit();

            return redirect()->route('inventory.purchase-orders.show', $order)
                ->with('success', 'Orden de compra actualizada exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al actualizar la orden de compra: ' . $e->getMessage());
        }
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        $this->checkPermission('purchase_orders.delete');
        
        if ($purchaseOrder->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        $result = $this->purchaseOrderService->deletePurchaseOrder($purchaseOrder);
        
        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        return redirect()->route('inventory.purchase-orders.index')
            ->with('success', 'Orden de compra eliminada exitosamente.');
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->checkPermission('purchase_orders.approve');
        
        if ($purchaseOrder->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $request->validate([
            'notes' => 'nullable|string|max:500'
        ]);

        $result = $this->purchaseOrderService->approvePurchaseOrder(
            $purchaseOrder,
            $request->get('notes')
        );

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', 'Orden de compra aprobada exitosamente.');
    }

    public function reject(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->checkPermission('purchase_orders.approve');
        
        if ($purchaseOrder->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        $result = $this->purchaseOrderService->rejectPurchaseOrder(
            $purchaseOrder,
            $request->get('reason')
        );

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', 'Orden de compra rechazada.');
    }

    public function send(PurchaseOrder $purchaseOrder)
    {
        $this->checkPermission('purchase_orders.edit');
        
        if ($purchaseOrder->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $result = $this->purchaseOrderService->sendPurchaseOrder($purchaseOrder);

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', 'Orden de compra enviada exitosamente.');
    }

    public function receive(PurchaseOrder $purchaseOrder)
    {
        $this->checkPermission('purchase_orders.receive');
        
        if ($purchaseOrder->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        if (!$this->purchaseOrderService->canReceive($purchaseOrder)) {
            return back()->with('error', 'Esta orden no puede ser recibida en su estado actual.');
        }

        return Inertia::render('Inventory/PurchaseOrders/Receive', [
            'order' => $purchaseOrder->load(['supplier', 'items.product', 'receipts']),
            'warehouses' => $this->inventoryService->getWarehouses(),
        ]);
    }

    public function processReceipt(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->checkPermission('purchase_orders.receive');
        
        if ($purchaseOrder->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $validated = $request->validate([
            'receipt_date' => 'required|date',
            'reference' => 'nullable|string|max:255',
            'warehouse_id' => 'required|exists:warehouses,id',
            'notes' => 'nullable|string',
            'items' => 'required|array',
            'items.*.id' => 'required|exists:purchase_order_items,id',
            'items.*.quantity_received' => 'required|numeric|min:0',
            'items.*.location' => 'nullable|string|max:100',
            'items.*.batch_number' => 'nullable|string|max:100',
            'items.*.expiry_date' => 'nullable|date',
            'items.*.notes' => 'nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();
            
            $receipt = $this->purchaseOrderService->createReceipt($purchaseOrder, $validated);
            
            // Update inventory
            $this->inventoryService->processReceipt($receipt);
            
            DB::commit();

            return redirect()->route('inventory.purchase-orders.show', $purchaseOrder)
                ->with('success', 'Recepción procesada exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al procesar la recepción: ' . $e->getMessage());
        }
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->checkPermission('purchase_orders.cancel');
        
        if ($purchaseOrder->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $result = $this->purchaseOrderService->cancelPurchaseOrder(
            $purchaseOrder,
            $request->get('reason')
        );

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', 'Orden de compra cancelada exitosamente.');
    }

    public function duplicate(PurchaseOrder $purchaseOrder)
    {
        $this->checkPermission('purchase_orders.create');
        
        if ($purchaseOrder->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        try {
            DB::beginTransaction();
            
            $newOrder = $this->purchaseOrderService->duplicatePurchaseOrder($purchaseOrder);
            
            DB::commit();

            return redirect()->route('inventory.purchase-orders.edit', $newOrder)
                ->with('success', 'Orden de compra duplicada exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al duplicar la orden: ' . $e->getMessage());
        }
    }

    public function pdf(PurchaseOrder $purchaseOrder)
    {
        $this->checkPermission('purchase_orders.view');
        
        if ($purchaseOrder->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        $purchaseOrder->load(['supplier', 'items.product', 'user']);

        $pdf = Pdf::loadView('purchase-orders.pdf', [
            'order' => $purchaseOrder,
            'company' => auth()->user()->tenant,
        ]);

        return $pdf->download("orden-compra-{$purchaseOrder->order_number}.pdf");
    }

    public function export(Request $request)
    {
        $this->checkPermission('purchase_orders.export');
        
        $filters = $request->only(['search', 'status', 'supplier_id', 'start_date', 'end_date']);
        
        return $this->purchaseOrderService->exportPurchaseOrders($filters);
    }

    public function getApprovalHistory(PurchaseOrder $purchaseOrder)
    {
        $this->checkPermission('purchase_orders.view');
        
        if ($purchaseOrder->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $history = $this->purchaseOrderService->getApprovalHistory($purchaseOrder);

        return response()->json([
            'success' => true,
            'history' => $history
        ]);
    }

    public function compareQuotes(Request $request)
    {
        $this->checkPermission('purchase_orders.create');
        
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
            'quantities' => 'required|array',
            'quantities.*' => 'numeric|min:1',
            'supplier_ids' => 'nullable|array',
            'supplier_ids.*' => 'exists:suppliers,id',
        ]);

        $comparison = $this->purchaseOrderService->compareQuotes(
            $request->get('product_ids'),
            $request->get('quantities'),
            $request->get('supplier_ids', [])
        );

        return response()->json([
            'success' => true,
            'comparison' => $comparison
        ]);
    }
}