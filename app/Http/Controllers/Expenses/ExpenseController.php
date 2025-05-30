<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Supplier;
use App\Traits\ChecksPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class ExpenseController extends Controller
{
    use ChecksPermissions;
    public function index(Request $request)
    {
        $this->checkPermission('expenses.view');
        $tenantId = auth()->user()->tenant_id;
        
        $query = Expense::where('tenant_id', $tenantId)
            ->with(['supplier']);

        // Filtros
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                  ->orWhere('supplier_document_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function ($supplierQuery) use ($search) {
                      $supplierQuery->where('name', 'like', "%{$search}%")
                                   ->orWhere('rut', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('supplier_id')) {
            $query->bySupplier($request->supplier_id);
        }

        if ($request->filled('document_type')) {
            $query->byDocumentType($request->document_type);
        }

        if ($request->filled('status')) {
            if ($request->status === 'overdue') {
                $query->overdue();
            } else {
                $query->where('status', $request->status);
            }
        }

        if ($request->filled('category')) {
            $query->byCategory($request->category);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('issue_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('issue_date', '<=', $request->date_to);
        }

        $expenses = $query->orderBy('issue_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Estadísticas optimizadas
        $currentMonth = now()->month;
        $currentYear = now()->year;
        
        $statistics = Expense::where('tenant_id', $tenantId)
            ->selectRaw("
                SUM(CASE WHEN status NOT IN ('cancelled', 'draft') THEN total_amount ELSE 0 END) as total_amount,
                SUM(CASE WHEN status = 'pending' THEN balance ELSE 0 END) as pending_amount,
                SUM(CASE WHEN status NOT IN ('cancelled', 'draft') AND MONTH(issue_date) = ? AND YEAR(issue_date) = ? THEN total_amount ELSE 0 END) as this_month,
                SUM(CASE WHEN status = 'pending' AND due_date < NOW() THEN balance ELSE 0 END) as overdue_amount,
                SUM(CASE WHEN status NOT IN ('cancelled', 'draft') AND MONTH(issue_date) = ? AND YEAR(issue_date) = ? THEN tax_amount ELSE 0 END) as tax_credit
            ", [$currentMonth, $currentYear, $currentMonth, $currentYear])
            ->first()
            ->toArray();

        $suppliers = Supplier::where('tenant_id', $tenantId)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'rut']);

        $categories = Expense::where('tenant_id', $tenantId)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values();

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
            'statistics' => $statistics,
            'suppliers' => $suppliers,
            'categories' => $categories,
            'filters' => $request->all(['search', 'supplier_id', 'document_type', 'status', 'category', 'date_from', 'date_to'])
        ]);
    }

    public function create()
    {
        $this->checkPermission('expenses.create');
        $tenantId = auth()->user()->tenant_id;
        
        $suppliers = Supplier::where('tenant_id', $tenantId)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'rut']);

        $categories = Expense::where('tenant_id', $tenantId)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values();

        return Inertia::render('Expenses/Create', [
            'suppliers' => $suppliers,
            'categories' => $categories
        ]);
    }

    public function store(Request $request)
    {
        $this->checkPermission('expenses.create');
        $request->validate([
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

        $tenantId = auth()->user()->tenant_id;

        // Verificar que el proveedor pertenece al tenant
        if ($request->supplier_id) {
            $supplier = Supplier::where('tenant_id', $tenantId)
                ->findOrFail($request->supplier_id);
        }

        // Calcular totales
        $netAmount = $request->net_amount;
        $taxAmount = $request->tax_amount ?? 0;
        $otherTaxes = $request->other_taxes ?? 0;
        $totalAmount = $request->total_amount ?? ($netAmount + $taxAmount + $otherTaxes);

        $expense = Expense::create([
            'number' => Expense::generateNumber($tenantId),
            'tenant_id' => $tenantId,
            'supplier_id' => $request->supplier_id,
            'document_type' => $request->document_type,
            'supplier_document_number' => $request->supplier_document_number,
            'issue_date' => $request->issue_date,
            'due_date' => $request->due_date,
            'net_amount' => $netAmount,
            'tax_amount' => $taxAmount,
            'other_taxes' => $otherTaxes,
            'total_amount' => $totalAmount,
            'balance' => $request->status === 'paid' ? 0 : $totalAmount,
            'payment_method' => $request->payment_method,
            'status' => $request->status,
            'category' => $request->category,
            'description' => $request->description,
            'reference' => $request->reference
        ]);

        return redirect()->route('expenses.index')
            ->with('success', 'Gasto registrado exitosamente.');
    }

    public function show(Expense $expense)
    {
        $this->checkPermission('expenses.view');
        
        if ($expense->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        $expense->load(['supplier']);

        return Inertia::render('Expenses/Show', [
            'expense' => $expense
        ]);
    }

    public function edit(Expense $expense)
    {
        $this->checkPermission('expenses.edit');
        
        if ($expense->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        $tenantId = auth()->user()->tenant_id;
        
        $suppliers = Supplier::where('tenant_id', $tenantId)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'rut']);

        $categories = Expense::where('tenant_id', $tenantId)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values();

        return Inertia::render('Expenses/Edit', [
            'expense' => $expense,
            'suppliers' => $suppliers,
            'categories' => $categories
        ]);
    }

    public function update(Request $request, Expense $expense)
    {
        $this->checkPermission('expenses.edit');
        
        if ($expense->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        $request->validate([
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

        $tenantId = auth()->user()->tenant_id;

        // Verificar que el proveedor pertenece al tenant
        if ($request->supplier_id) {
            $supplier = Supplier::where('tenant_id', $tenantId)
                ->findOrFail($request->supplier_id);
        }

        // Calcular totales
        $netAmount = $request->net_amount;
        $taxAmount = $request->tax_amount ?? 0;
        $otherTaxes = $request->other_taxes ?? 0;
        $totalAmount = $request->total_amount ?? ($netAmount + $taxAmount + $otherTaxes);

        // Calcular nuevo saldo
        $paidAmount = $expense->total_amount - $expense->balance;
        $newBalance = max(0, $totalAmount - $paidAmount);
        
        if ($request->status === 'paid') {
            $newBalance = 0;
        } elseif ($request->status === 'cancelled') {
            $newBalance = 0;
        }

        $expense->update([
            'supplier_id' => $request->supplier_id,
            'document_type' => $request->document_type,
            'supplier_document_number' => $request->supplier_document_number,
            'issue_date' => $request->issue_date,
            'due_date' => $request->due_date,
            'net_amount' => $netAmount,
            'tax_amount' => $taxAmount,
            'other_taxes' => $otherTaxes,
            'total_amount' => $totalAmount,
            'balance' => $newBalance,
            'payment_method' => $request->payment_method,
            'status' => $request->status,
            'category' => $request->category,
            'description' => $request->description,
            'reference' => $request->reference
        ]);

        return redirect()->route('expenses.index')
            ->with('success', 'Gasto actualizado exitosamente.');
    }

    public function destroy(Expense $expense)
    {
        $this->checkPermission('expenses.delete');
        
        if ($expense->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        $expense->delete();

        return redirect()->route('expenses.index')
            ->with('success', 'Gasto eliminado exitosamente.');
    }

    public function markAsPaid(Request $request, Expense $expense)
    {
        $this->checkPermission('expenses.edit');
        
        if ($expense->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        $request->validate([
            'payment_method' => 'required|in:cash,bank_transfer,check,credit_card,debit_card,electronic,credit_account,other',
            'reference' => 'nullable|string|max:255'
        ]);

        $expense->markAsPaid($request->payment_method, $request->reference);

        return redirect()->route('expenses.show', $expense)
            ->with('success', 'Gasto marcado como pagado.');
    }

    public function registerPayment(Request $request, Expense $expense)
    {
        $this->checkPermission('expenses.edit');
        
        if ($expense->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . $expense->balance,
            'payment_method' => 'required|in:cash,bank_transfer,check,credit_card,debit_card,electronic,credit_account,other',
            'reference' => 'nullable|string|max:255'
        ]);

        $expense->registerPayment(
            $request->amount,
            $request->payment_method,
            $request->reference
        );

        return redirect()->route('expenses.show', $expense)
            ->with('success', 'Pago registrado exitosamente.');
    }
}