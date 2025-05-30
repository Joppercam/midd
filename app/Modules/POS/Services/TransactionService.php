<?php

namespace App\Modules\POS\Services;

use App\Modules\POS\Models\Transaction;
use App\Modules\POS\Models\CashSession;
use App\Modules\POS\Models\Terminal;
use App\Models\Product;
use App\Models\Customer;
use App\Models\TaxDocument;
use App\Services\InventoryService;
use App\Services\SII\SIIService;
use App\Traits\BelongsToTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TransactionService
{
    use BelongsToTenant;

    public function __construct(
        private InventoryService $inventoryService,
        private SIIService $siiService
    ) {}

    /**
     * Obtener ventas recientes
     */
    public function getRecentSales($userId, $limit = 10)
    {
        return Transaction::whereHas('cashSession', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->where('type', 'sale')
            ->with(['customer', 'items.product', 'payments'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Obtener transacciones recientes
     */
    public function getRecentTransactions($limit = 20)
    {
        return Transaction::with(['customer', 'cashSession.user', 'items'])
            ->forCurrentTenant()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($transaction) {
                $transaction->formatted_total = number_format($transaction->total, 0, ',', '.');
                $transaction->formatted_date = $transaction->created_at->format('d/m/Y H:i');
                return $transaction;
            });
    }

    /**
     * Procesar venta
     */
    public function processSale($data)
    {
        DB::beginTransaction();
        try {
            // Validar sesión de caja activa
            $cashSession = CashSession::where('user_id', Auth::id())
                ->where('status', 'active')
                ->firstOrFail();

            // Crear transacción
            $transaction = Transaction::create([
                'type' => 'sale',
                'status' => 'pending',
                'transaction_number' => $this->generateTransactionNumber(),
                'cash_session_id' => $cashSession->id,
                'terminal_id' => $cashSession->terminal_id,
                'user_id' => Auth::id(),
                'customer_id' => $data['customer_id'] ?? null,
                'subtotal' => 0,
                'tax' => 0,
                'discount' => $data['discount'] ?? 0,
                'total' => 0,
                'notes' => $data['notes'] ?? null,
                'tenant_id' => $this->getCurrentTenantId(),
            ]);

            $subtotal = 0;
            $taxAmount = 0;

            // Procesar items
            foreach ($data['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);
                
                // Validar stock
                if ($product->track_stock && $product->stock < $item['quantity']) {
                    throw new \Exception("Stock insuficiente para {$product->name}");
                }

                $itemSubtotal = $item['quantity'] * $item['price'];
                $itemTax = $itemSubtotal * ($item['tax_rate'] ?? config('pos.sales.tax_rate', 0.19));
                
                $subtotal += $itemSubtotal;
                $taxAmount += $itemTax;

                // Crear item de transacción
                $transaction->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'discount' => $item['discount'] ?? 0,
                    'tax_rate' => $item['tax_rate'] ?? config('pos.sales.tax_rate', 0.19),
                    'subtotal' => $itemSubtotal,
                    'tax' => $itemTax,
                    'total' => $itemSubtotal + $itemTax - ($item['discount'] ?? 0),
                    'notes' => $item['notes'] ?? null,
                    'tenant_id' => $this->getCurrentTenantId(),
                ]);

                // Actualizar inventario
                if ($product->track_stock) {
                    $this->inventoryService->createMovement($product->id, -$item['quantity'], 'sale', 
                        "Venta #{$transaction->transaction_number}");
                }
            }

            // Calcular totales
            $total = $subtotal + $taxAmount - $transaction->discount;
            
            $transaction->update([
                'subtotal' => $subtotal,
                'tax' => $taxAmount,
                'total' => $total,
            ]);

            // Procesar pagos
            $totalPaid = 0;
            foreach ($data['payments'] as $payment) {
                $transaction->payments()->create([
                    'method' => $payment['method'],
                    'amount' => $payment['amount'],
                    'reference' => $payment['reference'] ?? null,
                    'status' => 'completed',
                    'tenant_id' => $this->getCurrentTenantId(),
                ]);
                
                $totalPaid += $payment['amount'];

                // Actualizar sesión de caja si es efectivo
                if ($payment['method'] === 'cash') {
                    $cashSession->increment('current_amount', $payment['amount']);
                }
            }

            // Validar que el pago es completo
            if ($totalPaid < $total) {
                throw new \Exception('El pago no cubre el total de la venta');
            }

            // Calcular vuelto
            $change = $totalPaid - $total;
            if ($change > 0 && $data['payments'][0]['method'] === 'cash') {
                $transaction->update(['change_amount' => $change]);
                $cashSession->decrement('current_amount', $change);
            }

            // Marcar como completada
            $transaction->update(['status' => 'completed', 'completed_at' => now()]);

            // Generar documento tributario si es necesario
            if ($data['generate_invoice'] ?? false) {
                $this->generateTaxDocument($transaction, $data['document_type'] ?? 'boleta');
            }

            DB::commit();

            // Limpiar cache
            $this->clearTransactionCache();

            Log::info('Venta procesada exitosamente', [
                'transaction_id' => $transaction->id,
                'total' => $total,
            ]);

            return $transaction->load(['customer', 'items.product', 'payments']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al procesar venta: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Anular venta
     */
    public function voidSale($saleId, $data)
    {
        DB::beginTransaction();
        try {
            $transaction = Transaction::with(['items', 'payments', 'cashSession'])
                ->forCurrentTenant()
                ->findOrFail($saleId);

            if ($transaction->status === 'voided') {
                throw new \Exception('La venta ya está anulada');
            }

            if ($transaction->status !== 'completed') {
                throw new \Exception('Solo se pueden anular ventas completadas');
            }

            // Validar PIN de manager si es necesario
            if ($data['require_approval'] ?? false) {
                $this->validateManagerPin($data['manager_pin']);
            }

            // Revertir inventario
            foreach ($transaction->items as $item) {
                if ($item->product->track_stock) {
                    $this->inventoryService->createMovement(
                        $item->product_id, 
                        $item->quantity, 
                        'void',
                        "Anulación venta #{$transaction->transaction_number}"
                    );
                }
            }

            // Revertir pagos en efectivo
            foreach ($transaction->payments as $payment) {
                if ($payment->method === 'cash' && $transaction->cashSession) {
                    $transaction->cashSession->decrement('current_amount', $payment->amount);
                    
                    // Si hubo vuelto, ajustar
                    if ($transaction->change_amount > 0) {
                        $transaction->cashSession->increment('current_amount', $transaction->change_amount);
                    }
                }
                
                $payment->update(['status' => 'voided']);
            }

            // Actualizar transacción
            $transaction->update([
                'status' => 'voided',
                'voided_at' => now(),
                'voided_by' => Auth::id(),
                'void_reason' => $data['reason'],
            ]);

            // Si hay documento tributario, anularlo
            if ($transaction->tax_document_id) {
                $this->voidTaxDocument($transaction->tax_document_id, $data['reason']);
            }

            DB::commit();

            Log::info('Venta anulada', [
                'transaction_id' => $transaction->id,
                'reason' => $data['reason'],
            ]);

            return $transaction->fresh();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al anular venta: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Procesar devolución
     */
    public function processRefund($saleId, $data)
    {
        DB::beginTransaction();
        try {
            $originalTransaction = Transaction::with(['items', 'customer'])
                ->forCurrentTenant()
                ->findOrFail($saleId);

            if ($originalTransaction->status !== 'completed') {
                throw new \Exception('Solo se pueden devolver ventas completadas');
            }

            // Validar PIN de manager si es necesario
            if ($data['require_approval'] ?? false) {
                $this->validateManagerPin($data['manager_pin']);
            }

            // Validar sesión de caja activa
            $cashSession = CashSession::where('user_id', Auth::id())
                ->where('status', 'active')
                ->firstOrFail();

            // Crear transacción de devolución
            $refund = Transaction::create([
                'type' => 'refund',
                'status' => 'pending',
                'transaction_number' => $this->generateTransactionNumber('REF'),
                'original_transaction_id' => $originalTransaction->id,
                'cash_session_id' => $cashSession->id,
                'terminal_id' => $cashSession->terminal_id,
                'user_id' => Auth::id(),
                'customer_id' => $originalTransaction->customer_id,
                'subtotal' => 0,
                'tax' => 0,
                'total' => 0,
                'notes' => $data['reason'],
                'tenant_id' => $this->getCurrentTenantId(),
            ]);

            $subtotal = 0;
            $taxAmount = 0;

            // Procesar items a devolver
            foreach ($data['items'] as $refundItem) {
                $originalItem = $originalTransaction->items()
                    ->where('id', $refundItem['id'])
                    ->firstOrFail();

                // Validar cantidad
                $previouslyRefunded = $this->getRefundedQuantity($originalItem->id);
                $availableToRefund = $originalItem->quantity - $previouslyRefunded;

                if ($refundItem['quantity'] > $availableToRefund) {
                    throw new \Exception("Cantidad a devolver excede la cantidad disponible");
                }

                $itemSubtotal = $refundItem['quantity'] * $originalItem->price;
                $itemTax = $itemSubtotal * $originalItem->tax_rate;
                
                $subtotal += $itemSubtotal;
                $taxAmount += $itemTax;

                // Crear item de devolución
                $refund->items()->create([
                    'product_id' => $originalItem->product_id,
                    'quantity' => $refundItem['quantity'],
                    'price' => $originalItem->price,
                    'tax_rate' => $originalItem->tax_rate,
                    'subtotal' => $itemSubtotal,
                    'tax' => $itemTax,
                    'total' => $itemSubtotal + $itemTax,
                    'original_item_id' => $originalItem->id,
                    'tenant_id' => $this->getCurrentTenantId(),
                ]);

                // Revertir inventario
                if ($originalItem->product->track_stock) {
                    $this->inventoryService->createMovement(
                        $originalItem->product_id,
                        $refundItem['quantity'],
                        'refund',
                        "Devolución #{$refund->transaction_number}"
                    );
                }
            }

            // Calcular total
            $total = $subtotal + $taxAmount;
            
            $refund->update([
                'subtotal' => $subtotal,
                'tax' => $taxAmount,
                'total' => $total,
            ]);

            // Procesar pago de devolución
            $refundPayment = $refund->payments()->create([
                'method' => $data['refund_method'] ?? 'cash',
                'amount' => $total,
                'status' => 'completed',
                'tenant_id' => $this->getCurrentTenantId(),
            ]);

            // Actualizar sesión de caja si es efectivo
            if ($refundPayment->method === 'cash') {
                $cashSession->decrement('current_amount', $total);
            }

            // Marcar como completada
            $refund->update(['status' => 'completed', 'completed_at' => now()]);

            // Generar nota de crédito si es necesario
            if ($originalTransaction->tax_document_id) {
                $this->generateCreditNote($refund, $originalTransaction);
            }

            DB::commit();

            Log::info('Devolución procesada', [
                'refund_id' => $refund->id,
                'original_id' => $originalTransaction->id,
                'total' => $total,
            ]);

            return $refund->load(['customer', 'items.product', 'payments']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al procesar devolución: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generar documento tributario
     */
    private function generateTaxDocument($transaction, $type)
    {
        try {
            $documentData = [
                'type' => $type,
                'customer_id' => $transaction->customer_id,
                'subtotal' => $transaction->subtotal,
                'tax' => $transaction->tax,
                'total' => $transaction->total,
                'items' => $transaction->items->map(function ($item) {
                    return [
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'subtotal' => $item->subtotal,
                        'tax' => $item->tax,
                        'total' => $item->total,
                    ];
                })->toArray(),
                'transaction_id' => $transaction->id,
            ];

            $taxDocument = $this->siiService->createDocument($documentData);
            
            $transaction->update(['tax_document_id' => $taxDocument->id]);

            return $taxDocument;

        } catch (\Exception $e) {
            Log::error('Error al generar documento tributario: ' . $e->getMessage());
            // No lanzar excepción para no interrumpir la venta
        }
    }

    /**
     * Anular documento tributario
     */
    private function voidTaxDocument($taxDocumentId, $reason)
    {
        try {
            $this->siiService->voidDocument($taxDocumentId, $reason);
        } catch (\Exception $e) {
            Log::error('Error al anular documento tributario: ' . $e->getMessage());
        }
    }

    /**
     * Generar nota de crédito
     */
    private function generateCreditNote($refund, $originalTransaction)
    {
        try {
            $creditNoteData = [
                'type' => 'nota_credito',
                'reference_document_id' => $originalTransaction->tax_document_id,
                'customer_id' => $refund->customer_id,
                'subtotal' => $refund->subtotal,
                'tax' => $refund->tax,
                'total' => $refund->total,
                'reason' => $refund->notes,
                'items' => $refund->items->map(function ($item) {
                    return [
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'subtotal' => $item->subtotal,
                        'tax' => $item->tax,
                        'total' => $item->total,
                    ];
                })->toArray(),
            ];

            $creditNote = $this->siiService->createCreditNote($creditNoteData);
            
            $refund->update(['tax_document_id' => $creditNote->id]);

            return $creditNote;

        } catch (\Exception $e) {
            Log::error('Error al generar nota de crédito: ' . $e->getMessage());
        }
    }

    /**
     * Obtener cantidad devuelta de un item
     */
    private function getRefundedQuantity($originalItemId)
    {
        return DB::table('transaction_items')
            ->where('original_item_id', $originalItemId)
            ->whereHas('transaction', function ($query) {
                $query->where('type', 'refund')
                    ->where('status', 'completed');
            })
            ->sum('quantity');
    }

    /**
     * Generar número de transacción
     */
    private function generateTransactionNumber($prefix = 'TRX')
    {
        $date = now()->format('Ymd');
        $sequence = Cache::increment("transaction_sequence_{$date}", 1);
        
        return sprintf('%s-%s-%04d', $prefix, $date, $sequence);
    }

    /**
     * Validar PIN de manager
     */
    private function validateManagerPin($pin)
    {
        $user = Auth::user();
        
        if (!$user->hasRole(['admin', 'manager'])) {
            throw new \Exception('Usuario no autorizado');
        }

        if (!Hash::check($pin, $user->pin ?? $user->password)) {
            throw new \Exception('PIN incorrecto');
        }

        return true;
    }

    /**
     * Limpiar cache de transacciones
     */
    private function clearTransactionCache()
    {
        Cache::tags(['transactions', 'dashboard'])->flush();
    }

    /**
     * Obtener estadísticas de transacciones
     */
    public function getTransactionStats($filters = [])
    {
        $query = Transaction::forCurrentTenant();

        if (isset($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['terminal_id'])) {
            $query->where('terminal_id', $filters['terminal_id']);
        }

        $stats = [
            'sales' => [
                'count' => (clone $query)->where('type', 'sale')->where('status', 'completed')->count(),
                'total' => (clone $query)->where('type', 'sale')->where('status', 'completed')->sum('total'),
            ],
            'refunds' => [
                'count' => (clone $query)->where('type', 'refund')->count(),
                'total' => (clone $query)->where('type', 'refund')->sum('total'),
            ],
            'voids' => [
                'count' => (clone $query)->where('status', 'voided')->count(),
                'total' => (clone $query)->where('status', 'voided')->sum('total'),
            ],
            'average_sale' => 0,
            'top_products' => $this->getTopProducts($filters),
            'payment_methods' => $this->getPaymentMethodStats($filters),
        ];

        if ($stats['sales']['count'] > 0) {
            $stats['average_sale'] = $stats['sales']['total'] / $stats['sales']['count'];
        }

        return $stats;
    }

    /**
     * Obtener productos más vendidos
     */
    private function getTopProducts($filters = [], $limit = 10)
    {
        $query = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->where('transactions.type', 'sale')
            ->where('transactions.status', 'completed')
            ->where('transactions.tenant_id', $this->getCurrentTenantId());

        if (isset($filters['start_date'])) {
            $query->where('transactions.created_at', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->where('transactions.created_at', '<=', $filters['end_date']);
        }

        return $query->select(
                'products.id',
                'products.name',
                DB::raw('SUM(transaction_items.quantity) as total_quantity'),
                DB::raw('SUM(transaction_items.total) as total_revenue')
            )
            ->groupBy('products.id', 'products.name')
            ->orderBy('total_revenue', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Obtener estadísticas de métodos de pago
     */
    private function getPaymentMethodStats($filters = [])
    {
        $query = DB::table('transaction_payments')
            ->join('transactions', 'transaction_payments.transaction_id', '=', 'transactions.id')
            ->where('transactions.status', 'completed')
            ->where('transaction_payments.status', 'completed')
            ->where('transactions.tenant_id', $this->getCurrentTenantId());

        if (isset($filters['start_date'])) {
            $query->where('transactions.created_at', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->where('transactions.created_at', '<=', $filters['end_date']);
        }

        return $query->select(
                'transaction_payments.method',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(transaction_payments.amount) as total')
            )
            ->groupBy('transaction_payments.method')
            ->get()
            ->keyBy('method');
    }
}