<?php

namespace App\Modules\Accounting\Services;

use App\Models\Expense;
use App\Models\Supplier;
use App\Models\User;
use App\Models\ExpenseApproval;
use App\Models\ExpensePayment;
use App\Models\JournalEntry;
use App\Models\Attachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ExpenseService
{
    public function getFilteredExpenses(array $filters, int $perPage = 20): array
    {
        $tenantId = auth()->user()->tenant_id;
        
        $query = Expense::where('tenant_id', $tenantId)
            ->with(['supplier', 'approvals.user', 'payments']);

        // Apply filters
        if (!empty($filters['search'])) {
            $search = $filters['search'];
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

        if (!empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (!empty($filters['document_type'])) {
            $query->where('document_type', $filters['document_type']);
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'overdue') {
                $query->where('status', 'pending')
                      ->where('due_date', '<', now());
            } else {
                $query->where('status', $filters['status']);
            }
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['approval_status'])) {
            $query->where('approval_status', $filters['approval_status']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        if (!empty($filters['amount_from'])) {
            $query->where('total_amount', '>=', $filters['amount_from']);
        }

        if (!empty($filters['amount_to'])) {
            $query->where('total_amount', '<=', $filters['amount_to']);
        }

        $expenses = $query->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        // Calculate statistics
        $statistics = $this->calculateExpenseStatistics($tenantId, $filters);

        return [
            'expenses' => $expenses,
            'statistics' => $statistics,
        ];
    }

    public function createExpense(array $data, int $tenantId, int $userId): Expense
    {
        return DB::transaction(function () use ($data, $tenantId, $userId) {
            // Generate expense number
            $number = $this->generateExpenseNumber($tenantId);

            // Calculate amounts
            $netAmount = $data['net_amount'];
            $taxAmount = $data['tax_amount'] ?? 0;
            $otherTaxes = $data['other_taxes'] ?? 0;
            $totalAmount = $data['total_amount'] ?? ($netAmount + $taxAmount + $otherTaxes);

            // Determine approval status based on workflow
            $approvalStatus = $this->determineInitialApprovalStatus($totalAmount);

            $expense = Expense::create([
                'number' => $number,
                'tenant_id' => $tenantId,
                'supplier_id' => $data['supplier_id'] ?? null,
                'document_type' => $data['document_type'],
                'supplier_document_number' => $data['supplier_document_number'] ?? null,
                'date' => $data['date'],
                'due_date' => $data['due_date'] ?? null,
                'net_amount' => $netAmount,
                'tax_amount' => $taxAmount,
                'other_taxes' => $otherTaxes,
                'total_amount' => $totalAmount,
                'balance' => $data['status'] === 'paid' ? 0 : $totalAmount,
                'payment_method' => $data['payment_method'] ?? null,
                'status' => $data['status'] ?? 'pending',
                'approval_status' => $approvalStatus,
                'category' => $data['category'] ?? null,
                'subcategory' => $data['subcategory'] ?? null,
                'description' => $data['description'] ?? null,
                'reference' => $data['reference'] ?? null,
                'created_by' => $userId,
            ]);

            // Create journal entry if configured
            if (config('accounting.integrations.auto_create_journal_entries')) {
                $this->createJournalEntry($expense);
            }

            return $expense;
        });
    }

    public function updateExpense(Expense $expense, array $data): Expense
    {
        return DB::transaction(function () use ($expense, $data) {
            // Calculate amounts
            $netAmount = $data['net_amount'];
            $taxAmount = $data['tax_amount'] ?? 0;
            $otherTaxes = $data['other_taxes'] ?? 0;
            $totalAmount = $data['total_amount'] ?? ($netAmount + $taxAmount + $otherTaxes);

            // Update balance if total amount changed
            $balanceAdjustment = $totalAmount - $expense->total_amount;
            $newBalance = max(0, $expense->balance + $balanceAdjustment);

            $expense->update([
                'supplier_id' => $data['supplier_id'] ?? null,
                'document_type' => $data['document_type'],
                'supplier_document_number' => $data['supplier_document_number'] ?? null,
                'date' => $data['date'],
                'due_date' => $data['due_date'] ?? null,
                'net_amount' => $netAmount,
                'tax_amount' => $taxAmount,
                'other_taxes' => $otherTaxes,
                'total_amount' => $totalAmount,
                'balance' => $newBalance,
                'payment_method' => $data['payment_method'] ?? null,
                'status' => $data['status'] ?? $expense->status,
                'category' => $data['category'] ?? null,
                'subcategory' => $data['subcategory'] ?? null,
                'description' => $data['description'] ?? null,
                'reference' => $data['reference'] ?? null,
                'updated_by' => auth()->id(),
            ]);

            // Update journal entry if exists
            if ($expense->journalEntries->isNotEmpty()) {
                $this->updateJournalEntry($expense);
            }

            return $expense;
        });
    }

    public function deleteExpense(Expense $expense): void
    {
        DB::transaction(function () use ($expense) {
            // Delete related records
            $expense->approvals()->delete();
            $expense->payments()->delete();
            
            // Reverse journal entries
            foreach ($expense->journalEntries as $entry) {
                if ($entry->status === 'posted') {
                    $this->reverseJournalEntry($entry);
                } else {
                    $entry->delete();
                }
            }

            // Delete attachments
            foreach ($expense->attachments as $attachment) {
                Storage::delete($attachment->file_path);
                $attachment->delete();
            }
            
            // Delete the expense
            $expense->delete();
        });
    }

    public function approveExpense(Expense $expense, User $user): array
    {
        if (!$this->canUserApprove($expense, $user)) {
            return [
                'success' => false,
                'message' => 'No tiene permisos para aprobar este gasto.',
            ];
        }

        if ($expense->approval_status === 'approved') {
            return [
                'success' => false,
                'message' => 'El gasto ya está aprobado.',
            ];
        }

        return DB::transaction(function () use ($expense, $user) {
            // Create approval record
            ExpenseApproval::create([
                'expense_id' => $expense->id,
                'user_id' => $user->id,
                'status' => 'approved',
                'approved_at' => now(),
                'comments' => null,
            ]);

            // Update expense approval status
            $newStatus = $this->calculateApprovalStatus($expense, $user);
            $expense->update([
                'approval_status' => $newStatus,
                'approved_by' => $user->id,
                'approved_at' => $newStatus === 'approved' ? now() : null,
            ]);

            return [
                'success' => true,
                'message' => $newStatus === 'approved' 
                    ? 'Gasto aprobado exitosamente.' 
                    : 'Aprobación registrada. Pendiente de aprobación superior.',
            ];
        });
    }

    public function rejectExpense(Expense $expense, User $user, string $reason): array
    {
        if (!$this->canUserApprove($expense, $user)) {
            return [
                'success' => false,
                'message' => 'No tiene permisos para rechazar este gasto.',
            ];
        }

        return DB::transaction(function () use ($expense, $user, $reason) {
            // Create rejection record
            ExpenseApproval::create([
                'expense_id' => $expense->id,
                'user_id' => $user->id,
                'status' => 'rejected',
                'rejected_at' => now(),
                'comments' => $reason,
            ]);

            // Update expense status
            $expense->update([
                'approval_status' => 'rejected',
                'rejected_by' => $user->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            return [
                'success' => true,
                'message' => 'Gasto rechazado exitosamente.',
            ];
        });
    }

    public function markAsPaid(Expense $expense, array $paymentData): array
    {
        if ($expense->status === 'paid') {
            return [
                'success' => false,
                'message' => 'El gasto ya está marcado como pagado.',
            ];
        }

        return DB::transaction(function () use ($expense, $paymentData) {
            $amountPaid = $paymentData['amount_paid'];
            $newBalance = $expense->balance - $amountPaid;

            // Create payment record
            ExpensePayment::create([
                'expense_id' => $expense->id,
                'amount' => $amountPaid,
                'payment_date' => $paymentData['payment_date'],
                'payment_method' => $paymentData['payment_method'],
                'reference' => $paymentData['payment_reference'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // Update expense
            $newStatus = $newBalance <= 0 ? 'paid' : 'partial';
            $expense->update([
                'balance' => max(0, $newBalance),
                'status' => $newStatus,
                'paid_at' => $newStatus === 'paid' ? now() : null,
            ]);

            return [
                'success' => true,
                'message' => $newStatus === 'paid' 
                    ? 'Gasto marcado como pagado.' 
                    : "Pago parcial registrado. Saldo pendiente: $" . number_format($newBalance, 2),
            ];
        });
    }

    public function duplicateExpense(Expense $expense, int $userId): Expense
    {
        return DB::transaction(function () use ($expense, $userId) {
            $newExpense = $expense->replicate();
            $newExpense->number = $this->generateExpenseNumber($expense->tenant_id);
            $newExpense->status = 'draft';
            $newExpense->approval_status = 'pending';
            $newExpense->balance = $newExpense->total_amount;
            $newExpense->created_by = $userId;
            $newExpense->approved_by = null;
            $newExpense->approved_at = null;
            $newExpense->paid_at = null;
            $newExpense->save();

            return $newExpense;
        });
    }

    public function attachFile(Expense $expense, UploadedFile $file, ?string $description = null): Attachment
    {
        $filename = time() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs("expenses/{$expense->tenant_id}/{$expense->id}", $filename);

        return Attachment::create([
            'attachable_type' => Expense::class,
            'attachable_id' => $expense->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'description' => $description,
            'uploaded_by' => auth()->id(),
        ]);
    }

    public function removeFile(Expense $expense, int $fileId): void
    {
        $attachment = Attachment::where('attachable_type', Expense::class)
            ->where('attachable_id', $expense->id)
            ->findOrFail($fileId);

        Storage::delete($attachment->file_path);
        $attachment->delete();
    }

    public function getRelatedExpenses(Expense $expense): Collection
    {
        return Expense::where('tenant_id', $expense->tenant_id)
            ->where('id', '!=', $expense->id)
            ->where(function ($query) use ($expense) {
                $query->where('supplier_id', $expense->supplier_id)
                      ->orWhere('category', $expense->category);
            })
            ->with('supplier')
            ->orderBy('date', 'desc')
            ->limit(5)
            ->get();
    }

    public function canUserApprove(Expense $expense, User $user): bool
    {
        $workflow = config('accounting.approval_workflow');
        
        if (!$workflow['enabled']) {
            return $user->hasPermissionTo('expenses.approve');
        }

        $amount = $expense->total_amount;
        
        if ($amount <= $workflow['thresholds']['auto_approve']) {
            return true;
        }

        if ($amount <= $workflow['thresholds']['manager_approval']) {
            return $user->hasRole(['manager', 'director', 'admin']);
        }

        if ($amount <= $workflow['thresholds']['director_approval']) {
            return $user->hasRole(['director', 'admin']);
        }

        return $user->hasRole(['admin']);
    }

    public function bulkApprove(array $expenseIds, User $user, int $tenantId): array
    {
        $approved = 0;
        $errors = 0;

        $expenses = Expense::where('tenant_id', $tenantId)
            ->whereIn('id', $expenseIds)
            ->where('approval_status', 'pending')
            ->get();

        foreach ($expenses as $expense) {
            try {
                $result = $this->approveExpense($expense, $user);
                if ($result['success']) {
                    $approved++;
                } else {
                    $errors++;
                }
            } catch (\Exception $e) {
                $errors++;
            }
        }

        return [
            'approved' => $approved,
            'errors' => $errors,
        ];
    }

    public function bulkReject(array $expenseIds, User $user, string $reason, int $tenantId): array
    {
        $rejected = 0;
        $errors = 0;

        $expenses = Expense::where('tenant_id', $tenantId)
            ->whereIn('id', $expenseIds)
            ->where('approval_status', 'pending')
            ->get();

        foreach ($expenses as $expense) {
            try {
                $result = $this->rejectExpense($expense, $user, $reason);
                if ($result['success']) {
                    $rejected++;
                } else {
                    $errors++;
                }
            } catch (\Exception $e) {
                $errors++;
            }
        }

        return [
            'rejected' => $rejected,
            'errors' => $errors,
        ];
    }

    public function bulkExport(array $expenseIds, string $format, int $tenantId)
    {
        $expenses = Expense::where('tenant_id', $tenantId)
            ->whereIn('id', $expenseIds)
            ->with(['supplier', 'approvals.user'])
            ->get();

        switch ($format) {
            case 'excel':
                return Excel::download(
                    new \App\Exports\ExpensesExport($expenses),
                    'gastos-' . now()->format('Y-m-d') . '.xlsx'
                );
                
            case 'csv':
                return Excel::download(
                    new \App\Exports\ExpensesExport($expenses),
                    'gastos-' . now()->format('Y-m-d') . '.csv'
                );
                
            case 'pdf':
                $pdf = Pdf::loadView('accounting.expenses.export-pdf', [
                    'expenses' => $expenses,
                    'company' => auth()->user()->tenant,
                ]);
                
                return $pdf->download('gastos-' . now()->format('Y-m-d') . '.pdf');
                
            default:
                throw new \Exception('Formato de exportación no válido.');
        }
    }

    public function getReportStatistics(int $tenantId): array
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;

        return [
            'total_expenses' => Expense::where('tenant_id', $tenantId)
                ->where('status', '!=', 'cancelled')
                ->count(),
            
            'total_amount' => Expense::where('tenant_id', $tenantId)
                ->where('status', '!=', 'cancelled')
                ->sum('total_amount'),
            
            'pending_approval' => Expense::where('tenant_id', $tenantId)
                ->where('approval_status', 'pending')
                ->count(),
            
            'this_month_amount' => Expense::where('tenant_id', $tenantId)
                ->whereMonth('date', $currentMonth)
                ->whereYear('date', $currentYear)
                ->where('status', '!=', 'cancelled')
                ->sum('total_amount'),
            
            'by_category' => Expense::where('tenant_id', $tenantId)
                ->where('status', '!=', 'cancelled')
                ->selectRaw('category, SUM(total_amount) as total, COUNT(*) as count')
                ->groupBy('category')
                ->get(),
            
            'by_status' => Expense::where('tenant_id', $tenantId)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->get(),
        ];
    }

    protected function calculateExpenseStatistics(int $tenantId, array $filters = []): array
    {
        $baseQuery = Expense::where('tenant_id', $tenantId);
        
        // Apply same filters for consistency
        if (!empty($filters['date_from'])) {
            $baseQuery->whereDate('date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $baseQuery->whereDate('date', '<=', $filters['date_to']);
        }

        return [
            'total_amount' => (clone $baseQuery)->where('status', '!=', 'cancelled')->sum('total_amount'),
            'pending_amount' => (clone $baseQuery)->where('status', 'pending')->sum('balance'),
            'overdue_amount' => (clone $baseQuery)->where('status', 'pending')
                ->where('due_date', '<', now())->sum('balance'),
            'this_month_amount' => Expense::where('tenant_id', $tenantId)
                ->whereMonth('date', now()->month)
                ->whereYear('date', now()->year)
                ->where('status', '!=', 'cancelled')
                ->sum('total_amount'),
            'pending_approval_count' => (clone $baseQuery)->where('approval_status', 'pending')->count(),
            'tax_credit_amount' => (clone $baseQuery)->where('status', '!=', 'cancelled')->sum('tax_amount'),
        ];
    }

    protected function generateExpenseNumber(int $tenantId): string
    {
        $lastExpense = Expense::where('tenant_id', $tenantId)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastExpense ? (intval(substr($lastExpense->number, -6)) + 1) : 1;
        
        return 'EXP-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    protected function determineInitialApprovalStatus(float $amount): string
    {
        $workflow = config('accounting.approval_workflow');
        
        if (!$workflow['enabled']) {
            return 'approved';
        }

        if ($amount <= $workflow['thresholds']['auto_approve']) {
            return 'approved';
        }

        return 'pending';
    }

    protected function calculateApprovalStatus(Expense $expense, User $user): string
    {
        $workflow = config('accounting.approval_workflow');
        $amount = $expense->total_amount;

        if ($amount <= $workflow['thresholds']['manager_approval']) {
            return 'approved';
        }

        if ($amount <= $workflow['thresholds']['director_approval'] && $user->hasRole(['director', 'admin'])) {
            return 'approved';
        }

        if ($user->hasRole(['admin'])) {
            return 'approved';
        }

        return 'pending';
    }

    protected function createJournalEntry(Expense $expense): void
    {
        // This would create a journal entry for the expense
        // Implementation depends on chart of accounts structure
    }

    protected function updateJournalEntry(Expense $expense): void
    {
        // This would update existing journal entries
        // Implementation depends on chart of accounts structure
    }

    protected function reverseJournalEntry(JournalEntry $entry): void
    {
        // This would reverse a journal entry
        // Implementation depends on journal entry structure
    }
}