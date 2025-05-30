<?php

namespace App\Modules\Invoicing\Services;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Customer;
use App\Models\TaxDocument;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class PaymentService
{
    public function getFilteredPayments(array $filters, int $perPage = 20): array
    {
        $tenantId = auth()->user()->tenant_id;
        
        $query = Payment::where('tenant_id', $tenantId)
            ->with(['customer', 'allocations.taxDocument', 'bankTransaction']);

        // Apply filters
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($customerQuery) use ($search) {
                      $customerQuery->where('name', 'like', "%{$search}%")
                                   ->orWhere('rut', 'like', "%{$search}%");
                  });
            });
        }

        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (!empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        if (!empty($filters['amount_from'])) {
            $query->where('amount', '>=', $filters['amount_from']);
        }

        if (!empty($filters['amount_to'])) {
            $query->where('amount', '<=', $filters['amount_to']);
        }

        $payments = $query->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        // Calculate statistics
        $statistics = $this->calculatePaymentStatistics($tenantId, $filters);

        return [
            'payments' => $payments,
            'statistics' => $statistics,
        ];
    }

    public function createPayment(array $data, int $tenantId): Payment
    {
        return DB::transaction(function () use ($data, $tenantId) {
            // Generate payment number
            $number = $this->generatePaymentNumber($tenantId);

            // Calculate total allocations
            $totalAllocations = collect($data['allocations'] ?? [])->sum('amount');

            // Create payment
            $payment = Payment::create([
                'number' => $number,
                'tenant_id' => $tenantId,
                'customer_id' => $data['customer_id'],
                'date' => $data['date'],
                'amount' => $data['amount'],
                'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? null,
                'bank' => $data['bank'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'pending',
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'remaining_amount' => $data['amount'] - $totalAllocations,
                'created_by' => auth()->id(),
            ]);

            // Create allocations if provided
            if (!empty($data['allocations'])) {
                $this->createAllocations($payment, $data['allocations']);
            }

            return $payment;
        });
    }

    public function updatePayment(Payment $payment, array $data): Payment
    {
        return DB::transaction(function () use ($payment, $data) {
            $payment->update([
                'customer_id' => $data['customer_id'],
                'date' => $data['date'],
                'amount' => $data['amount'],
                'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? null,
                'bank' => $data['bank'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => $data['status'],
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'updated_by' => auth()->id(),
            ]);

            // Recalculate remaining amount
            $this->recalculateRemainingAmount($payment);

            return $payment;
        });
    }

    public function deletePayment(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            // Remove all allocations (this will update document balances)
            $payment->allocations()->delete();
            
            // Delete the payment
            $payment->delete();
        });
    }

    public function allocatePayment(Payment $payment, array $allocations): void
    {
        DB::transaction(function () use ($payment, $allocations) {
            // Validate total allocation amount
            $totalAllocations = collect($allocations)->sum('amount');
            
            if ($totalAllocations > $payment->remaining_amount) {
                throw new \Exception('La suma de asignaciones excede el monto disponible del pago.');
            }

            // Create allocations
            $this->createAllocations($payment, $allocations);
            
            // Update remaining amount
            $this->recalculateRemainingAmount($payment);
        });
    }

    public function removeAllocation(PaymentAllocation $allocation): void
    {
        DB::transaction(function () use ($allocation) {
            $payment = $allocation->payment;
            
            // Delete the allocation
            $allocation->delete();
            
            // Recalculate remaining amount
            $this->recalculateRemainingAmount($payment);
        });
    }

    public function voidPayment(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment) {
            // Remove all allocations
            $payment->allocations()->delete();
            
            // Update payment status
            $payment->update([
                'status' => 'voided',
                'voided_at' => now(),
                'voided_by' => auth()->id(),
                'remaining_amount' => 0,
            ]);

            return $payment;
        });
    }

    public function getUnpaidDocuments(Customer $customer): Collection
    {
        return TaxDocument::where('tenant_id', $customer->tenant_id)
            ->where('customer_id', $customer->id)
            ->whereIn('type', ['factura_electronica', 'factura_exenta_electronica', 'nota_debito_electronica'])
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->where('balance', '>', 0)
            ->orderBy('date')
            ->get(['id', 'number', 'type', 'date', 'due_date', 'total', 'balance']);
    }

    public function getAvailableDocumentsForPayment(Payment $payment): Collection
    {
        return $this->getUnpaidDocuments($payment->customer);
    }

    public function bulkActions(array $paymentIds, string $action, int $tenantId): array
    {
        $processed = 0;
        $errors = 0;

        $payments = Payment::where('tenant_id', $tenantId)
            ->whereIn('id', $paymentIds)
            ->get();

        foreach ($payments as $payment) {
            try {
                switch ($action) {
                    case 'confirm':
                        if ($payment->status === 'pending') {
                            $payment->update(['status' => 'confirmed']);
                            $processed++;
                        }
                        break;
                        
                    case 'cancel':
                        if (in_array($payment->status, ['pending', 'confirmed'])) {
                            $payment->update(['status' => 'cancelled']);
                            $processed++;
                        }
                        break;
                        
                    case 'void':
                        if ($payment->status !== 'voided') {
                            $this->voidPayment($payment);
                            $processed++;
                        }
                        break;
                }
            } catch (\Exception $e) {
                $errors++;
            }
        }

        return [
            'processed' => $processed,
            'errors' => $errors,
        ];
    }

    public function exportPayments(array $filters, string $format)
    {
        $tenantId = auth()->user()->tenant_id;
        
        $query = Payment::where('tenant_id', $tenantId)
            ->with(['customer', 'allocations.taxDocument']);

        // Apply filters
        if (!empty($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $payments = $query->orderBy('date')->get();

        switch ($format) {
            case 'excel':
                return Excel::download(
                    new \App\Exports\PaymentsExport($payments),
                    'pagos-' . now()->format('Y-m-d') . '.xlsx'
                );
                
            case 'csv':
                return Excel::download(
                    new \App\Exports\PaymentsExport($payments),
                    'pagos-' . now()->format('Y-m-d') . '.csv'
                );
                
            case 'pdf':
                $pdf = Pdf::loadView('invoicing.payments.export-pdf', [
                    'payments' => $payments,
                    'company' => auth()->user()->tenant,
                    'filters' => $filters,
                ]);
                
                return $pdf->download('pagos-' . now()->format('Y-m-d') . '.pdf');
                
            default:
                throw new \Exception('Formato de exportación no válido.');
        }
    }

    public function getReconciliationPreview(int $bankAccountId, string $dateFrom, string $dateTo): array
    {
        $tenantId = auth()->user()->tenant_id;
        
        // Get bank account
        $bankAccount = BankAccount::where('tenant_id', $tenantId)
            ->findOrFail($bankAccountId);

        // Get unreconciled payments
        $payments = Payment::where('tenant_id', $tenantId)
            ->where('status', 'confirmed')
            ->whereNull('bank_transaction_id')
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->whereIn('payment_method', ['bank_transfer', 'debit_card', 'check'])
            ->with('customer')
            ->get();

        // Get unmatched bank transactions
        $transactions = BankTransaction::where('bank_account_id', $bankAccountId)
            ->where('type', 'deposit')
            ->whereNull('matched_at')
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->get();

        // Find potential matches
        $potentialMatches = [];
        
        foreach ($payments as $payment) {
            $matches = $transactions->filter(function ($transaction) use ($payment) {
                return abs($transaction->amount - $payment->amount) < 0.01 &&
                       abs(Carbon::parse($transaction->date)->diffInDays($payment->date)) <= 3;
            });

            if ($matches->isNotEmpty()) {
                $potentialMatches[] = [
                    'payment' => $payment,
                    'transactions' => $matches->values(),
                ];
            }
        }

        return [
            'bank_account' => $bankAccount,
            'unreconciled_payments' => $payments->count(),
            'unmatched_transactions' => $transactions->count(),
            'potential_matches' => $potentialMatches,
            'summary' => [
                'total_payment_amount' => $payments->sum('amount'),
                'total_transaction_amount' => $transactions->sum('amount'),
                'potential_matches_count' => count($potentialMatches),
            ],
        ];
    }

    protected function calculatePaymentStatistics(int $tenantId, array $filters = []): array
    {
        $baseQuery = Payment::where('tenant_id', $tenantId);
        
        // Apply same filters for consistency
        if (!empty($filters['date_from'])) {
            $baseQuery->whereDate('date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $baseQuery->whereDate('date', '<=', $filters['date_to']);
        }

        return [
            'total_amount' => (clone $baseQuery)->where('status', 'confirmed')->sum('amount'),
            'pending_amount' => (clone $baseQuery)->where('status', 'pending')->sum('amount'),
            'unallocated_amount' => (clone $baseQuery)->where('status', 'confirmed')
                ->where('remaining_amount', '>', 0)->sum('remaining_amount'),
            'this_month_amount' => Payment::where('tenant_id', $tenantId)
                ->where('status', 'confirmed')
                ->whereMonth('date', now()->month)
                ->whereYear('date', now()->year)
                ->sum('amount'),
            'count_by_status' => Payment::where('tenant_id', $tenantId)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(),
        ];
    }

    protected function generatePaymentNumber(int $tenantId): string
    {
        $lastPayment = Payment::where('tenant_id', $tenantId)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastPayment ? (intval(substr($lastPayment->number, -6)) + 1) : 1;
        
        return 'PAY-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    protected function createAllocations(Payment $payment, array $allocations): void
    {
        foreach ($allocations as $allocation) {
            // Verify document belongs to same customer and tenant
            $document = TaxDocument::where('tenant_id', $payment->tenant_id)
                ->where('customer_id', $payment->customer_id)
                ->where('balance', '>', 0)
                ->findOrFail($allocation['tax_document_id']);

            // Check if allocation already exists
            $existingAllocation = PaymentAllocation::where('payment_id', $payment->id)
                ->where('tax_document_id', $allocation['tax_document_id'])
                ->first();

            if ($existingAllocation) {
                continue; // Skip if already exists
            }

            // Validate allocation amount doesn't exceed document balance
            if ($allocation['amount'] > $document->balance) {
                throw new \Exception("El monto de asignación excede el saldo del documento {$document->number}.");
            }

            PaymentAllocation::create([
                'payment_id' => $payment->id,
                'tax_document_id' => $allocation['tax_document_id'],
                'amount' => $allocation['amount'],
                'notes' => $allocation['notes'] ?? null,
            ]);
        }
    }

    protected function recalculateRemainingAmount(Payment $payment): void
    {
        $allocatedAmount = $payment->allocations()->sum('amount');
        $payment->update([
            'remaining_amount' => $payment->amount - $allocatedAmount,
        ]);
    }
}