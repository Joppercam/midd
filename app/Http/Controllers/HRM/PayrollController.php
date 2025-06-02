<?php

namespace App\Http\Controllers\HRM;

use App\Http\Controllers\Controller;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\Employee;
use App\Services\PayrollService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class PayrollController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:hrm.payroll.view')->only(['index', 'show', 'showPayslip']);
        $this->middleware('permission:hrm.payroll.create')->only(['create', 'store', 'calculate']);
        $this->middleware('permission:hrm.payroll.edit')->only(['edit', 'update', 'approve']);
        $this->middleware('permission:hrm.payroll.delete')->only(['destroy']);
    }
    
    protected function getPayrollService(): PayrollService
    {
        return app(PayrollService::class);
    }

    /**
     * Display payroll periods
     */
    public function index(Request $request)
    {
        try {
            $tenantId = auth()->user()->tenant_id;
            
            // Create simple empty data structure
            $payrolls = new \Illuminate\Pagination\LengthAwarePaginator(
                collect([]),
                0,
                20,
                1,
                ['path' => request()->url(), 'pageName' => 'page']
            );

            $summary = [
                'total_employees' => Employee::where('tenant_id', $tenantId)->count(),
                'gross_total' => 0,
                'deductions_total' => 0,
                'net_total' => 0,
            ];

            $departments = \App\Models\Department::where('tenant_id', $tenantId)->get();

            return Inertia::render('HRM/Payroll/Index', [
                'payrolls' => $payrolls,
                'departments' => $departments,
                'summary' => $summary,
                'filters' => $request->only(['status', 'department', 'period']),
            ]);
        } catch (\Exception $e) {
            \Log::error('PayrollController index error: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Show payroll period details
     */
    public function show(PayrollPeriod $period)
    {
        $period->load([
            'payslips.employee',
            'approvedBy'
        ]);

        $summary = $period->getPayrollSummary();

        return Inertia::render('HRM/Payroll/Show', [
            'period' => $period,
            'summary' => $summary,
        ]);
    }

    /**
     * Create new payroll period
     */
    public function create()
    {
        return Inertia::render('HRM/Payroll/Create');
    }

    /**
     * Store new payroll period
     */
    public function store(Request $request)
    {
        $request->validate([
            'period_type' => 'required|in:monthly,weekly,biweekly',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        $period = PayrollPeriod::createForDateRange(
            $startDate, 
            $endDate, 
            $request->period_type
        );

        return redirect()->route('hrm.payroll.show', $period)
            ->with('success', 'Período de nómina creado exitosamente.');
    }

    /**
     * Calculate payroll for period
     */
    public function calculate(Request $request, PayrollPeriod $period)
    {
        if (!$period->canBeProcessed()) {
            return back()->withErrors(['calculate' => 'Este período no puede ser procesado.']);
        }

        try {
            $results = $this->getPayrollService()->calculatePayrollForPeriod($period);

            $message = "Nómina calculada: {$results['processed']} empleados procesados";
            if ($results['failed'] > 0) {
                $message .= ", {$results['failed']} fallaron";
            }

            return back()->with('success', $message)
                         ->with('calculation_results', $results);

        } catch (\Exception $e) {
            return back()->withErrors(['calculate' => 'Error al calcular nómina: ' . $e->getMessage()]);
        }
    }

    /**
     * Approve payroll period
     */
    public function approve(PayrollPeriod $period)
    {
        if (!$period->canBeApproved()) {
            return back()->withErrors(['approve' => 'Este período no puede ser aprobado.']);
        }

        try {
            $approver = Employee::where('user_id', auth()->id())
                ->where('tenant_id', auth()->user()->tenant_id)
                ->first();

            if (!$approver) {
                return back()->withErrors(['approve' => 'Usuario no tiene perfil de empleado.']);
            }

            $this->getPayrollService()->approvePeriod($period, $approver);

            return back()->with('success', 'Período de nómina aprobado exitosamente.');

        } catch (\Exception $e) {
            return back()->withErrors(['approve' => 'Error al aprobar período: ' . $e->getMessage()]);
        }
    }

    /**
     * Mark period as paid
     */
    public function markPaid(PayrollPeriod $period)
    {
        if (!$period->isFinalized()) {
            return back()->withErrors(['paid' => 'El período debe estar aprobado para marcarlo como pagado.']);
        }

        $period->markAsPaid();

        return back()->with('success', 'Período marcado como pagado exitosamente.');
    }

    /**
     * Show individual payslip
     */
    public function showPayslip(Payslip $payslip)
    {
        $payslip->load(['employee', 'payrollPeriod']);

        return Inertia::render('HRM/Payroll/Payslip', [
            'payslip' => $payslip,
            'earnings_breakdown' => $payslip->getEarningsBreakdown(),
            'deductions_breakdown' => $payslip->getDeductionsBreakdown(),
        ]);
    }

    /**
     * Download payslip PDF
     */
    public function downloadPayslip(Payslip $payslip)
    {
        $pdf = \PDF::loadView('payroll.payslip-pdf', [
            'payslip' => $payslip,
            'employee' => $payslip->employee,
            'period' => $payslip->payrollPeriod,
            'tenant' => auth()->user()->tenant,
            'earnings_breakdown' => $payslip->getEarningsBreakdown(),
            'deductions_breakdown' => $payslip->getDeductionsBreakdown(),
        ]);

        $filename = "liquidacion_{$payslip->employee->employee_number}_{$payslip->payrollPeriod->start_date->format('Y_m')}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Employee payslips (for employee self-service)
     */
    public function myPayslips()
    {
        $user = auth()->user();
        $employee = Employee::where('user_id', $user->id)
            ->where('tenant_id', $user->tenant_id)
            ->first();

        if (!$employee) {
            abort(404, 'No se encontró el perfil de empleado.');
        }

        $payslips = Payslip::where('employee_id', $employee->id)
            ->with('payrollPeriod')
            ->where('status', '!=', Payslip::STATUS_DRAFT)
            ->latest('pay_date')
            ->paginate(12);

        return Inertia::render('HRM/Employee/Payslips', [
            'payslips' => $payslips,
            'employee' => $employee,
        ]);
    }

    /**
     * Create monthly payroll period
     */
    public function createMonthly(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:2030',
            'month' => 'required|integer|min:1|max:12',
        ]);

        try {
            $period = $this->getPayrollService()->createMonthlyPeriod(
                $request->year, 
                $request->month
            );

            return redirect()->route('hrm.payroll.show', $period)
                ->with('success', 'Período mensual creado exitosamente.');

        } catch (\Exception $e) {
            return back()->withErrors(['create' => 'Error al crear período: ' . $e->getMessage()]);
        }
    }

    /**
     * Payroll reports
     */
    public function reports(Request $request)
    {
        $startDate = $request->start_date ? Carbon::parse($request->start_date) : now()->startOfYear();
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : now()->endOfYear();

        $statistics = $this->getPayrollService()->getPayrollStatistics($startDate, $endDate);

        $periods = PayrollPeriod::where('tenant_id', auth()->user()->tenant_id)
            ->whereBetween('start_date', [$startDate, $endDate])
            ->with('payslips')
            ->get();

        return Inertia::render('HRM/Payroll/Reports', [
            'statistics' => $statistics,
            'periods' => $periods,
            'filters' => $request->only(['start_date', 'end_date']),
        ]);
    }

    /**
     * Recalculate payslip
     */
    public function recalculatePayslip(Payslip $payslip)
    {
        if (!$payslip->canBeEdited()) {
            return back()->withErrors(['recalculate' => 'Esta liquidación no puede ser editada.']);
        }

        try {
            $updatedPayslip = $this->getPayrollService()->calculateEmployeePayslip(
                $payslip->employee, 
                $payslip->payrollPeriod
            );

            return back()->with('success', 'Liquidación recalculada exitosamente.');

        } catch (\Exception $e) {
            return back()->withErrors(['recalculate' => 'Error al recalcular: ' . $e->getMessage()]);
        }
    }

    /**
     * Bulk operations on payslips
     */
    public function bulkAction(Request $request, PayrollPeriod $period)
    {
        $request->validate([
            'action' => 'required|in:approve,delete,recalculate',
            'payslip_ids' => 'required|array|min:1',
            'payslip_ids.*' => 'exists:payslips,id',
        ]);

        $payslips = Payslip::whereIn('id', $request->payslip_ids)
            ->where('payroll_period_id', $period->id)
            ->get();

        $processed = 0;
        $failed = 0;

        foreach ($payslips as $payslip) {
            try {
                switch ($request->action) {
                    case 'approve':
                        if ($payslip->canBeEdited()) {
                            $payslip->approve();
                            $processed++;
                        }
                        break;
                    case 'delete':
                        if ($payslip->canBeEdited()) {
                            $payslip->delete();
                            $processed++;
                        }
                        break;
                    case 'recalculate':
                        if ($payslip->canBeEdited()) {
                            $this->getPayrollService()->calculateEmployeePayslip(
                                $payslip->employee, 
                                $payslip->payrollPeriod
                            );
                            $processed++;
                        }
                        break;
                }
            } catch (\Exception $e) {
                $failed++;
            }
        }

        $message = "Acción completada: {$processed} liquidaciones procesadas";
        if ($failed > 0) {
            $message .= ", {$failed} fallaron";
        }

        return back()->with('success', $message);
    }
}