<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Supplier;
use App\Traits\ChecksPermissions;
use App\Modules\Accounting\Services\ExpenseService;
use App\Modules\Accounting\Requests\ExpenseRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class ExpenseController extends Controller
{
    use ChecksPermissions;

    protected $expenseService;

    public function __construct(ExpenseService $expenseService)
    {
        $this->middleware(['auth', 'verified', 'check.subscription', 'check.module:accounting']);
        $this->expenseService = $expenseService;
    }

    public function index(Request $request)
    {
        $this->checkPermission('expenses.view');
        
        $filters = $request->only([
            'search', 'supplier_id', 'document_type', 'status', 'category',
            'date_from', 'date_to', 'amount_from', 'amount_to', 'approval_status'
        ]);

        $result = $this->expenseService->getFilteredExpenses($filters, $request->get('per_page', 20));

        $suppliers = Supplier::where('tenant_id', auth()->user()->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'rut']);

        return Inertia::render('Accounting/Expenses/Index', [
            'expenses' => $result['expenses'],
            'statistics' => $result['statistics'],
            'suppliers' => $suppliers,
            'categories' => config('accounting.expense_categories'),
            'documentTypes' => config('accounting.expense_document_types'),
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        $this->checkPermission('expenses.create');
        
        $suppliers = Supplier::where('tenant_id', auth()->user()->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'rut']);

        return Inertia::render('Accounting/Expenses/Create', [
            'suppliers' => $suppliers,
            'categories' => config('accounting.expense_categories'),
            'documentTypes' => config('accounting.expense_document_types'),
            'approvalWorkflow' => config('accounting.approval_workflow'),
        ]);
    }

    public function store(ExpenseRequest $request)
    {
        $this->checkPermission('expenses.create');
        
        try {
            $expense = $this->expenseService->createExpense(
                $request->validated(),
                auth()->user()->tenant_id,
                auth()->id()
            );

            return redirect()->route('accounting.expenses.index')
                ->with('success', 'Gasto registrado exitosamente.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function show(Expense $expense)
    {
        $this->checkPermission('expenses.view');
        $this->authorize('view', $expense);
        
        $expense->load([
            'supplier',
            'approvals.user',
            'payments.payment',
            'journalEntries',
            'attachments',
        ]);

        $relatedExpenses = $this->expenseService->getRelatedExpenses($expense);

        return Inertia::render('Accounting/Expenses/Show', [
            'expense' => $expense,
            'relatedExpenses' => $relatedExpenses,
            'canApprove' => $this->expenseService->canUserApprove($expense, auth()->user()),
            'approvalWorkflow' => config('accounting.approval_workflow'),
        ]);
    }

    public function edit(Expense $expense)
    {
        $this->checkPermission('expenses.edit');
        $this->authorize('update', $expense);
        
        if ($expense->status === 'paid' || $expense->approval_status === 'approved') {
            return back()->withErrors(['error' => 'No se puede editar un gasto pagado o aprobado.']);
        }
        
        $suppliers = Supplier::where('tenant_id', auth()->user()->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'rut']);

        $expense->load(['attachments']);

        return Inertia::render('Accounting/Expenses/Edit', [
            'expense' => $expense,
            'suppliers' => $suppliers,
            'categories' => config('accounting.expense_categories'),
            'documentTypes' => config('accounting.expense_document_types'),
        ]);
    }

    public function update(ExpenseRequest $request, Expense $expense)
    {
        $this->checkPermission('expenses.edit');
        $this->authorize('update', $expense);
        
        if ($expense->status === 'paid' || $expense->approval_status === 'approved') {
            return back()->withErrors(['error' => 'No se puede modificar un gasto pagado o aprobado.']);
        }

        try {
            $this->expenseService->updateExpense($expense, $request->validated());

            return redirect()->route('accounting.expenses.index')
                ->with('success', 'Gasto actualizado exitosamente.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function destroy(Expense $expense)
    {
        $this->checkPermission('expenses.delete');
        $this->authorize('delete', $expense);
        
        if ($expense->status === 'paid' || $expense->approval_status === 'approved') {
            return back()->withErrors(['error' => 'No se puede eliminar un gasto pagado o aprobado.']);
        }

        try {
            $this->expenseService->deleteExpense($expense);

            return redirect()->route('accounting.expenses.index')
                ->with('success', 'Gasto eliminado exitosamente.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function approve(Expense $expense)
    {
        $this->checkPermission('expenses.approve');
        $this->authorize('approve', $expense);

        try {
            $result = $this->expenseService->approveExpense($expense, auth()->user());

            if ($result['success']) {
                return redirect()->back()->with('success', $result['message']);
            } else {
                return redirect()->back()->withErrors(['error' => $result['message']]);
            }

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function reject(Request $request, Expense $expense)
    {
        $this->checkPermission('expenses.reject');
        $this->authorize('reject', $expense);

        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        try {
            $result = $this->expenseService->rejectExpense(
                $expense,
                auth()->user(),
                $request->reason
            );

            if ($result['success']) {
                return redirect()->back()->with('success', $result['message']);
            } else {
                return redirect()->back()->withErrors(['error' => $result['message']]);
            }

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function markAsPaid(Request $request, Expense $expense)
    {
        $this->checkPermission('expenses.edit');
        $this->authorize('markAsPaid', $expense);

        $request->validate([
            'payment_date' => 'required|date|before_or_equal:today',
            'payment_method' => 'required|string',
            'payment_reference' => 'nullable|string|max:255',
            'amount_paid' => 'required|numeric|min:0.01|max:' . $expense->balance,
        ]);

        try {
            $result = $this->expenseService->markAsPaid($expense, $request->validated());

            if ($result['success']) {
                return redirect()->back()->with('success', $result['message']);
            } else {
                return redirect()->back()->withErrors(['error' => $result['message']]);
            }

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function registerPayment(Request $request, Expense $expense)
    {
        $this->checkPermission('expenses.edit');
        $this->authorize('registerPayment', $expense);

        $request->validate([
            'payment_id' => 'required|exists:payments,id',
            'amount' => 'required|numeric|min:0.01|max:' . $expense->balance,
        ]);

        try {
            $result = $this->expenseService->registerPayment(
                $expense,
                $request->payment_id,
                $request->amount
            );

            if ($result['success']) {
                return redirect()->back()->with('success', $result['message']);
            } else {
                return redirect()->back()->withErrors(['error' => $result['message']]);
            }

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function duplicate(Expense $expense)
    {
        $this->checkPermission('expenses.create');
        $this->authorize('view', $expense);

        try {
            $newExpense = $this->expenseService->duplicateExpense($expense, auth()->id());

            return redirect()->route('accounting.expenses.edit', $newExpense)
                ->with('success', 'Gasto duplicado exitosamente. Puede modificar los datos necesarios.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function attachFile(Request $request, Expense $expense)
    {
        $this->checkPermission('expenses.edit');
        $this->authorize('update', $expense);

        $request->validate([
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,gif,doc,docx,xls,xlsx',
            'description' => 'nullable|string|max:255',
        ]);

        try {
            $attachment = $this->expenseService->attachFile(
                $expense,
                $request->file('file'),
                $request->description
            );

            return redirect()->back()->with('success', 'Archivo adjuntado exitosamente.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function removeFile(Expense $expense, $fileId)
    {
        $this->checkPermission('expenses.edit');
        $this->authorize('update', $expense);

        try {
            $this->expenseService->removeFile($expense, $fileId);

            return redirect()->back()->with('success', 'Archivo eliminado exitosamente.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function bulkApprove(Request $request)
    {
        $this->checkPermission('expenses.approve');

        $request->validate([
            'expense_ids' => 'required|array|min:1',
            'expense_ids.*' => 'exists:expenses,id',
        ]);

        try {
            $result = $this->expenseService->bulkApprove(
                $request->expense_ids,
                auth()->user(),
                auth()->user()->tenant_id
            );

            return redirect()->back()->with('success', 
                "Aprobados {$result['approved']} gastos. {$result['errors']} errores."
            );

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function bulkReject(Request $request)
    {
        $this->checkPermission('expenses.reject');

        $request->validate([
            'expense_ids' => 'required|array|min:1',
            'expense_ids.*' => 'exists:expenses,id',
            'reason' => 'required|string|max:1000',
        ]);

        try {
            $result = $this->expenseService->bulkReject(
                $request->expense_ids,
                auth()->user(),
                $request->reason,
                auth()->user()->tenant_id
            );

            return redirect()->back()->with('success', 
                "Rechazados {$result['rejected']} gastos. {$result['errors']} errores."
            );

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function bulkExport(Request $request)
    {
        $this->checkPermission('expenses.export');

        $request->validate([
            'expense_ids' => 'required|array|min:1',
            'expense_ids.*' => 'exists:expenses,id',
            'format' => 'required|in:excel,csv,pdf',
        ]);

        try {
            $export = $this->expenseService->bulkExport(
                $request->expense_ids,
                $request->format,
                auth()->user()->tenant_id
            );

            return $export;

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function reports()
    {
        $this->checkPermission('expenses.reports');

        $statistics = $this->expenseService->getReportStatistics(auth()->user()->tenant_id);

        return Inertia::render('Accounting/Expenses/Reports', [
            'statistics' => $statistics,
            'categories' => config('accounting.expense_categories'),
            'documentTypes' => config('accounting.expense_document_types'),
        ]);
    }

    public function reportByCategory(Request $request)
    {
        $this->checkPermission('expenses.reports');

        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'format' => 'nullable|in:json,excel,pdf',
        ]);

        try {
            $report = $this->expenseService->generateCategoryReport(
                auth()->user()->tenant_id,
                $request->date_from,
                $request->date_to
            );

            if ($request->format === 'excel' || $request->format === 'pdf') {
                return $this->expenseService->exportCategoryReport($report, $request->format);
            }

            return response()->json($report);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function reportBySupplier(Request $request)
    {
        $this->checkPermission('expenses.reports');

        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'format' => 'nullable|in:json,excel,pdf',
        ]);

        try {
            $report = $this->expenseService->generateSupplierReport(
                auth()->user()->tenant_id,
                $request->date_from,
                $request->date_to,
                $request->supplier_id
            );

            if ($request->format === 'excel' || $request->format === 'pdf') {
                return $this->expenseService->exportSupplierReport($report, $request->format);
            }

            return response()->json($report);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function reportByPeriod(Request $request)
    {
        $this->checkPermission('expenses.reports');

        $request->validate([
            'period' => 'required|in:week,month,quarter,year',
            'year' => 'required|integer|min:2020|max:' . (date('Y') + 1),
            'format' => 'nullable|in:json,excel,pdf',
        ]);

        try {
            $report = $this->expenseService->generatePeriodReport(
                auth()->user()->tenant_id,
                $request->period,
                $request->year
            );

            if ($request->format === 'excel' || $request->format === 'pdf') {
                return $this->expenseService->exportPeriodReport($report, $request->format);
            }

            return response()->json($report);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function reportPendingApproval()
    {
        $this->checkPermission('expenses.reports');

        try {
            $report = $this->expenseService->generatePendingApprovalReport(
                auth()->user()->tenant_id,
                auth()->user()
            );

            return response()->json($report);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}