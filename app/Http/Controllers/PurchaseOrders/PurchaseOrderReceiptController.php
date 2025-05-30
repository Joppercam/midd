<?php

namespace App\Http\Controllers\PurchaseOrders;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderReceipt;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PurchaseOrderReceiptController extends Controller
{
    protected $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    public function create(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('update', $purchaseOrder);
        
        if (!$purchaseOrder->canBeReceived()) {
            return back()->with('error', 'Esta orden no puede recibir mercancía en su estado actual.');
        }

        $purchaseOrder->load(['items.product', 'supplier']);

        // Filtrar solo items con cantidad pendiente
        $pendingItems = $purchaseOrder->items->filter(function ($item) {
            return $item->pending_quantity > 0;
        });

        if ($pendingItems->isEmpty()) {
            return back()->with('error', 'No hay items pendientes de recepción.');
        }

        return Inertia::render('PurchaseOrders/Receipt', [
            'order' => $purchaseOrder,
            'pendingItems' => $pendingItems->values(),
        ]);
    }

    public function store(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorize('update', $purchaseOrder);
        
        if (!$purchaseOrder->canBeReceived()) {
            return back()->with('error', 'Esta orden no puede recibir mercancía en su estado actual.');
        }

        $validated = $request->validate([
            'received_by' => 'required|string|max:255',
            'reference_document' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.purchase_order_item_id' => 'required|exists:purchase_order_items,id',
            'items.*.quantity_received' => 'required|numeric|min:0',
            'items.*.condition' => 'required|in:good,damaged,rejected',
            'items.*.notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // Crear recepción
            $receipt = PurchaseOrderReceipt::create([
                'purchase_order_id' => $purchaseOrder->id,
                'user_id' => auth()->id(),
                'received_at' => now(),
                'received_by' => $validated['received_by'],
                'reference_document' => $validated['reference_document'],
                'notes' => $validated['notes'],
            ]);

            // Procesar items recibidos
            foreach ($validated['items'] as $itemData) {
                if ($itemData['quantity_received'] > 0) {
                    // Crear registro de recepción
                    $receipt->items()->create([
                        'purchase_order_item_id' => $itemData['purchase_order_item_id'],
                        'quantity_received' => $itemData['quantity_received'],
                        'condition' => $itemData['condition'],
                        'notes' => $itemData['notes'] ?? null,
                    ]);

                    // Actualizar inventario solo para items en buenas condiciones
                    if ($itemData['condition'] === 'good') {
                        $orderItem = $purchaseOrder->items()->find($itemData['purchase_order_item_id']);
                        if ($orderItem && $orderItem->product_id) {
                            $this->inventoryService->adjustStock(
                                $orderItem->product_id,
                                $itemData['quantity_received'],
                                'purchase_receipt',
                                "Recepción OC {$purchaseOrder->order_number}",
                                [
                                    'purchase_order_id' => $purchaseOrder->id,
                                    'receipt_id' => $receipt->id,
                                    'cost' => $orderItem->unit_price,
                                ]
                            );
                        }
                    }
                }
            }

            // Actualizar cantidades recibidas y estado de la orden
            $purchaseOrder->updateReceivedQuantities();

            DB::commit();

            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->with('success', 'Recepción registrada exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al registrar la recepción: ' . $e->getMessage());
        }
    }

    public function show(PurchaseOrder $purchaseOrder, PurchaseOrderReceipt $receipt)
    {
        $this->authorize('view', $purchaseOrder);
        
        $receipt->load(['items.purchaseOrderItem.product', 'user']);

        return Inertia::render('PurchaseOrders/ReceiptDetails', [
            'order' => $purchaseOrder,
            'receipt' => $receipt,
        ]);
    }

    public function pdf(PurchaseOrder $purchaseOrder, PurchaseOrderReceipt $receipt)
    {
        $this->authorize('view', $purchaseOrder);
        
        $receipt->load(['items.purchaseOrderItem.product', 'user', 'purchaseOrder.supplier']);

        $pdf = \PDF::loadView('purchase-orders.receipt-pdf', [
            'receipt' => $receipt,
        ]);

        return $pdf->download("recepcion-{$receipt->receipt_number}.pdf");
    }
}