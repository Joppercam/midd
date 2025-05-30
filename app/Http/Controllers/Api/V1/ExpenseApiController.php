<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Expense;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ExpenseApiController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $permissionCheck = $this->checkApiPermission('expenses.view');
        if ($permissionCheck) return $permissionCheck;
        
        $this->logApiActivity('expenses.index');
        
        $query = Expense::where('tenant_id', $this->getTenantId($request))
            ->with(['supplier']);
        
        $query = $this->applyFilters($request, $query);
        
        // Additional filters
        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }
        
        if ($request->has('document_type')) {
            $query->where('document_type', $request->document_type);
        }
        
        if ($request->has('status')) {
            if ($request->status === 'overdue') {
                $query->where('due_date', '<', now())
                    ->where('status', 'pending');
            } else {
                $query->where('status', $request->status);
            }
        }
        
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }
        
        $expenses = $query->orderBy('issue_date', 'desc')
            ->paginate($request->get('per_page', 15));
        
        return $this->paginated($expenses);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $permissionCheck = $this->checkApiPermission('expenses.view');
        if ($permissionCheck) return $permissionCheck;
        
        $expense = Expense::where('tenant_id', $this->getTenantId($request))
            ->with(['supplier'])
            ->find($id);
            
        if (!$expense) {
            return $this->notFound('Gasto no encontrado');
        }
        
        return $this->success($this->transformModel($expense, ['supplier']));
    }

    public function store(Request $request): JsonResponse
    {
        $permissionCheck = $this->checkApiPermission('expenses.create');
        if ($permissionCheck) return $permissionCheck;
        
        try {
            $validated = $request->validate([
                'supplier_id' => 'nullable|exists:suppliers,id',
                'document_type' => 'required|in:invoice,receipt,expense_note,petty_cash,bank_charge,other',
                'supplier_document_number' => 'nullable|string|max:255',
                'issue_date' => 'required|date',
                'due_date' => 'nullable|date|after_or_equal:issue_date',
                'net_amount' => 'required|numeric|min:0',
                'tax_amount' => 'nullable|numeric|min:0',
                'other_taxes' => 'nullable|numeric|min:0',
                'total_amount' => 'nullable|numeric|min:0',
                'payment_method' => 'nullable|in:cash,bank_transfer,check,credit_card,debit_card,electronic,credit_account,other',
                'status' => 'required|in:draft,pending,paid,cancelled',
                'category' => 'nullable|string|max:100',
                'description' => 'nullable|string|max:1000',
                'reference' => 'nullable|string|max:255'
            ]);

            $tenantId = $this->getTenantId($request);

            // Verify supplier belongs to tenant if provided
            if ($validated['supplier_id']) {
                $supplier = Supplier::where('tenant_id', $tenantId)
                    ->findOrFail($validated['supplier_id']);
            }

            // Calculate totals
            $netAmount = $validated['net_amount'];
            $taxAmount = $validated['tax_amount'] ?? 0;
            $otherTaxes = $validated['other_taxes'] ?? 0;
            $totalAmount = $validated['total_amount'] ?? ($netAmount + $taxAmount + $otherTaxes);

            $expense = Expense::create([
                'number' => Expense::generateNumber($tenantId),
                'tenant_id' => $tenantId,
                'supplier_id' => $validated['supplier_id'],
                'document_type' => $validated['document_type'],
                'supplier_document_number' => $validated['supplier_document_number'],
                'issue_date' => $validated['issue_date'],
                'due_date' => $validated['due_date'],
                'net_amount' => $netAmount,
                'tax_amount' => $taxAmount,
                'other_taxes' => $otherTaxes,
                'total_amount' => $totalAmount,
                'balance' => $validated['status'] === 'paid' ? 0 : $totalAmount,
                'payment_method' => $validated['payment_method'],
                'status' => $validated['status'],
                'category' => $validated['category'],
                'description' => $validated['description'],
                'reference' => $validated['reference']
            ]);

            $this->logApiActivity('expenses.create', ['expense_id' => $expense->id]);

            return $this->created($expense->load('supplier'), 'Gasto creado exitosamente');
            
        } catch (\Exception $e) {
            return $this->handleApiException($e);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        $permissionCheck = $this->checkApiPermission('expenses.edit');
        if ($permissionCheck) return $permissionCheck;
        
        try {
            $expense = Expense::where('tenant_id', $this->getTenantId($request))
                ->find($id);
                
            if (!$expense) {
                return $this->notFound('Gasto no encontrado');
            }
            
            $validated = $request->validate([
                'supplier_id' => 'sometimes|nullable|exists:suppliers,id',
                'document_type' => 'sometimes|in:invoice,receipt,expense_note,petty_cash,bank_charge,other',
                'supplier_document_number' => 'sometimes|nullable|string|max:255',
                'issue_date' => 'sometimes|date',
                'due_date' => 'sometimes|nullable|date|after_or_equal:issue_date',
                'net_amount' => 'sometimes|numeric|min:0',
                'tax_amount' => 'sometimes|nullable|numeric|min:0',
                'other_taxes' => 'sometimes|nullable|numeric|min:0',
                'total_amount' => 'sometimes|nullable|numeric|min:0',
                'payment_method' => 'sometimes|nullable|in:cash,bank_transfer,check,credit_card,debit_card,electronic,credit_account,other',
                'status' => 'sometimes|in:draft,pending,paid,cancelled',
                'category' => 'sometimes|nullable|string|max:100',
                'description' => 'sometimes|nullable|string|max:1000',
                'reference' => 'sometimes|nullable|string|max:255'
            ]);

            $tenantId = $this->getTenantId($request);

            // Verify supplier belongs to tenant if changed
            if (isset($validated['supplier_id']) && $validated['supplier_id']) {
                $supplier = Supplier::where('tenant_id', $tenantId)
                    ->findOrFail($validated['supplier_id']);
            }

            // Recalculate totals if amounts changed
            if (isset($validated['net_amount']) || isset($validated['tax_amount']) || isset($validated['other_taxes'])) {
                $netAmount = $validated['net_amount'] ?? $expense->net_amount;
                $taxAmount = $validated['tax_amount'] ?? $expense->tax_amount;
                $otherTaxes = $validated['other_taxes'] ?? $expense->other_taxes;
                $validated['total_amount'] = $validated['total_amount'] ?? ($netAmount + $taxAmount + $otherTaxes);
                
                // Recalculate balance
                $paidAmount = $expense->total_amount - $expense->balance;
                $validated['balance'] = max(0, $validated['total_amount'] - $paidAmount);
            }
            
            // Set balance to 0 for paid or cancelled status
            if (isset($validated['status'])) {
                if (in_array($validated['status'], ['paid', 'cancelled'])) {
                    $validated['balance'] = 0;
                }
            }

            $expense->update($validated);

            $this->logApiActivity('expenses.update', ['expense_id' => $expense->id]);

            return $this->updated($expense->load('supplier'), 'Gasto actualizado exitosamente');
            
        } catch (\Exception $e) {
            return $this->handleApiException($e);
        }
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $permissionCheck = $this->checkApiPermission('expenses.delete');
        if ($permissionCheck) return $permissionCheck;
        
        try {
            $expense = Expense::where('tenant_id', $this->getTenantId($request))
                ->find($id);
                
            if (!$expense) {
                return $this->notFound('Gasto no encontrado');
            }

            $expense->delete();

            $this->logApiActivity('expenses.delete', ['expense_id' => $id]);

            return $this->deleted('Gasto eliminado exitosamente');
            
        } catch (\Exception $e) {
            return $this->handleApiException($e);
        }
    }

    public function markAsPaid(Request $request, $id): JsonResponse
    {
        $permissionCheck = $this->checkApiPermission('expenses.edit');
        if ($permissionCheck) return $permissionCheck;
        
        try {
            $expense = Expense::where('tenant_id', $this->getTenantId($request))
                ->find($id);
                
            if (!$expense) {
                return $this->notFound('Gasto no encontrado');
            }

            $validated = $request->validate([
                'payment_method' => 'required|in:cash,bank_transfer,check,credit_card,debit_card,electronic,credit_account,other',
                'reference' => 'nullable|string|max:255',
                'payment_date' => 'nullable|date'
            ]);

            $expense->markAsPaid(
                $validated['payment_method'], 
                $validated['reference'],
                $validated['payment_date'] ?? now()
            );

            $this->logApiActivity('expenses.mark_paid', ['expense_id' => $expense->id]);

            return $this->success($expense->fresh()->load('supplier'), 'Gasto marcado como pagado');
            
        } catch (\Exception $e) {
            return $this->handleApiException($e);
        }
    }

    public function categories(Request $request): JsonResponse
    {
        $permissionCheck = $this->checkApiPermission('expenses.view');
        if ($permissionCheck) return $permissionCheck;
        
        $categories = Expense::where('tenant_id', $this->getTenantId($request))
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values();

        return $this->success($categories->toArray());
    }

    public function statistics(Request $request): JsonResponse
    {
        $permissionCheck = $this->checkApiPermission('expenses.view');
        if ($permissionCheck) return $permissionCheck;
        
        $tenantId = $this->getTenantId($request);
        
        $stats = [
            'total_amount' => Expense::where('tenant_id', $tenantId)
                ->whereNotIn('status', ['cancelled', 'draft'])
                ->sum('total_amount'),
            'pending_amount' => Expense::where('tenant_id', $tenantId)
                ->where('status', 'pending')
                ->sum('balance'),
            'this_month' => Expense::where('tenant_id', $tenantId)
                ->whereNotIn('status', ['cancelled', 'draft'])
                ->whereMonth('issue_date', now()->month)
                ->whereYear('issue_date', now()->year)
                ->sum('total_amount'),
            'overdue_amount' => Expense::where('tenant_id', $tenantId)
                ->where('due_date', '<', now())
                ->where('status', 'pending')
                ->sum('balance'),
            'tax_credit' => Expense::where('tenant_id', $tenantId)
                ->whereNotIn('status', ['cancelled', 'draft'])
                ->whereMonth('issue_date', now()->month)
                ->whereYear('issue_date', now()->year)
                ->sum('tax_amount'),
            'by_category' => Expense::where('tenant_id', $tenantId)
                ->whereNotIn('status', ['cancelled', 'draft'])
                ->selectRaw('category, COUNT(*) as count, SUM(total_amount) as total')
                ->groupBy('category')
                ->get(),
            'by_supplier' => Expense::where('tenant_id', $tenantId)
                ->whereNotIn('status', ['cancelled', 'draft'])
                ->with('supplier:id,name')
                ->selectRaw('supplier_id, COUNT(*) as count, SUM(total_amount) as total')
                ->groupBy('supplier_id')
                ->orderByDesc('total')
                ->limit(10)
                ->get()
        ];

        return $this->success($stats);
    }
}