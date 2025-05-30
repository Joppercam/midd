<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\JournalEntryLine;
use App\Models\TaxDocument;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class AccountingService
{
    /**
     * Crear plan de cuentas básico
     */
    public function createBasicChartOfAccounts(int $tenantId): void
    {
        $accounts = [
            // ACTIVOS
            ['code' => '1', 'name' => 'ACTIVOS', 'type' => 'asset', 'normal_balance' => 'debit', 'is_parent' => true, 'accepts_entries' => false],
            ['code' => '1.1', 'name' => 'ACTIVOS CIRCULANTES', 'type' => 'asset', 'subtype' => 'current_asset', 'normal_balance' => 'debit', 'parent_code' => '1', 'is_parent' => true, 'accepts_entries' => false],
            ['code' => '1.1.01', 'name' => 'Caja', 'type' => 'asset', 'subtype' => 'current_asset', 'normal_balance' => 'debit', 'parent_code' => '1.1'],
            ['code' => '1.1.02', 'name' => 'Banco', 'type' => 'asset', 'subtype' => 'current_asset', 'normal_balance' => 'debit', 'parent_code' => '1.1'],
            ['code' => '1.1.03', 'name' => 'Clientes', 'type' => 'asset', 'subtype' => 'current_asset', 'normal_balance' => 'debit', 'parent_code' => '1.1'],
            ['code' => '1.1.04', 'name' => 'Inventario', 'type' => 'asset', 'subtype' => 'current_asset', 'normal_balance' => 'debit', 'parent_code' => '1.1'],
            ['code' => '1.1.05', 'name' => 'IVA Crédito Fiscal', 'type' => 'asset', 'subtype' => 'current_asset', 'normal_balance' => 'debit', 'parent_code' => '1.1'],

            // PASIVOS
            ['code' => '2', 'name' => 'PASIVOS', 'type' => 'liability', 'normal_balance' => 'credit', 'is_parent' => true, 'accepts_entries' => false],
            ['code' => '2.1', 'name' => 'PASIVOS CIRCULANTES', 'type' => 'liability', 'subtype' => 'current_liability', 'normal_balance' => 'credit', 'parent_code' => '2', 'is_parent' => true, 'accepts_entries' => false],
            ['code' => '2.1.01', 'name' => 'Proveedores', 'type' => 'liability', 'subtype' => 'current_liability', 'normal_balance' => 'credit', 'parent_code' => '2.1'],
            ['code' => '2.1.02', 'name' => 'IVA Débito Fiscal', 'type' => 'liability', 'subtype' => 'current_liability', 'normal_balance' => 'credit', 'parent_code' => '2.1'],
            ['code' => '2.1.03', 'name' => 'Impuestos por Pagar', 'type' => 'liability', 'subtype' => 'current_liability', 'normal_balance' => 'credit', 'parent_code' => '2.1'],

            // PATRIMONIO
            ['code' => '3', 'name' => 'PATRIMONIO', 'type' => 'equity', 'normal_balance' => 'credit', 'is_parent' => true, 'accepts_entries' => false],
            ['code' => '3.1', 'name' => 'Capital', 'type' => 'equity', 'subtype' => 'capital', 'normal_balance' => 'credit', 'parent_code' => '3'],
            ['code' => '3.2', 'name' => 'Utilidades Retenidas', 'type' => 'equity', 'subtype' => 'retained_earnings', 'normal_balance' => 'credit', 'parent_code' => '3'],

            // INGRESOS
            ['code' => '4', 'name' => 'INGRESOS', 'type' => 'revenue', 'normal_balance' => 'credit', 'is_parent' => true, 'accepts_entries' => false],
            ['code' => '4.1', 'name' => 'Ingresos Operacionales', 'type' => 'revenue', 'subtype' => 'operating_revenue', 'normal_balance' => 'credit', 'parent_code' => '4'],
            ['code' => '4.2', 'name' => 'Ingresos No Operacionales', 'type' => 'revenue', 'subtype' => 'non_operating_revenue', 'normal_balance' => 'credit', 'parent_code' => '4'],

            // GASTOS
            ['code' => '5', 'name' => 'GASTOS', 'type' => 'expense', 'normal_balance' => 'debit', 'is_parent' => true, 'accepts_entries' => false],
            ['code' => '5.1', 'name' => 'Gastos Operacionales', 'type' => 'expense', 'subtype' => 'operating_expense', 'normal_balance' => 'debit', 'parent_code' => '5', 'is_parent' => true, 'accepts_entries' => false],
            ['code' => '5.1.01', 'name' => 'Costo de Ventas', 'type' => 'expense', 'subtype' => 'operating_expense', 'normal_balance' => 'debit', 'parent_code' => '5.1'],
            ['code' => '5.1.02', 'name' => 'Sueldos y Salarios', 'type' => 'expense', 'subtype' => 'operating_expense', 'normal_balance' => 'debit', 'parent_code' => '5.1'],
            ['code' => '5.1.03', 'name' => 'Arriendo', 'type' => 'expense', 'subtype' => 'operating_expense', 'normal_balance' => 'debit', 'parent_code' => '5.1'],
        ];

        DB::beginTransaction();
        try {
            foreach ($accounts as $accountData) {
                $parentId = null;
                if (isset($accountData['parent_code'])) {
                    $parent = ChartOfAccount::where('tenant_id', $tenantId)
                        ->where('code', $accountData['parent_code'])
                        ->first();
                    $parentId = $parent?->id;
                }

                ChartOfAccount::create([
                    'tenant_id' => $tenantId,
                    'code' => $accountData['code'],
                    'name' => $accountData['name'],
                    'type' => $accountData['type'],
                    'subtype' => $accountData['subtype'] ?? null,
                    'parent_id' => $parentId,
                    'level' => substr_count($accountData['code'], '.') + 1,
                    'is_parent' => $accountData['is_parent'] ?? false,
                    'accepts_entries' => $accountData['accepts_entries'] ?? true,
                    'normal_balance' => $accountData['normal_balance'],
                    'opening_balance' => 0,
                    'current_balance' => 0,
                    'is_active' => true,
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Crear asiento desde factura
     */
    public function createJournalEntryFromInvoice(TaxDocument $invoice): JournalEntry
    {
        if ($invoice->document_type !== 'factura_electronica') {
            throw new \Exception('Solo se pueden contabilizar facturas electrónicas');
        }

        $accounts = $this->getDefaultAccounts($invoice->tenant_id);
        
        $entry = JournalEntry::create([
            'tenant_id' => $invoice->tenant_id,
            'entry_number' => $this->generateEntryNumber($invoice->tenant_id),
            'entry_date' => $invoice->issue_date,
            'reference' => $invoice->document_number,
            'description' => "Factura {$invoice->document_number} - {$invoice->customer->name}",
            'type' => 'automatic',
            'source' => 'invoice',
            'source_id' => $invoice->id,
            'created_by' => auth()->id(),
            'status' => 'draft',
            'total_debit' => 0,
            'total_credit' => 0,
        ]);

        // Débito: Clientes
        $entry->lines()->create([
            'account_id' => $accounts['clientes'],
            'description' => "Factura {$invoice->document_number}",
            'debit_amount' => $invoice->total,
            'credit_amount' => 0,
        ]);

        // Crédito: IVA Débito Fiscal
        if ($invoice->tax_amount > 0) {
            $entry->lines()->create([
                'account_id' => $accounts['iva_debito'],
                'description' => "IVA Factura {$invoice->document_number}",
                'debit_amount' => 0,
                'credit_amount' => $invoice->tax_amount,
            ]);
        }

        // Crédito: Ingresos
        $entry->lines()->create([
            'account_id' => $accounts['ingresos'],
            'description' => "Venta Factura {$invoice->document_number}",
            'debit_amount' => 0,
            'credit_amount' => $invoice->subtotal,
        ]);

        $entry->calculateTotals();
        $entry->post();

        return $entry;
    }

    /**
     * Crear asiento desde pago
     */
    public function createJournalEntryFromPayment(Payment $payment): JournalEntry
    {
        $accounts = $this->getDefaultAccounts($payment->tenant_id);
        
        $entry = JournalEntry::create([
            'tenant_id' => $payment->tenant_id,
            'entry_number' => $this->generateEntryNumber($payment->tenant_id),
            'entry_date' => $payment->payment_date,
            'reference' => $payment->reference,
            'description' => "Pago {$payment->payment_method} - {$payment->customer->name}",
            'type' => 'automatic',
            'source' => 'payment',
            'source_id' => $payment->id,
            'created_by' => auth()->id(),
            'status' => 'draft',
            'total_debit' => 0,
            'total_credit' => 0,
        ]);

        // Débito: Banco o Caja según método de pago
        $accountId = $payment->payment_method === 'cash' ? $accounts['caja'] : $accounts['banco'];
        $entry->lines()->create([
            'account_id' => $accountId,
            'description' => "Pago recibido - {$payment->payment_method}",
            'debit_amount' => $payment->amount,
            'credit_amount' => 0,
        ]);

        // Crédito: Clientes
        $entry->lines()->create([
            'account_id' => $accounts['clientes'],
            'description' => "Pago de cliente",
            'debit_amount' => 0,
            'credit_amount' => $payment->amount,
        ]);

        $entry->calculateTotals();
        $entry->post();

        return $entry;
    }

    /**
     * Generar balance de comprobación
     */
    public function generateTrialBalance(\DateTime $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?: now();
        
        $accounts = ChartOfAccount::where('tenant_id', auth()->user()->tenant_id)
            ->where('accepts_entries', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $trialBalance = [];
        $totalDebits = 0;
        $totalCredits = 0;

        foreach ($accounts as $account) {
            $balance = $account->getBalanceForPeriod(
                new \DateTime('1900-01-01'),
                $asOfDate
            );

            if (abs($balance['closing_balance']) > 0.01) { // Solo incluir cuentas con saldo
                $debitBalance = 0;
                $creditBalance = 0;

                if ($account->is_debit_normal) {
                    if ($balance['closing_balance'] > 0) {
                        $debitBalance = $balance['closing_balance'];
                    } else {
                        $creditBalance = abs($balance['closing_balance']);
                    }
                } else {
                    if ($balance['closing_balance'] > 0) {
                        $creditBalance = $balance['closing_balance'];
                    } else {
                        $debitBalance = abs($balance['closing_balance']);
                    }
                }

                $trialBalance[] = [
                    'account_code' => $account->code,
                    'account_name' => $account->name,
                    'account_type' => $account->type,
                    'debit_balance' => $debitBalance,
                    'credit_balance' => $creditBalance,
                ];

                $totalDebits += $debitBalance;
                $totalCredits += $creditBalance;
            }
        }

        return [
            'as_of_date' => $asOfDate->format('Y-m-d'),
            'accounts' => $trialBalance,
            'total_debits' => $totalDebits,
            'total_credits' => $totalCredits,
            'difference' => abs($totalDebits - $totalCredits),
            'is_balanced' => abs($totalDebits - $totalCredits) < 0.01,
        ];
    }

    /**
     * Generar estado de resultados
     */
    public function generateIncomeStatement(\DateTime $startDate, \DateTime $endDate): array
    {
        $revenueAccounts = ChartOfAccount::where('tenant_id', auth()->user()->tenant_id)
            ->where('type', 'revenue')
            ->where('accepts_entries', true)
            ->get();

        $expenseAccounts = ChartOfAccount::where('tenant_id', auth()->user()->tenant_id)
            ->where('type', 'expense')
            ->where('accepts_entries', true)
            ->get();

        $totalRevenue = 0;
        $totalExpenses = 0;
        $revenues = [];
        $expenses = [];

        foreach ($revenueAccounts as $account) {
            $balance = $account->getBalanceForPeriod($startDate, $endDate);
            if (abs($balance['closing_balance']) > 0.01) {
                $revenues[] = [
                    'account_code' => $account->code,
                    'account_name' => $account->name,
                    'amount' => $balance['closing_balance'],
                ];
                $totalRevenue += $balance['closing_balance'];
            }
        }

        foreach ($expenseAccounts as $account) {
            $balance = $account->getBalanceForPeriod($startDate, $endDate);
            if (abs($balance['closing_balance']) > 0.01) {
                $expenses[] = [
                    'account_code' => $account->code,
                    'account_name' => $account->name,
                    'amount' => $balance['closing_balance'],
                ];
                $totalExpenses += $balance['closing_balance'];
            }
        }

        return [
            'period_start' => $startDate->format('Y-m-d'),
            'period_end' => $endDate->format('Y-m-d'),
            'revenues' => $revenues,
            'expenses' => $expenses,
            'total_revenue' => $totalRevenue,
            'total_expenses' => $totalExpenses,
            'net_income' => $totalRevenue - $totalExpenses,
        ];
    }

    /**
     * Obtener cuentas por defecto
     */
    private function getDefaultAccounts(int $tenantId): array
    {
        return [
            'caja' => ChartOfAccount::where('tenant_id', $tenantId)->where('code', '1.1.01')->first()?->id,
            'banco' => ChartOfAccount::where('tenant_id', $tenantId)->where('code', '1.1.02')->first()?->id,
            'clientes' => ChartOfAccount::where('tenant_id', $tenantId)->where('code', '1.1.03')->first()?->id,
            'inventario' => ChartOfAccount::where('tenant_id', $tenantId)->where('code', '1.1.04')->first()?->id,
            'iva_credito' => ChartOfAccount::where('tenant_id', $tenantId)->where('code', '1.1.05')->first()?->id,
            'proveedores' => ChartOfAccount::where('tenant_id', $tenantId)->where('code', '2.1.01')->first()?->id,
            'iva_debito' => ChartOfAccount::where('tenant_id', $tenantId)->where('code', '2.1.02')->first()?->id,
            'ingresos' => ChartOfAccount::where('tenant_id', $tenantId)->where('code', '4.1')->first()?->id,
            'gastos' => ChartOfAccount::where('tenant_id', $tenantId)->where('code', '5.1.01')->first()?->id,
        ];
    }

    /**
     * Generar número de asiento
     */
    private function generateEntryNumber(int $tenantId): string
    {
        $prefix = 'JE';
        $year = now()->year;
        $month = now()->month;
        
        $lastEntry = JournalEntry::where('tenant_id', $tenantId)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('entry_number', 'desc')
            ->first();
        
        if ($lastEntry && preg_match('/' . $prefix . '-' . $year . $month . '-(\d+)/', $lastEntry->entry_number, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }
        
        return $prefix . '-' . $year . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }
}