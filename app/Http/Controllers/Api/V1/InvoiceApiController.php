<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\TaxDocument;
use App\Models\Customer;
use App\Models\Product;
use App\Services\SII\DTEService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InvoiceApiController extends BaseApiController
{
    protected DTEService $dteService;

    public function __construct(DTEService $dteService)
    {
        $this->dteService = $dteService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = TaxDocument::where('tenant_id', $this->getTenantId($request))
            ->with(['customer', 'items']);
        
        // Filter by document type
        if ($request->has('document_type')) {
            $query->where('document_type', $request->document_type);
        }
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by payment status
        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }
        
        // Filter by customer
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        
        $query = $this->applyFilters($request, $query);
        
        $invoices = $query->paginate($request->get('per_page', 15));
        
        return $this->paginated($invoices);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $invoice = TaxDocument::where('tenant_id', $this->getTenantId($request))
            ->with(['customer', 'items.product', 'payments'])
            ->find($id);
            
        if (!$invoice) {
            return $this->notFound('Factura no encontrada');
        }
        
        return $this->success($invoice);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'document_type' => 'required|in:33,34,61,56', // Factura, Exenta, Nota Crédito, Nota Débito
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:issue_date',
            'currency' => 'nullable|string|size:3',
            'exchange_rate' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_percentage' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_rate' => 'nullable|numeric|min:0'
        ]);
        
        // Verify customer belongs to tenant
        $customer = Customer::where('tenant_id', $this->getTenantId($request))
            ->find($validated['customer_id']);
            
        if (!$customer) {
            return $this->forbidden('Cliente no pertenece a su empresa');
        }
        
        try {
            DB::beginTransaction();
            
            $validated['tenant_id'] = $this->getTenantId($request);
            $validated['status'] = 'draft';
            
            // Calculate totals
            $subtotal = 0;
            $totalTax = 0;
            
            foreach ($validated['items'] as $item) {
                $lineTotal = $item['quantity'] * $item['unit_price'];
                if (isset($item['discount_percentage'])) {
                    $lineTotal *= (1 - $item['discount_percentage'] / 100);
                }
                $subtotal += $lineTotal;
                
                $taxRate = $item['tax_rate'] ?? 19; // Default 19% IVA
                $totalTax += $lineTotal * ($taxRate / 100);
            }
            
            $validated['subtotal'] = round($subtotal, 2);
            $validated['tax_amount'] = round($totalTax, 2);
            $validated['total_amount'] = round($subtotal + $totalTax, 2);
            
            // Create invoice
            $items = $validated['items'];
            unset($validated['items']);
            
            $invoice = TaxDocument::create($validated);
            
            // Create items
            foreach ($items as $item) {
                $invoice->items()->create([
                    'tenant_id' => $this->getTenantId($request),
                    'product_id' => $item['product_id'] ?? null,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount_percentage' => $item['discount_percentage'] ?? 0,
                    'tax_rate' => $item['tax_rate'] ?? 19,
                    'subtotal' => round($item['quantity'] * $item['unit_price'] * (1 - ($item['discount_percentage'] ?? 0) / 100), 2)
                ]);
                
                // Update inventory if product
                if (isset($item['product_id'])) {
                    $product = Product::find($item['product_id']);
                    if ($product && $product->track_inventory) {
                        $product->recordMovement(
                            -$item['quantity'],
                            'sale',
                            "Venta - Factura #{$invoice->document_number}"
                        );
                    }
                }
            }
            
            DB::commit();
            
            return $this->created($invoice->load(['customer', 'items']), 'Factura creada exitosamente');
            
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Error al crear factura: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        $invoice = TaxDocument::where('tenant_id', $this->getTenantId($request))
            ->find($id);
            
        if (!$invoice) {
            return $this->notFound('Factura no encontrada');
        }
        
        if ($invoice->status !== 'draft') {
            return $this->error('Solo se pueden editar facturas en borrador', 409);
        }
        
        $validated = $request->validate([
            'due_date' => 'nullable|date|after_or_equal:issue_date',
            'notes' => 'nullable|string|max:1000'
        ]);
        
        $invoice->update($validated);
        
        return $this->updated($invoice, 'Factura actualizada exitosamente');
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $invoice = TaxDocument::where('tenant_id', $this->getTenantId($request))
            ->find($id);
            
        if (!$invoice) {
            return $this->notFound('Factura no encontrada');
        }
        
        if ($invoice->status !== 'draft') {
            return $this->error('Solo se pueden eliminar facturas en borrador', 409);
        }
        
        $invoice->delete();
        
        return $this->deleted('Factura eliminada exitosamente');
    }

    public function send(Request $request, $id): JsonResponse
    {
        $invoice = TaxDocument::where('tenant_id', $this->getTenantId($request))
            ->with(['customer', 'items', 'tenant'])
            ->find($id);
            
        if (!$invoice) {
            return $this->notFound('Factura no encontrada');
        }
        
        if ($invoice->status !== 'draft') {
            return $this->error('La factura ya fue enviada', 409);
        }
        
        try {
            $result = $this->dteService->generateAndSend($invoice);
            
            if ($result['success']) {
                return $this->success([
                    'trackid' => $result['trackid'],
                    'message' => 'Factura enviada exitosamente al SII'
                ]);
            } else {
                return $this->error('Error al enviar factura: ' . $result['message'], 500);
            }
        } catch (\Exception $e) {
            return $this->serverError('Error al procesar factura: ' . $e->getMessage());
        }
    }

    public function download(Request $request, $id): JsonResponse
    {
        $invoice = TaxDocument::where('tenant_id', $this->getTenantId($request))
            ->find($id);
            
        if (!$invoice) {
            return $this->notFound('Factura no encontrada');
        }
        
        $pdfUrl = route('invoices.download', $invoice->id);
        
        return $this->success([
            'download_url' => $pdfUrl,
            'filename' => "factura_{$invoice->document_number}.pdf"
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        
        $summary = [
            'total_invoices' => TaxDocument::where('tenant_id', $tenantId)
                ->whereIn('document_type', [33, 34])
                ->count(),
            'pending_amount' => TaxDocument::where('tenant_id', $tenantId)
                ->whereIn('document_type', [33, 34])
                ->where('payment_status', '!=', 'paid')
                ->sum('total_amount'),
            'overdue_count' => TaxDocument::where('tenant_id', $tenantId)
                ->whereIn('document_type', [33, 34])
                ->where('payment_status', '!=', 'paid')
                ->where('due_date', '<', now())
                ->count(),
            'monthly_total' => TaxDocument::where('tenant_id', $tenantId)
                ->whereIn('document_type', [33, 34])
                ->whereMonth('issue_date', now()->month)
                ->whereYear('issue_date', now()->year)
                ->sum('total_amount')
        ];
        
        return $this->success($summary);
    }
}