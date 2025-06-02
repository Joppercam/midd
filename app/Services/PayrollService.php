<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\EmploymentContract;
use App\Models\AttendanceRecord;
use App\Models\PayrollSettings;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PayrollService
{
    protected ?PayrollSettings $settings = null;

    public function __construct()
    {
        // Settings will be loaded when needed
    }
    
    protected function getSettings(): PayrollSettings
    {
        if ($this->settings === null) {
            $this->settings = $this->getPayrollSettings();
        }
        
        return $this->settings;
    }

    /**
     * Calculate payroll for a period
     */
    public function calculatePayrollForPeriod(PayrollPeriod $period, ?Collection $employees = null): array
    {
        $period->markAsProcessing();

        if (!$employees) {
            $employees = Employee::active()
                ->whereHas('currentContract', function($query) {
                    $query->where('status', EmploymentContract::STATUS_ACTIVE);
                })
                ->with(['currentContract.department', 'currentContract.position'])
                ->get();
        }

        $results = [
            'processed' => 0,
            'failed' => 0,
            'errors' => [],
            'payslips' => [],
        ];

        foreach ($employees as $employee) {
            try {
                $payslip = $this->calculateEmployeePayslip($employee, $period);
                $results['payslips'][] = $payslip;
                $results['processed']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'employee' => $employee->full_name,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $period->markAsCalculated();

        return $results;
    }

    /**
     * Calculate individual employee payslip
     */
    public function calculateEmployeePayslip(Employee $employee, PayrollPeriod $period): Payslip
    {
        $contract = $employee->currentContract;
        if (!$contract) {
            throw new \Exception("Employee {$employee->full_name} has no active contract");
        }

        // Get or create payslip
        $payslip = Payslip::firstOrNew([
            'employee_id' => $employee->id,
            'payroll_period_id' => $period->id,
        ]);

        // Basic information
        $payslip->fill([
            'tenant_id' => tenant()->id,
            'pay_date' => $period->end_date,
            'base_salary' => $contract->base_salary,
        ]);

        // Calculate attendance and hours
        $attendanceData = $this->calculateAttendance($employee, $period);
        $payslip->fill($attendanceData);

        // Calculate earnings
        $earnings = $this->calculateEarnings($employee, $contract, $period, $attendanceData);
        $payslip->fill($earnings);

        // Calculate deductions
        $deductions = $this->calculateDeductions($employee, $contract, $earnings['total_earnings']);
        $payslip->fill($deductions);

        // Calculate net pay
        $payslip->net_pay = $earnings['total_earnings'] - $deductions['total_deductions'];

        $payslip->save();

        return $payslip;
    }

    /**
     * Calculate attendance data for employee in period
     */
    protected function calculateAttendance(Employee $employee, PayrollPeriod $period): array
    {
        $attendanceRecords = AttendanceRecord::where('employee_id', $employee->id)
            ->whereBetween('date', [$period->start_date, $period->end_date])
            ->get();

        $workedDays = $attendanceRecords->whereIn('status', ['present', 'late', 'partial'])->count();
        $totalDays = $period->working_days;
        $regularHours = $attendanceRecords->sum('regular_hours');
        $overtimeHours = $attendanceRecords->sum('overtime_hours');

        return [
            'worked_days' => $workedDays,
            'total_days' => $totalDays,
            'regular_hours' => $regularHours,
            'overtime_hours' => $overtimeHours,
        ];
    }

    /**
     * Calculate earnings for employee
     */
    protected function calculateEarnings(Employee $employee, EmploymentContract $contract, PayrollPeriod $period, array $attendanceData): array
    {
        // Basic pay (prorated by worked days)
        $basicPay = ($contract->base_salary / $attendanceData['total_days']) * $attendanceData['worked_days'];

        // Overtime pay
        $hourlyRate = $contract->hourly_rate;
        $overtimePay = $attendanceData['overtime_hours'] * $hourlyRate * $this->getSettings()->overtime_rate;

        // Allowances
        $familyAllowance = $this->calculateFamilyAllowance($employee);
        $transportAllowance = $this->calculateTransportAllowance($employee, $contract);
        $mealAllowance = $this->calculateMealAllowance($employee, $contract);
        $otherAllowances = $this->calculateOtherAllowances($employee, $contract);

        // Bonuses and commissions
        $bonus = $this->calculateBonus($employee, $period);
        $commission = $this->calculateCommission($employee, $period);

        $totalEarnings = $basicPay + $overtimePay + $familyAllowance + 
                        $transportAllowance + $mealAllowance + $otherAllowances + 
                        $bonus + $commission;

        return [
            'basic_pay' => round($basicPay, 2),
            'overtime_pay' => round($overtimePay, 2),
            'family_allowance' => round($familyAllowance, 2),
            'transport_allowance' => round($transportAllowance, 2),
            'meal_allowance' => round($mealAllowance, 2),
            'other_allowances' => round($otherAllowances, 2),
            'bonus' => round($bonus, 2),
            'commission' => round($commission, 2),
            'total_earnings' => round($totalEarnings, 2),
        ];
    }

    /**
     * Calculate deductions for employee
     */
    protected function calculateDeductions(Employee $employee, EmploymentContract $contract, float $grossPay): array
    {
        // Pension deduction (AFP)
        $pensionDeduction = $grossPay * $this->getSettings()->pension_rate;

        // Health deduction
        $healthDeduction = $grossPay * $this->getSettings()->health_rate;

        // Unemployment insurance
        $unemploymentInsurance = $grossPay * $this->getSettings()->unemployment_rate;

        // Income tax
        $incomeTax = $this->calculateIncomeTax($grossPay);

        // Other deductions (loans, advances, etc.)
        $otherDeductions = $this->calculateOtherDeductions($employee);

        $totalDeductions = $pensionDeduction + $healthDeduction + 
                          $unemploymentInsurance + $incomeTax + $otherDeductions;

        return [
            'pension_deduction' => round($pensionDeduction, 2),
            'health_deduction' => round($healthDeduction, 2),
            'unemployment_insurance' => round($unemploymentInsurance, 2),
            'income_tax' => round($incomeTax, 2),
            'other_deductions' => round($otherDeductions, 2),
            'total_deductions' => round($totalDeductions, 2),
        ];
    }

    /**
     * Calculate family allowance (Asignación Familiar)
     */
    protected function calculateFamilyAllowance(Employee $employee): float
    {
        // In Chile, family allowance is based on number of dependents and income level
        // This is a simplified calculation
        return $this->getSettings()->family_allowance_amount ?? 0;
    }

    /**
     * Calculate transport allowance
     */
    protected function calculateTransportAllowance(Employee $employee, EmploymentContract $contract): float
    {
        $allowance = $contract->getAllowance('transport');
        return $allowance ? $allowance['amount'] : 0;
    }

    /**
     * Calculate meal allowance
     */
    protected function calculateMealAllowance(Employee $employee, EmploymentContract $contract): float
    {
        $allowance = $contract->getAllowance('meal');
        return $allowance ? $allowance['amount'] : 0;
    }

    /**
     * Calculate other allowances
     */
    protected function calculateOtherAllowances(Employee $employee, EmploymentContract $contract): float
    {
        $total = 0;
        $allowances = $contract->allowances ?? [];
        
        foreach ($allowances as $allowance) {
            if (!in_array($allowance['type'], ['transport', 'meal'])) {
                $total += $allowance['amount'];
            }
        }

        return $total;
    }

    /**
     * Calculate bonus for period
     */
    protected function calculateBonus(Employee $employee, PayrollPeriod $period): float
    {
        // Implement bonus calculation logic based on performance, targets, etc.
        return 0;
    }

    /**
     * Calculate commission for period
     */
    protected function calculateCommission(Employee $employee, PayrollPeriod $period): float
    {
        // Implement commission calculation logic based on sales, etc.
        return 0;
    }

    /**
     * Calculate income tax using Chilean tax brackets
     */
    protected function calculateIncomeTax(float $grossPay): float
    {
        $taxBrackets = $this->getSettings()->tax_brackets ?? $this->getDefaultTaxBrackets();
        $tax = 0;
        $remainingIncome = $grossPay;

        foreach ($taxBrackets as $bracket) {
            $bracketIncome = min($remainingIncome, $bracket['max'] - $bracket['min']);
            if ($bracketIncome > 0) {
                $tax += $bracketIncome * $bracket['rate'];
                $remainingIncome -= $bracketIncome;
            }

            if ($remainingIncome <= 0) {
                break;
            }
        }

        return $tax;
    }

    /**
     * Calculate other deductions (loans, advances, etc.)
     */
    protected function calculateOtherDeductions(Employee $employee): float
    {
        // Implement logic for other deductions like loans, salary advances, etc.
        return 0;
    }

    /**
     * Get payroll settings for tenant
     */
    protected function getPayrollSettings(): PayrollSettings
    {
        $tenantId = auth()->user()->tenant_id ?? null;
        
        if (!$tenantId) {
            throw new \Exception('No se pudo determinar el tenant ID para configurar la nómina');
        }
        
        return PayrollSettings::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'pension_rate' => 0.1000, // 10%
                'health_rate' => 0.0700,  // 7%
                'unemployment_rate' => 0.0060, // 0.6%
                'overtime_rate' => 1.50,  // 50% extra
                'family_allowance_amount' => 13000, // Approximate amount in CLP
                'tax_brackets' => $this->getDefaultTaxBrackets(),
                'working_hours' => [
                    'daily' => 8,
                    'weekly' => 45,
                    'monthly' => 180,
                ],
            ]
        );
    }

    /**
     * Get default Chilean tax brackets (2025 values - approximate)
     */
    protected function getDefaultTaxBrackets(): array
    {
        return [
            ['min' => 0, 'max' => 670000, 'rate' => 0.00],      // 0%
            ['min' => 670000, 'max' => 1490000, 'rate' => 0.04], // 4%
            ['min' => 1490000, 'max' => 2480000, 'rate' => 0.08], // 8%
            ['min' => 2480000, 'max' => 3470000, 'rate' => 0.135], // 13.5%
            ['min' => 3470000, 'max' => 4630000, 'rate' => 0.23], // 23%
            ['min' => 4630000, 'max' => 6150000, 'rate' => 0.304], // 30.4%
            ['min' => 6150000, 'max' => 13750000, 'rate' => 0.35], // 35%
            ['min' => 13750000, 'max' => PHP_INT_MAX, 'rate' => 0.40], // 40%
        ];
    }

    /**
     * Generate payroll report
     */
    public function generatePayrollReport(PayrollPeriod $period): array
    {
        $payslips = $period->payslips()->with('employee')->get();
        
        return [
            'period' => $period,
            'summary' => $period->getPayrollSummary(),
            'payslips' => $payslips,
            'statistics' => [
                'average_gross_pay' => $period->average_gross_pay,
                'average_net_pay' => $period->average_net_pay,
                'effective_deduction_rate' => $period->effective_deduction_rate,
                'total_employees' => $period->employee_count,
            ],
        ];
    }

    /**
     * Create monthly payroll period
     */
    public function createMonthlyPeriod(int $year, int $month): PayrollPeriod
    {
        $startDate = Carbon::createFromDate($year, $month, 1);
        $endDate = $startDate->copy()->endOfMonth();

        return PayrollPeriod::createForDateRange($startDate, $endDate, 'monthly');
    }

    /**
     * Create weekly payroll period
     */
    public function createWeeklyPeriod(Carbon $startDate): PayrollPeriod
    {
        $endDate = $startDate->copy()->addDays(6);
        
        return PayrollPeriod::createForDateRange($startDate, $endDate, 'weekly');
    }

    /**
     * Approve payroll period
     */
    public function approvePeriod(PayrollPeriod $period, Employee $approver): void
    {
        if (!$period->canBeApproved()) {
            throw new \Exception('Payroll period cannot be approved in its current status');
        }

        $period->approve($approver);
    }

    /**
     * Get payroll statistics for tenant
     */
    public function getPayrollStatistics(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $tenant = tenant();
        
        $query = PayrollPeriod::where('tenant_id', $tenant->id);
        
        if ($startDate && $endDate) {
            $query->whereBetween('start_date', [$startDate, $endDate]);
        }

        $periods = $query->get();

        return [
            'total_periods' => $periods->count(),
            'total_gross_pay' => $periods->sum('total_gross_pay'),
            'total_net_pay' => $periods->sum('total_net_pay'),
            'total_deductions' => $periods->sum('total_deductions'),
            'average_payroll' => $periods->avg('total_gross_pay'),
            'periods_by_status' => $periods->groupBy('status')->map->count(),
        ];
    }
}