<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Payment;
use App\Models\Customer;
use App\Models\TaxDocument;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PaymentApiController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $permissionCheck = $this->checkApiPermission('payments.view');
        if ($permissionCheck) return $permissionCheck;
        
        $this->logApiActivity('payments.index');
        
        $query = Payment::where('tenant_id', $this->getTenantId($request))
            ->with(['customer', 'allocations.taxDocument']);
        
        $query = $this->applyFilters($request, $query);
        
        // Additional filters specific to payments
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        
        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        $payments = $query->orderBy('payment_date', 'desc')
            ->paginate($request->get('per_page', 15));
        
        return $this->paginated($payments);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $permissionCheck = $this->checkApiPermission('payments.view');
        if ($permissionCheck) return $permissionCheck;
        
        $payment = Payment::where('tenant_id', $this->getTenantId($request))
            ->with(['customer', 'allocations.taxDocument'])
            ->find($id);
            
        if (!$payment) {
            return $this->notFound('Pago no encontrado');
        }
        
        return $this->success($this->transformModel($payment, ['customer', 'allocations.taxDocument']));
    }

    public function store(Request $request): JsonResponse
    {
        $permissionCheck = $this->checkApiPermission('payments.create');
        if ($permissionCheck) return $permissionCheck;
        
        try {
            $validated = $request->validate([
                'customer_id' => 'required|exists:customers,id',
                'payment_date' => 'required|date',
                'amount' => 'required|numeric|min:0.01',
                'payment_method' => 'required|in:cash,bank_transfer,check,credit_card,debit_card,electronic,other',
                'reference' => 'nullable|string|max:255',
                'bank' => 'nullable|string|max:255',
                'description' => 'nullable|string|max:1000',
                'status' => 'required|in:pending,confirmed,rejected,cancelled',
                'allocations' => 'nullable|array',
                'allocations.*.tax_document_id' => 'required|exists:tax_documents,id',
                'allocations.*.amount' => 'required|numeric|min:0.01'
            ]);

            $tenantId = $this->getTenantId($request);

            // Verify customer belongs to tenant
            $customer = Customer::where('tenant_id', $tenantId)
                ->findOrFail($validated['customer_id']);

            // Validate allocation amounts
            $totalAllocations = collect($validated['allocations'] ?? [])->sum('amount');
            if ($totalAllocations > $validated['amount']) {
                return $this->error('La suma de asignaciones no puede exceder el monto del pago', 422);
            }

            DB::transaction(function () use ($validated, $tenantId, $totalAllocations, &$payment) {
                // Create payment
                $payment = Payment::create([
                    'number' => Payment::generateNumber($tenantId),
                    'tenant_id' => $tenantId,
                    'customer_id' => $validated['customer_id'],
                    'payment_date' => $validated['payment_date'],
                    'amount' => $validated['amount'],
                    'payment_method' => $validated['payment_method'],
                    'reference' => $validated['reference'],
                    'bank' => $validated['bank'],
                    'description' => $validated['description'],
                    'status' => $validated['status'],
                    'remaining_amount' => $validated['amount'] - $totalAllocations
                ]);

                // Create allocations
                if (!empty($validated['allocations'])) {
                    foreach ($validated['allocations'] as $allocation) {
                        // Verify document belongs to same customer and tenant
                        $document = TaxDocument::where('tenant_id', $tenantId)
                            ->where('customer_id', $validated['customer_id'])
                            ->where('balance', '>', 0)
                            ->findOrFail($allocation['tax_document_id']);

                        $payment->allocations()->create([
                            'tax_document_id' => $allocation['tax_document_id'],
                            'amount' => $allocation['amount'],
                            'notes' => $allocation['notes'] ?? null
                        ]);
                    }
                }
            });

            $this->logApiActivity('payments.create', ['payment_id' => $payment->id]);

            return $this->created($payment->load(['customer', 'allocations.taxDocument']), 'Pago creado exitosamente');
            
        } catch (\Exception $e) {
            return $this->handleApiException($e);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        $permissionCheck = $this->checkApiPermission('payments.edit');
        if ($permissionCheck) return $permissionCheck;
        
        try {
            $payment = Payment::where('tenant_id', $this->getTenantId($request))
                ->find($id);
                
            if (!$payment) {
                return $this->notFound('Pago no encontrado');
            }
            
            $validated = $request->validate([
                'customer_id' => 'sometimes|exists:customers,id',
                'payment_date' => 'sometimes|date',
                'amount' => 'sometimes|numeric|min:0.01',
                'payment_method' => 'sometimes|in:cash,bank_transfer,check,credit_card,debit_card,electronic,other',
                'reference' => 'nullable|string|max:255',
                'bank' => 'nullable|string|max:255',
                'description' => 'nullable|string|max:1000',
                'status' => 'sometimes|in:pending,confirmed,rejected,cancelled'
            ]);

            $payment->update($validated);
            
            // Recalculate remaining amount if amount changed
            if (isset($validated['amount'])) {
                $payment->updateRemainingAmount();
            }

            $this->logApiActivity('payments.update', ['payment_id' => $payment->id]);

            return $this->updated($payment->load(['customer', 'allocations.taxDocument']), 'Pago actualizado exitosamente');
            
        } catch (\Exception $e) {
            return $this->handleApiException($e);
        }
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $permissionCheck = $this->checkApiPermission('payments.delete');
        if ($permissionCheck) return $permissionCheck;
        
        try {
            $payment = Payment::where('tenant_id', $this->getTenantId($request))
                ->find($id);
                
            if (!$payment) {
                return $this->notFound('Pago no encontrado');
            }

            DB::transaction(function () use ($payment) {
                // Delete allocations (this will update document balances)
                $payment->allocations()->delete();
                
                // Delete payment
                $payment->delete();
            });

            $this->logApiActivity('payments.delete', ['payment_id' => $id]);

            return $this->deleted('Pago eliminado exitosamente');
            
        } catch (\Exception $e) {
            return $this->handleApiException($e);
        }
    }

    public function allocate(Request $request, $id): JsonResponse
    {
        $permissionCheck = $this->checkApiPermission('payments.edit');
        if ($permissionCheck) return $permissionCheck;
        
        try {
            $payment = Payment::where('tenant_id', $this->getTenantId($request))
                ->find($id);
                
            if (!$payment) {
                return $this->notFound('Pago no encontrado');
            }

            $validated = $request->validate([
                'allocations' => 'required|array|min:1',
                'allocations.*.tax_document_id' => 'required|exists:tax_documents,id',
                'allocations.*.amount' => 'required|numeric|min:0.01',
                'allocations.*.notes' => 'nullable|string|max:500'
            ]);

            $tenantId = $this->getTenantId($request);
            
            // Validate allocation amounts don't exceed remaining amount
            $totalAllocations = collect($validated['allocations'])->sum('amount');
            if ($totalAllocations > $payment->remaining_amount) {
                return $this->error('La suma de asignaciones no puede exceder el monto disponible del pago', 422);
            }

            DB::transaction(function () use ($validated, $payment, $tenantId) {
                foreach ($validated['allocations'] as $allocation) {
                    // Verify document belongs to same customer and tenant
                    $document = TaxDocument::where('tenant_id', $tenantId)
                        ->where('customer_id', $payment->customer_id)
                        ->where('balance', '>', 0)
                        ->findOrFail($allocation['tax_document_id']);

                    // Check if allocation already exists
                    $existingAllocation = $payment->allocations()
                        ->where('tax_document_id', $allocation['tax_document_id'])
                        ->first();

                    if ($existingAllocation) {
                        continue; // Skip if already exists
                    }

                    $payment->allocations()->create([
                        'tax_document_id' => $allocation['tax_document_id'],
                        'amount' => $allocation['amount'],
                        'notes' => $allocation['notes'] ?? null
                    ]);
                }
            });

            $this->logApiActivity('payments.allocate', ['payment_id' => $payment->id]);

            return $this->success($payment->fresh()->load(['customer', 'allocations.taxDocument']), 'Asignaciones creadas exitosamente');
            
        } catch (\Exception $e) {
            return $this->handleApiException($e);
        }
    }

    public function statistics(Request $request): JsonResponse
    {
        $permissionCheck = $this->checkApiPermission('payments.view');
        if ($permissionCheck) return $permissionCheck;
        
        $tenantId = $this->getTenantId($request);
        
        // Get payment statistics
        $stats = [
            'total_amount' => Payment::where('tenant_id', $tenantId)
                ->where('status', 'confirmed')
                ->sum('amount'),
            'pending_amount' => Payment::where('tenant_id', $tenantId)
                ->where('status', 'pending')
                ->sum('amount'),
            'this_month' => Payment::where('tenant_id', $tenantId)
                ->where('status', 'confirmed')
                ->whereMonth('payment_date', now()->month)
                ->whereYear('payment_date', now()->year)
                ->sum('amount'),
            'unallocated_amount' => Payment::where('tenant_id', $tenantId)
                ->where('status', 'confirmed')
                ->where('remaining_amount', '>', 0)
                ->sum('remaining_amount'),
            'by_method' => Payment::where('tenant_id', $tenantId)
                ->where('status', 'confirmed')
                ->selectRaw('payment_method, COUNT(*) as count, SUM(amount) as total')
                ->groupBy('payment_method')
                ->get()
        ];

        return $this->success($stats);
    }
}