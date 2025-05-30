<?php

namespace App\Modules\Banking\Controllers;

use App\Http\Controllers\Controller;
use App\Models\BankReconciliation;
use App\Models\BankTransaction;
use App\Models\BankAccount;
use App\Exports\BankReconciliationExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class BankReportController extends Controller
{
    use \App\Traits\ChecksPermissions;

    public function __construct()
    {
        $this->middleware(['auth', 'verified', 'check.subscription', 'check.module:banking']);
    }

    public function index()
    {
        $this->checkPermission('bank_reconciliations.view');

        $accounts = BankAccount::where('tenant_id', auth()->user()->tenant_id)
            ->withCount(['transactions', 'reconciliations'])
            ->get();

        $stats = [
            'total_accounts' => $accounts->count(),
            'total_balance' => $accounts->sum('current_balance'),
            'pending_reconciliations' => BankReconciliation::where('tenant_id', auth()->user()->tenant_id)
                ->where('status', 'pending')
                ->count(),
            'unmatched_transactions' => BankTransaction::where('tenant_id', auth()->user()->tenant_id)
                ->whereNull('matched_at')
                ->count(),
        ];

        return Inertia::render('Banking/Reports/Index', [
            'accounts' => $accounts,
            'stats' => $stats,
        ]);
    }

    public function reconciliationReport(Request $request, BankReconciliation $reconciliation)
    {
        $this->checkPermission('bank_reconciliations.view');
        $this->authorize('view', $reconciliation);

        $reconciliation->load([
            'bankAccount',
            'matches.bankTransaction',
            'matches.matchable',
            'user'
        ]);

        $summary = [
            'total_transactions' => $reconciliation->matches->count(),
            'matched_amount' => $reconciliation->matches->sum('amount'),
            'bank_balance' => $reconciliation->bank_ending_balance,
            'system_balance' => $reconciliation->system_ending_balance,
            'difference' => $reconciliation->difference,
        ];

        if ($request->wantsJson()) {
            return response()->json([
                'reconciliation' => $reconciliation,
                'summary' => $summary,
            ]);
        }

        return Inertia::render('Banking/Reports/ReconciliationDetail', [
            'reconciliation' => $reconciliation,
            'summary' => $summary,
        ]);
    }

    public function monthlyReport(Request $request, BankAccount $account)
    {
        $this->checkPermission('bank_reconciliations.view');
        $this->authorize('view', $account);

        $month = $request->input('month', now()->format('Y-m'));
        $startDate = Carbon::parse($month)->startOfMonth();
        $endDate = Carbon::parse($month)->endOfMonth();

        $transactions = BankTransaction::where('bank_account_id', $account->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get();

        $reconciliations = BankReconciliation::where('bank_account_id', $account->id)
            ->whereBetween('period_end', [$startDate, $endDate])
            ->with('user')
            ->get();

        $summary = [
            'opening_balance' => $transactions->first()?->running_balance ?? $account->current_balance,
            'closing_balance' => $transactions->last()?->running_balance ?? $account->current_balance,
            'total_deposits' => $transactions->where('type', 'deposit')->sum('amount'),
            'total_withdrawals' => $transactions->where('type', 'withdrawal')->sum('amount'),
            'transaction_count' => $transactions->count(),
            'reconciliation_count' => $reconciliations->count(),
        ];

        return Inertia::render('Banking/Reports/Monthly', [
            'account' => $account,
            'month' => $month,
            'transactions' => $transactions,
            'reconciliations' => $reconciliations,
            'summary' => $summary,
        ]);
    }

    public function exportReconciliation(Request $request, BankReconciliation $reconciliation)
    {
        $this->checkPermission('bank_reconciliations.export');
        $this->authorize('view', $reconciliation);

        $format = $request->input('format', 'excel');

        if ($format === 'pdf') {
            return $this->exportReconciliationPdf($reconciliation);
        }

        return Excel::download(
            new BankReconciliationExport($reconciliation),
            "reconciliation-{$reconciliation->id}-{$reconciliation->period_end->format('Y-m-d')}.xlsx"
        );
    }

    public function exportMonthly(Request $request, BankAccount $account)
    {
        $this->checkPermission('bank_reconciliations.export');
        $this->authorize('view', $account);

        $month = $request->input('month', now()->format('Y-m'));
        $format = $request->input('format', 'pdf');

        if ($format === 'pdf') {
            return $this->exportMonthlyPdf($account, $month);
        }

        // Excel export would be implemented here
        return response()->json(['message' => 'Excel export not yet implemented'], 501);
    }

    public function cashFlowAnalysis(Request $request)
    {
        $this->checkPermission('bank_reconciliations.view');

        $startDate = Carbon::parse($request->input('start_date', now()->subMonths(6)->startOfMonth()));
        $endDate = Carbon::parse($request->input('end_date', now()->endOfMonth()));

        $accounts = BankAccount::where('tenant_id', auth()->user()->tenant_id)
            ->when($request->input('account_id'), function ($query, $accountId) {
                $query->where('id', $accountId);
            })
            ->get();

        $cashFlowData = [];

        foreach ($accounts as $account) {
            $monthlyData = [];
            $currentDate = $startDate->copy();

            while ($currentDate <= $endDate) {
                $monthStart = $currentDate->copy()->startOfMonth();
                $monthEnd = $currentDate->copy()->endOfMonth();

                $deposits = BankTransaction::where('bank_account_id', $account->id)
                    ->where('type', 'deposit')
                    ->whereBetween('date', [$monthStart, $monthEnd])
                    ->sum('amount');

                $withdrawals = BankTransaction::where('bank_account_id', $account->id)
                    ->where('type', 'withdrawal')
                    ->whereBetween('date', [$monthStart, $monthEnd])
                    ->sum('amount');

                $monthlyData[] = [
                    'month' => $currentDate->format('Y-m'),
                    'deposits' => $deposits,
                    'withdrawals' => $withdrawals,
                    'net' => $deposits - $withdrawals,
                ];

                $currentDate->addMonth();
            }

            $cashFlowData[] = [
                'account' => $account,
                'data' => $monthlyData,
            ];
        }

        return Inertia::render('Banking/Reports/CashFlow', [
            'cashFlowData' => $cashFlowData,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
            'accounts' => $accounts,
        ]);
    }

    private function exportReconciliationPdf(BankReconciliation $reconciliation)
    {
        $reconciliation->load([
            'bankAccount',
            'matches.bankTransaction',
            'matches.matchable',
            'user'
        ]);

        $pdf = Pdf::loadView('reports.bank-reconciliation-pdf', [
            'reconciliation' => $reconciliation,
            'company' => auth()->user()->tenant,
        ]);

        return $pdf->download("reconciliation-{$reconciliation->id}.pdf");
    }

    private function exportMonthlyPdf(BankAccount $account, string $month)
    {
        $startDate = Carbon::parse($month)->startOfMonth();
        $endDate = Carbon::parse($month)->endOfMonth();

        $transactions = BankTransaction::where('bank_account_id', $account->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get();

        $pdf = Pdf::loadView('reports.bank-monthly-pdf', [
            'account' => $account,
            'transactions' => $transactions,
            'month' => $month,
            'company' => auth()->user()->tenant,
        ]);

        return $pdf->download("bank-statement-{$account->account_number}-{$month}.pdf");
    }
}