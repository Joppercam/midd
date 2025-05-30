<?php

namespace App\Modules\Inventory\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderReceipt;
use App\Models\PurchaseOrderReceiptItem;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class PurchaseOrderService
{
    protected $productService;
    
    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    public function getPurchaseOrders(array $filters = [], int $perPage = 20)
    {
        $tenantId = auth()->user()->tenant_id;
        $query = PurchaseOrder::where('tenant_id', $tenantId)
            ->with(['supplier', 'items.product', 'createdBy']);

        // Filtros
        if (!empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('order_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('order_date', '<=', $filters['date_to']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Ordenamiento
        $sortField = $filters['sort'] ?? 'order_date';
        $sortDirection = $filters['direction'] ?? 'desc';
        $query->orderBy($sortField, $sortDirection);

        return $query->paginate($perPage)->withQueryString();
    }

    public function createPurchaseOrder(array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            // Generar número de orden
            $orderNumber = $this->generateOrderNumber();

            $purchaseOrder = PurchaseOrder::create([
                'tenant_id' => auth()->user()->tenant_id,
                'order_number' => $orderNumber,
                'supplier_id' => $data['supplier_id'],
                'order_date' => $data['order_date'] ?? now(),
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'status' => 'draft',
                'subtotal' => 0,
                'tax_amount' => 0,
                'total' => 0,
                'notes' => $data['notes'] ?? '',
                'reference' => $data['reference'] ?? '',
                'created_by' => auth()->id(),
            ]);

            // Crear items si se proporcionan
            if (isset($data['items']) && is_array($data['items'])) {
                $this->updateOrderItems($purchaseOrder, $data['items']);
            }

            return $purchaseOrder;
        });
    }

    public function updatePurchaseOrder(PurchaseOrder $order, array $data): PurchaseOrder
    {
        if ($order->status === 'completed') {
            throw new \Exception('No se puede modificar una orden completada');
        }

        return DB::transaction(function () use ($order, $data) {
            $order->update([
                'supplier_id' => $data['supplier_id'] ?? $order->supplier_id,
                'order_date' => $data['order_date'] ?? $order->order_date,
                'expected_delivery_date' => $data['expected_delivery_date'] ?? $order->expected_delivery_date,
                'notes' => $data['notes'] ?? $order->notes,
                'reference' => $data['reference'] ?? $order->reference,
                'updated_by' => auth()->id(),
            ]);

            // Actualizar items si se proporcionan
            if (isset($data['items']) && is_array($data['items'])) {
                $this->updateOrderItems($order, $data['items']);
            }

            return $order->fresh();
        });
    }

    public function deletePurchaseOrder(PurchaseOrder $order): array
    {
        if ($order->status === 'completed') {
            return [
                'success' => false,
                'message' => 'No se puede eliminar una orden completada'
            ];
        }

        if ($order->receipts()->exists()) {
            return [
                'success' => false,
                'message' => 'No se puede eliminar una orden con recepciones'
            ];
        }

        $order->delete();

        return [
            'success' => true,
            'message' => 'Orden de compra eliminada exitosamente'
        ];
    }

    public function approvePurchaseOrder(PurchaseOrder $order): PurchaseOrder
    {
        if ($order->status !== 'draft') {
            throw new \Exception('Solo se pueden aprobar órdenes en borrador');
        }

        $order->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return $order;
    }

    public function sendToSupplier(PurchaseOrder $order): PurchaseOrder
    {
        if ($order->status !== 'approved') {
            throw new \Exception('Solo se pueden enviar órdenes aprobadas');
        }

        $order->update([
            'status' => 'sent',
            'sent_at' => now(),
            'sent_by' => auth()->id(),
        ]);

        // Aquí se podría integrar con email o API del proveedor

        return $order;
    }

    public function receiveOrder(PurchaseOrder $order, array $receivedItems): PurchaseOrderReceipt
    {
        if (!in_array($order->status, ['sent', 'partial'])) {
            throw new \Exception('La orden no está en estado válido para recepción');
        }

        return DB::transaction(function () use ($order, $receivedItems) {
            $receipt = PurchaseOrderReceiptItem::create([
                'tenant_id' => auth()->user()->tenant_id,
                'purchase_order_id' => $order->id,
                'receipt_number' => $this->generateReceiptNumber(),
                'receipt_date' => now(),
                'notes' => $receivedItems['notes'] ?? '',
                'created_by' => auth()->id(),
            ]);

            $totalReceived = 0;
            $totalOrdered = 0;

            foreach ($receivedItems['items'] as $itemData) {
                $orderItem = PurchaseOrderItem::findOrFail($itemData['purchase_order_item_id']);
                
                $receiptItem = PurchaseOrderReceiptItem::create([
                    'tenant_id' => auth()->user()->tenant_id,
                    'purchase_order_receipt_id' => $receipt->id,
                    'purchase_order_item_id' => $orderItem->id,
                    'product_id' => $orderItem->product_id,
                    'quantity_received' => $itemData['quantity_received'],
                    'unit_cost' => $itemData['unit_cost'] ?? $orderItem->unit_cost,
                    'total_cost' => $itemData['quantity_received'] * ($itemData['unit_cost'] ?? $orderItem->unit_cost),
                    'condition' => $itemData['condition'] ?? 'good',
                    'notes' => $itemData['notes'] ?? '',
                ]);

                // Actualizar stock si el producto está en buenas condiciones
                if ($receiptItem->condition === 'good' && $receiptItem->quantity_received > 0) {
                    $this->productService->createInventoryMovement($orderItem->product, [
                        'type' => 'purchase',
                        'quantity' => $receiptItem->quantity_received,
                        'unit_cost' => $receiptItem->unit_cost,
                        'reference' => "Orden #{$order->order_number}",
                        'notes' => "Recepción de orden de compra",
                    ]);
                }

                // Actualizar cantidad recibida en el item de la orden
                $orderItem->increment('quantity_received', $receiptItem->quantity_received);
                
                $totalReceived += $orderItem->quantity_received;
                $totalOrdered += $orderItem->quantity;
            }

            // Actualizar estado de la orden
            if ($totalReceived >= $totalOrdered) {
                $order->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            } else {
                $order->update(['status' => 'partial']);
            }

            return $receipt;
        });
    }

    public function cancelPurchaseOrder(PurchaseOrder $order, string $reason): PurchaseOrder
    {
        if ($order->status === 'completed') {
            throw new \Exception('No se puede cancelar una orden completada');
        }

        $order->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => auth()->id(),
            'cancellation_reason' => $reason,
        ]);

        return $order;
    }

    public function duplicatePurchaseOrder(PurchaseOrder $order): PurchaseOrder
    {
        return DB::transaction(function () use ($order) {
            $newOrder = $order->replicate([
                'order_number',
                'status',
                'approved_by',
                'approved_at',
                'sent_at',
                'sent_by',
                'completed_at',
                'cancelled_at',
                'cancelled_by',
                'cancellation_reason'
            ]);

            $newOrder->order_number = $this->generateOrderNumber();
            $newOrder->status = 'draft';
            $newOrder->order_date = now();
            $newOrder->created_by = auth()->id();
            $newOrder->save();

            // Duplicar items
            foreach ($order->items as $item) {
                $newOrder->items()->create([
                    'tenant_id' => $item->tenant_id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'total_cost' => $item->total_cost,
                    'notes' => $item->notes,
                ]);
            }

            $this->recalculateTotals($newOrder);

            return $newOrder;
        });
    }

    public function getPurchaseOrderStats(): array
    {
        $tenantId = auth()->user()->tenant_id;

        $stats = PurchaseOrder::where('tenant_id', $tenantId)
            ->selectRaw("
                COUNT(*) as total_orders,
                COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_orders,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_orders,
                COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent_orders,
                COUNT(CASE WHEN status = 'partial' THEN 1 END) as partial_orders,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_orders,
                SUM(total) as total_amount,
                AVG(total) as average_amount
            ")
            ->first();

        return [
            'total_orders' => $stats->total_orders,
            'draft_orders' => $stats->draft_orders,
            'approved_orders' => $stats->approved_orders,
            'sent_orders' => $stats->sent_orders,
            'partial_orders' => $stats->partial_orders,
            'completed_orders' => $stats->completed_orders,
            'cancelled_orders' => $stats->cancelled_orders,
            'total_amount' => $stats->total_amount,
            'average_amount' => $stats->average_amount,
        ];
    }

    private function updateOrderItems(PurchaseOrder $order, array $items): void
    {
        // Eliminar items existentes
        $order->items()->delete();

        $subtotal = 0;

        foreach ($items as $item) {
            $totalCost = $item['quantity'] * $item['unit_cost'];
            
            PurchaseOrderItem::create([
                'tenant_id' => auth()->user()->tenant_id,
                'purchase_order_id' => $order->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_cost' => $item['unit_cost'],
                'total_cost' => $totalCost,
                'notes' => $item['notes'] ?? '',
            ]);

            $subtotal += $totalCost;
        }

        // Actualizar totales de la orden
        $taxAmount = $subtotal * 0.19; // IVA 19%
        $total = $subtotal + $taxAmount;

        $order->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ]);
    }

    private function recalculateTotals(PurchaseOrder $order): void
    {
        $subtotal = $order->items()->sum('total_cost');
        $taxAmount = $subtotal * 0.19; // IVA 19%
        $total = $subtotal + $taxAmount;

        $order->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ]);
    }

    private function generateOrderNumber(): string
    {
        $tenantId = auth()->user()->tenant_id;
        $year = now()->year;
        
        $lastOrder = PurchaseOrder::where('tenant_id', $tenantId)
            ->whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastOrder) {
            $lastNumber = (int) substr($lastOrder->order_number, -6);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return 'OC-' . $year . '-' . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
    }

    private function generateReceiptNumber(): string
    {
        $tenantId = auth()->user()->tenant_id;
        $year = now()->year;
        
        $lastReceipt = PurchaseOrderReceipt::where('tenant_id', $tenantId)
            ->whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastReceipt) {
            $lastNumber = (int) substr($lastReceipt->receipt_number, -6);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return 'RC-' . $year . '-' . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
    }
}