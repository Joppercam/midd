<?php

namespace App\Modules\HRM\Services;

use App\Modules\HRM\Models\Employee;
use App\Modules\HRM\Models\Payroll;
use App\Modules\HRM\Models\PayrollDetail;
use App\Modules\HRM\Models\Department;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use ZipArchive;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class PayrollService
{
    private const UF_VALUE = 36000; // Valor UF aproximado
    private const UTM_VALUE = 65000; // Valor UTM aproximado
    
    // Tabla de impuesto único (valores en UTM)
    private const TAX_BRACKETS = [
        ['from' => 0, 'to' => 13.5, 'rate' => 0, 'deduction' => 0],
        ['from' => 13.5, 'to' => 30, 'rate' => 0.04, 'deduction' => 0.54],
        ['from' => 30, 'to' => 50, 'rate' => 0.08, 'deduction' => 1.74],
        ['from' => 50, 'to' => 70, 'rate' => 0.135, 'deduction' => 4.49],
        ['from' => 70, 'to' => 90, 'rate' => 0.23, 'deduction' => 11.14],
        ['from' => 90, 'to' => 120, 'rate' => 0.304, 'deduction' => 17.8],
        ['from' => 120, 'to' => 310, 'rate' => 0.35, 'deduction' => 23.32],
        ['from' => 310, 'to' => PHP_FLOAT_MAX, 'rate' => 0.4, 'deduction' => 38.82],
    ];

    /**
     * Get current tenant ID
     */
    private function getTenantId(): int
    {
        return Auth::user()->tenant_id;
    }

    /**
     * Get paginated list of payrolls with filters
     */
    public function getPayrollsList(array $filters = []): LengthAwarePaginator
    {
        $query = Payroll::where('tenant_id', $this->getTenantId())
            ->with(['approvedBy']);

        if (!empty($filters['year'])) {
            $query->where('year', $filters['year']);
        }

        if (!empty($filters['month'])) {
            $query->where('month', $filters['month']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['employee_id'])) {
            $query->whereHas('items', function ($q) use ($filters) {
                $q->where('employee_id', $filters['employee_id']);
            });
        }

        if (!empty($filters['department_id'])) {
            $query->whereHas('items.employee', function ($q) use ($filters) {
                $q->where('department_id', $filters['department_id']);
            });
        }

        return $query->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->paginate(10);
    }

    /**
     * Get payroll statistics for dashboard
     */
    public function getPayrollStatistics(array $filters = []): array
    {
        $query = Payroll::where('tenant_id', $this->getTenantId());

        if (!empty($filters['year'])) {
            $query->where('year', $filters['year']);
        }

        if (!empty($filters['month'])) {
            $query->where('month', $filters['month']);
        }

        $totals = $query->selectRaw('
            COUNT(*) as total_payrolls,
            SUM(CASE WHEN status = "paid" THEN total_earnings ELSE 0 END) as total_paid,
            SUM(CASE WHEN status = "paid" THEN total_deductions ELSE 0 END) as total_deductions,
            SUM(CASE WHEN status = "paid" THEN net_pay ELSE 0 END) as total_net,
            AVG(net_pay) as average_salary
        ')->first();

        return [
            'total_payrolls' => $totals->total_payrolls ?? 0,
            'total_paid' => $totals->total_paid ?? 0,
            'total_deductions' => $totals->total_deductions ?? 0,
            'total_net' => $totals->total_net ?? 0,
            'average_salary' => $totals->average_salary ?? 0,
        ];
    }

    /**
     * Get active employees for payroll
     */
    public function getActiveEmployees(): Collection
    {
        return Employee::where('tenant_id', $this->getTenantId())
            ->where('status', 'active')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'employee_code']);
    }

    /**
     * Get all departments
     */
    public function getDepartments(): Collection
    {
        return Department::where('tenant_id', $this->getTenantId())
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Get available years for payroll
     */
    public function getAvailableYears(): array
    {
        $years = Payroll::where('tenant_id', $this->getTenantId())
            ->distinct()
            ->pluck('year')
            ->sort()
            ->reverse()
            ->values()
            ->toArray();

        // Add current year if not present
        $currentYear = now()->year;
        if (!in_array($currentYear, $years)) {
            array_unshift($years, $currentYear);
        }

        return $years;
    }

    /**
     * Check if payroll exists for given period
     */
    public function checkExistingPayroll(int $year, int $month): ?Payroll
    {
        return Payroll::where('tenant_id', $this->getTenantId())
            ->where('year', $year)
            ->where('month', $month)
            ->first();
    }

    /**
     * Get eligible employees for new payroll
     */
    public function getEligibleEmployees(int $year, int $month): Collection
    {
        $periodStart = Carbon::create($year, $month, 1);
        $periodEnd = $periodStart->copy()->endOfMonth();

        return Employee::where('tenant_id', $this->getTenantId())
            ->where('status', 'active')
            ->whereHas('contracts', function ($query) use ($periodEnd) {
                $query->where('start_date', '<=', $periodEnd)
                    ->where(function ($q) use ($periodEnd) {
                        $q->whereNull('end_date')
                            ->orWhere('end_date', '>=', $periodEnd);
                    });
            })
            ->with(['department', 'contracts' => function ($query) {
                $query->where('is_active', true);
            }])
            ->get();
    }

    /**
     * Create new payroll
     */
    public function createPayroll(array $data): Payroll
    {
        $periodStart = Carbon::create($data['year'], $data['month'], 1);
        $periodEnd = $periodStart->copy()->endOfMonth();
        
        // Generate payroll number
        $payrollNumber = $this->generatePayrollNumber($data['year'], $data['month']);
        
        // Create payroll
        $payroll = Payroll::create([
            'tenant_id' => $this->getTenantId(),
            'payroll_number' => $payrollNumber,
            'month' => $data['month'],
            'year' => $data['year'],
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'payment_date' => $data['payment_date'] ?? $periodEnd->copy()->addDays(5),
            'status' => 'draft',
            'total_earnings' => 0,
            'total_deductions' => 0,
            'net_pay' => 0,
        ]);

        // Add payroll items for selected employees
        if (!empty($data['employees'])) {
            foreach ($data['employees'] as $employeeData) {
                $this->addPayrollItem($payroll, $employeeData);
            }
        }

        // Calculate totals
        $this->recalculatePayrollTotals($payroll);

        return $payroll;
    }

    /**
     * Update existing payroll
     */
    public function updatePayroll(Payroll $payroll, array $data): Payroll
    {
        // Update basic information
        $payroll->update([
            'payment_date' => $data['payment_date'] ?? $payroll->payment_date,
        ]);

        // Update items if provided
        if (!empty($data['items'])) {
            foreach ($data['items'] as $itemData) {
                $this->updatePayrollItem($payroll, $itemData);
            }
        }

        // Recalculate totals
        $this->recalculatePayrollTotals($payroll);

        return $payroll;
    }

    /**
     * Calculate payroll (change status from draft to calculated)
     */
    public function calculatePayroll(Payroll $payroll): Payroll
    {
        // Recalculate all items
        foreach ($payroll->items as $item) {
            $this->calculatePayrollItem($item);
        }

        // Update totals and status
        $this->recalculatePayrollTotals($payroll);
        $payroll->update(['status' => 'calculated']);

        return $payroll;
    }

    /**
     * Approve payroll
     */
    public function approvePayroll(Payroll $payroll, ?string $notes = null): array
    {
        try {
            $payroll->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'approval_notes' => $notes,
            ]);

            return ['success' => true, 'message' => 'Nómina aprobada exitosamente'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Process payroll for payment
     */
    public function processPayroll(Payroll $payroll): array
    {
        try {
            // Mark as paid
            $payroll->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            // Update all items as paid
            $payroll->items()->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            // TODO: Integrate with payment system
            // TODO: Generate bank transfer files
            // TODO: Send payment notifications

            return ['success' => true, 'message' => 'Nómina procesada exitosamente'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get detailed payroll information
     */
    public function getPayrollDetails(Payroll $payroll): array
    {
        $payroll->load(['items.employee.department', 'approvedBy']);

        return [
            'payroll' => $payroll,
            'summary' => [
                'total_employees' => $payroll->items->count(),
                'total_earnings' => $payroll->total_earnings,
                'total_deductions' => $payroll->total_deductions,
                'net_pay' => $payroll->net_pay,
            ],
            'breakdown' => [
                'by_department' => $this->getPayrollBreakdownByDepartment($payroll),
                'by_type' => $this->getPayrollBreakdownByType($payroll),
            ],
        ];
    }

    /**
     * Add deduction to payroll
     */
    public function addDeduction(Payroll $payroll, array $data): void
    {
        $item = $payroll->items()->where('employee_id', $data['employee_id'])->first();
        
        if ($item) {
            $deductions = $item->deductions ?? [];
            $deductions[] = [
                'type' => $data['type'],
                'amount' => $data['amount'],
                'description' => $data['description'],
            ];
            
            $item->update([
                'deductions' => $deductions,
                'other_deductions' => collect($deductions)->sum('amount'),
            ]);
            
            $this->calculatePayrollItem($item);
            $this->recalculatePayrollTotals($payroll);
        }
    }

    /**
     * Add bonus to payroll
     */
    public function addBonus(Payroll $payroll, array $data): void
    {
        $item = $payroll->items()->where('employee_id', $data['employee_id'])->first();
        
        if ($item) {
            $bonuses = $item->bonuses ?? [];
            $bonuses[] = [
                'type' => $data['type'],
                'amount' => $data['amount'],
                'description' => $data['description'],
                'is_taxable' => $data['is_taxable'] ?? true,
            ];
            
            $item->update([
                'bonuses' => $bonuses,
                'bonuses_amount' => collect($bonuses)->sum('amount'),
            ]);
            
            $this->calculatePayrollItem($item);
            $this->recalculatePayrollTotals($payroll);
        }
    }

    /**
     * Get payslip data for an employee
     */
    public function getPayslipData(Payroll $payroll, Employee $employee): ?array
    {
        $item = $payroll->items()->where('employee_id', $employee->id)->first();
        
        if (!$item) {
            return null;
        }

        return [
            'payroll' => $payroll,
            'employee' => $employee,
            'item' => $item,
            'earnings' => $this->getItemEarningsBreakdown($item),
            'deductions' => $this->getItemDeductionsBreakdown($item),
        ];
    }

    /**
     * Generate bulk payslips as ZIP
     * 
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function generateBulkPayslips(Payroll $payroll)
    {
        $tempPath = storage_path('app/temp/payslips_' . $payroll->id);
        
        // Create temp directory
        if (!file_exists($tempPath)) {
            mkdir($tempPath, 0755, true);
        }

        // Generate individual PDFs
        foreach ($payroll->items as $item) {
            $employee = $item->employee;
            $payslipData = $this->getPayslipData($payroll, $employee);
            
            if ($payslipData) {
                $pdf = Pdf::loadView('hrm.payslip', [
                    'payslip' => $payslipData,
                    'company' => Auth::user()->tenant,
                ]);
                
                $filename = "liquidacion-{$employee->employee_code}-{$payroll->year}-{$payroll->month}.pdf";
                $pdf->save($tempPath . '/' . $filename);
            }
        }

        // Create ZIP file
        $zipPath = storage_path('app/temp/payslips_' . $payroll->id . '.zip');
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $files = scandir($tempPath);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $zip->addFile($tempPath . '/' . $file, $file);
            }
        }
        $zip->close();

        // Clean up temp files
        array_map('unlink', glob($tempPath . '/*.*'));
        rmdir($tempPath);

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }

    /**
     * Get yearly summary for reports
     */
    public function getYearlySummary(int $year): array
    {
        $payrolls = Payroll::where('tenant_id', $this->getTenantId())
            ->where('year', $year)
            ->where('status', 'paid')
            ->get();

        $summary = [];
        
        foreach (range(1, 12) as $month) {
            $monthPayroll = $payrolls->firstWhere('month', $month);
            
            $summary[] = [
                'month' => $month,
                'exists' => !is_null($monthPayroll),
                'total_earnings' => $monthPayroll->total_earnings ?? 0,
                'total_deductions' => $monthPayroll->total_deductions ?? 0,
                'net_pay' => $monthPayroll->net_pay ?? 0,
                'employee_count' => $monthPayroll ? $monthPayroll->items->count() : 0,
            ];
        }

        return [
            'months' => $summary,
            'totals' => [
                'total_earnings' => collect($summary)->sum('total_earnings'),
                'total_deductions' => collect($summary)->sum('total_deductions'),
                'net_pay' => collect($summary)->sum('net_pay'),
            ],
        ];
    }

    /**
     * Generate tax report
     */
    public function generateTaxReport(int $year, int $month): array
    {
        $payroll = $this->checkExistingPayroll($year, $month);
        
        if (!$payroll || $payroll->status !== 'paid') {
            return ['employees' => [], 'totals' => []];
        }

        $employees = [];
        
        foreach ($payroll->items as $item) {
            $employees[] = [
                'employee' => $item->employee,
                'taxable_income' => $item->total_earnings - ($item->afp_amount + $item->health_amount),
                'tax_amount' => $item->tax_amount,
                'rut' => $item->employee->rut,
            ];
        }

        return [
            'employees' => $employees,
            'totals' => [
                'total_taxable' => collect($employees)->sum('taxable_income'),
                'total_tax' => collect($employees)->sum('tax_amount'),
            ],
        ];
    }

    /**
     * Generate social security report
     */
    public function generateSocialSecurityReport(int $year, int $month): array
    {
        $payroll = $this->checkExistingPayroll($year, $month);
        
        if (!$payroll || $payroll->status !== 'paid') {
            return ['employees' => [], 'totals' => []];
        }

        $employees = [];
        
        foreach ($payroll->items as $item) {
            $employees[] = [
                'employee' => $item->employee,
                'base_salary' => $item->base_salary,
                'afp_amount' => $item->afp_amount,
                'health_amount' => $item->health_amount,
                'rut' => $item->employee->rut,
            ];
        }

        return [
            'employees' => $employees,
            'totals' => [
                'total_afp' => collect($employees)->sum('afp_amount'),
                'total_health' => collect($employees)->sum('health_amount'),
                'total_social_security' => collect($employees)->sum('afp_amount') + collect($employees)->sum('health_amount'),
            ],
        ];
    }

    /**
     * Export payroll data
     */
    public function exportPayroll(array $filters): Response
    {
        $payrolls = $this->getPayrollsList($filters);
        
        // TODO: Implement Excel export
        // For now, return CSV
        
        $csv = "Período,Estado,Total Ingresos,Total Deducciones,Pago Neto,Empleados\n";
        
        foreach ($payrolls as $payroll) {
            $csv .= "{$payroll->period_name},{$payroll->status},{$payroll->total_earnings},{$payroll->total_deductions},{$payroll->net_pay},{$payroll->employee_count}\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="nominas.csv"',
        ]);
    }

    /**
     * Generate bank file for payments
     */
    public function generateBankFile(Payroll $payroll): Response
    {
        $content = ""; // Bank file header
        
        foreach ($payroll->items as $item) {
            $employee = $item->employee;
            
            // Format for Chilean banks (example)
            $line = sprintf(
                "%s;%s;%s;%s;%d\n",
                $employee->rut,
                $employee->full_name,
                $employee->bank_account_number ?? '',
                $employee->bank_code ?? '',
                $item->net_pay * 100 // Amount in cents
            );
            
            $content .= $line;
        }

        return response($content, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="nomina_' . $payroll->payroll_number . '.txt"',
        ]);
    }

    /**
     * Private helper methods
     */
    
    private function generatePayrollNumber(int $year, int $month): string
    {
        $tenantId = $this->getTenantId();
        return sprintf('NOM-%d-%04d%02d', $tenantId, $year, $month);
    }

    private function addPayrollItem(Payroll $payroll, array $data): void
    {
        $employee = Employee::find($data['employee_id']);
        $contract = $employee->contracts()->where('is_active', true)->first();
        
        if (!$contract) {
            return;
        }

        // Create basic payroll item
        $payroll->items()->create([
            'employee_id' => $employee->id,
            'base_salary' => $contract->base_salary,
            'worked_days' => $data['worked_days'] ?? 30,
            'status' => 'pending',
        ]);
    }

    private function updatePayrollItem(Payroll $payroll, array $data): void
    {
        $item = $payroll->items()->find($data['id']);
        
        if ($item) {
            $item->update($data);
            $this->calculatePayrollItem($item);
        }
    }

    private function calculatePayrollItem($item): void
    {
        // Basic calculation
        $baseSalary = $item->base_salary;
        $workedDays = $item->worked_days;
        $proportionalSalary = ($baseSalary / 30) * $workedDays;
        
        // Add bonuses
        $bonuses = $item->bonuses_amount ?? 0;
        $totalEarnings = $proportionalSalary + $bonuses;
        
        // Calculate deductions
        $afpAmount = $totalEarnings * 0.1244; // AFP 12.44%
        $healthAmount = $totalEarnings * 0.07; // Health 7%
        
        // Calculate income tax
        $taxableIncome = $totalEarnings - $afpAmount - $healthAmount;
        $taxAmount = $this->calculateIncomeTax($taxableIncome);
        
        // Other deductions
        $otherDeductions = $item->other_deductions ?? 0;
        
        // Update item
        $item->update([
            'total_earnings' => $totalEarnings,
            'afp_amount' => $afpAmount,
            'health_amount' => $healthAmount,
            'tax_amount' => $taxAmount,
            'total_deductions' => $afpAmount + $healthAmount + $taxAmount + $otherDeductions,
            'net_pay' => $totalEarnings - ($afpAmount + $healthAmount + $taxAmount + $otherDeductions),
        ]);
    }

    private function calculateIncomeTax(float $taxableIncome): float
    {
        $incomeInUTM = $taxableIncome / self::UTM_VALUE;
        
        foreach (self::TAX_BRACKETS as $bracket) {
            if ($incomeInUTM >= $bracket['from'] && $incomeInUTM < $bracket['to']) {
                $tax = ($incomeInUTM * $bracket['rate'] - $bracket['deduction']) * self::UTM_VALUE;
                return max(0, $tax);
            }
        }
        
        return 0;
    }

    private function recalculatePayrollTotals(Payroll $payroll): void
    {
        $totals = $payroll->items()->selectRaw('
            SUM(total_earnings) as earnings,
            SUM(total_deductions) as deductions,
            SUM(net_pay) as net
        ')->first();

        $payroll->update([
            'total_earnings' => $totals->earnings ?? 0,
            'total_deductions' => $totals->deductions ?? 0,
            'net_pay' => $totals->net ?? 0,
        ]);
    }

    private function getPayrollBreakdownByDepartment(Payroll $payroll): array
    {
        $breakdown = [];
        
        foreach ($payroll->items as $item) {
            $deptName = $item->employee->department->name ?? 'Sin Departamento';
            
            if (!isset($breakdown[$deptName])) {
                $breakdown[$deptName] = [
                    'count' => 0,
                    'total_earnings' => 0,
                    'total_deductions' => 0,
                    'net_pay' => 0,
                ];
            }
            
            $breakdown[$deptName]['count']++;
            $breakdown[$deptName]['total_earnings'] += $item->total_earnings;
            $breakdown[$deptName]['total_deductions'] += $item->total_deductions;
            $breakdown[$deptName]['net_pay'] += $item->net_pay;
        }
        
        return $breakdown;
    }

    private function getPayrollBreakdownByType(Payroll $payroll): array
    {
        return [
            'earnings' => [
                'base_salary' => $payroll->items->sum('base_salary'),
                'bonuses' => $payroll->items->sum('bonuses_amount'),
                'other' => $payroll->items->sum('other_earnings'),
            ],
            'deductions' => [
                'afp' => $payroll->items->sum('afp_amount'),
                'health' => $payroll->items->sum('health_amount'),
                'tax' => $payroll->items->sum('tax_amount'),
                'other' => $payroll->items->sum('other_deductions'),
            ],
        ];
    }

    private function getItemEarningsBreakdown($item): array
    {
        $breakdown = [];
        
        $breakdown[] = [
            'concept' => 'Sueldo Base',
            'amount' => $item->base_salary,
            'calculation' => "{$item->worked_days} días",
        ];
        
        if ($item->bonuses_amount > 0) {
            foreach ($item->bonuses ?? [] as $bonus) {
                $breakdown[] = [
                    'concept' => $bonus['description'],
                    'amount' => $bonus['amount'],
                    'calculation' => $bonus['type'],
                ];
            }
        }
        
        return $breakdown;
    }

    private function getItemDeductionsBreakdown($item): array
    {
        $breakdown = [];
        
        $breakdown[] = [
            'concept' => 'AFP',
            'amount' => $item->afp_amount,
            'calculation' => '12.44%',
        ];
        
        $breakdown[] = [
            'concept' => 'Salud',
            'amount' => $item->health_amount,
            'calculation' => '7%',
        ];
        
        if ($item->tax_amount > 0) {
            $breakdown[] = [
                'concept' => 'Impuesto Único',
                'amount' => $item->tax_amount,
                'calculation' => 'Según tabla',
            ];
        }
        
        foreach ($item->deductions ?? [] as $deduction) {
            $breakdown[] = [
                'concept' => $deduction['description'],
                'amount' => $deduction['amount'],
                'calculation' => $deduction['type'],
            ];
        }
        
        return $breakdown;
    }
}