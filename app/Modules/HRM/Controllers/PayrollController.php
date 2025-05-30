<?php

namespace App\Modules\HRM\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HRM\Models\Employee;
use App\Modules\HRM\Models\Payroll;
use App\Traits\ChecksPermissions;
use App\Modules\HRM\Services\PayrollService;
use App\Modules\HRM\Requests\PayrollRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class PayrollController extends Controller
{
    use ChecksPermissions;
    
    protected $payrollService;
    
    public function __construct(PayrollService $payrollService)
    {
        $this->middleware(['auth', 'verified', 'check.subscription', 'check.module:hrm']);
        $this->payrollService = $payrollService;
    }
    
    public function index(Request $request)
    {
        $this->checkPermission('payroll.view');
        
        $filters = $request->only(['year', 'month', 'employee_id', 'department_id', 'status']);
        $payrolls = $this->payrollService->getPayrollsList($filters);
        $statistics = $this->payrollService->getPayrollStatistics($filters);
        
        return Inertia::render('HRM/Payroll/Index', [
            'payrolls' => $payrolls,
            'statistics' => $statistics,
            'filters' => $filters,
            'employees' => $this->payrollService->getActiveEmployees(),
            'departments' => $this->payrollService->getDepartments(),
            'years' => $this->payrollService->getAvailableYears(),
            'months' => $this->getMonthsArray(),
            'statusOptions' => [
                'draft' => 'Borrador',
                'calculated' => 'Calculado',
                'approved' => 'Aprobado',
                'paid' => 'Pagado',
                'cancelled' => 'Cancelado'
            ]
        ]);
    }
    
    public function create(Request $request)
    {
        $this->checkPermission('payroll.create');
        
        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);
        
        // Check if payroll already exists
        $existingPayroll = $this->payrollService->checkExistingPayroll($year, $month);
        if ($existingPayroll) {
            return redirect()->route('hrm.payroll.show', $existingPayroll)
                ->with('warning', 'Ya existe una nómina para este período.');
        }
        
        return Inertia::render('HRM/Payroll/Create', [
            'year' => $year,
            'month' => $month,
            'employees' => $this->payrollService->getEligibleEmployees($year, $month),
            'payrollSettings' => config('hrm.payroll'),
        ]);
    }
    
    public function store(PayrollRequest $request)
    {
        $this->checkPermission('payroll.create');
        
        DB::beginTransaction();
        try {
            $payroll = $this->payrollService->createPayroll($request->validated());
            
            DB::commit();
            
            return redirect()->route('hrm.payroll.show', $payroll)
                ->with('success', 'Nómina creada exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al crear la nómina: ' . $e->getMessage());
        }
    }
    
    public function show(Payroll $payroll)
    {
        $this->checkPermission('payroll.view');
        
        if ($payroll->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        $payrollData = $this->payrollService->getPayrollDetails($payroll);
        
        return Inertia::render('HRM/Payroll/Show', $payrollData);
    }
    
    public function edit(Payroll $payroll)
    {
        $this->checkPermission('payroll.edit');
        
        if ($payroll->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        if (!in_array($payroll->status, ['draft', 'calculated'])) {
            return redirect()->route('hrm.payroll.show', $payroll)
                ->with('error', 'Solo se pueden editar nóminas en estado borrador o calculado.');
        }
        
        return Inertia::render('HRM/Payroll/Edit', [
            'payroll' => $payroll->load('items.employee'),
            'payrollSettings' => config('hrm.payroll'),
        ]);
    }
    
    public function update(PayrollRequest $request, Payroll $payroll)
    {
        $this->checkPermission('payroll.edit');
        
        if ($payroll->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        if (!in_array($payroll->status, ['draft', 'calculated'])) {
            return back()->with('error', 'Solo se pueden editar nóminas en estado borrador o calculado.');
        }
        
        DB::beginTransaction();
        try {
            $payroll = $this->payrollService->updatePayroll($payroll, $request->validated());
            
            DB::commit();
            
            return redirect()->route('hrm.payroll.show', $payroll)
                ->with('success', 'Nómina actualizada exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al actualizar la nómina: ' . $e->getMessage());
        }
    }
    
    public function calculate(Payroll $payroll)
    {
        $this->checkPermission('payroll.process');
        
        if ($payroll->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        if ($payroll->status !== 'draft') {
            return back()->with('error', 'Solo se pueden calcular nóminas en estado borrador.');
        }
        
        DB::beginTransaction();
        try {
            $payroll = $this->payrollService->calculatePayroll($payroll);
            
            DB::commit();
            
            return back()->with('success', 'Nómina calculada exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al calcular la nómina: ' . $e->getMessage());
        }
    }
    
    public function approve(Request $request, Payroll $payroll)
    {
        $this->checkPermission('payroll.approve');
        
        if ($payroll->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        if ($payroll->status !== 'calculated') {
            return back()->with('error', 'Solo se pueden aprobar nóminas calculadas.');
        }
        
        $request->validate([
            'notes' => 'nullable|string|max:500'
        ]);
        
        $result = $this->payrollService->approvePayroll($payroll, $request->get('notes'));
        
        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }
        
        return back()->with('success', 'Nómina aprobada exitosamente.');
    }
    
    public function process(Payroll $payroll)
    {
        $this->checkPermission('payroll.process');
        
        if ($payroll->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        if ($payroll->status !== 'approved') {
            return back()->with('error', 'Solo se pueden procesar nóminas aprobadas.');
        }
        
        DB::beginTransaction();
        try {
            $result = $this->payrollService->processPayroll($payroll);
            
            if (!$result['success']) {
                throw new \Exception($result['message']);
            }
            
            DB::commit();
            
            return back()->with('success', 'Nómina procesada exitosamente. Se han generado los pagos.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al procesar la nómina: ' . $e->getMessage());
        }
    }
    
    public function addDeduction(Request $request, Payroll $payroll)
    {
        $this->checkPermission('payroll.deductions');
        
        if ($payroll->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        if (!in_array($payroll->status, ['draft', 'calculated'])) {
            return back()->with('error', 'No se pueden agregar deducciones a esta nómina.');
        }
        
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'type' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'description' => 'required|string|max:255'
        ]);
        
        $this->payrollService->addDeduction($payroll, $request->all());
        
        return back()->with('success', 'Deducción agregada exitosamente.');
    }
    
    public function addBonus(Request $request, Payroll $payroll)
    {
        $this->checkPermission('payroll.bonuses');
        
        if ($payroll->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        if (!in_array($payroll->status, ['draft', 'calculated'])) {
            return back()->with('error', 'No se pueden agregar bonos a esta nómina.');
        }
        
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'type' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'description' => 'required|string|max:255',
            'is_taxable' => 'boolean'
        ]);
        
        $this->payrollService->addBonus($payroll, $request->all());
        
        return back()->with('success', 'Bono agregado exitosamente.');
    }
    
    public function payslip(Payroll $payroll, Employee $employee)
    {
        $this->checkPermission('payroll.view');
        
        if ($payroll->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        $payslipData = $this->payrollService->getPayslipData($payroll, $employee);
        
        if (!$payslipData) {
            abort(404, 'Liquidación no encontrada.');
        }
        
        $pdf = Pdf::loadView('hrm.payslip', [
            'payslip' => $payslipData,
            'company' => auth()->user()->tenant,
        ]);
        
        return $pdf->download("liquidacion-{$employee->employee_code}-{$payroll->year}-{$payroll->month}.pdf");
    }
    
    public function bulkPayslips(Payroll $payroll)
    {
        $this->checkPermission('payroll.export');
        
        if ($payroll->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        return $this->payrollService->generateBulkPayslips($payroll);
    }
    
    public function summary(Request $request)
    {
        $this->checkPermission('payroll.reports');
        
        $year = $request->get('year', now()->year);
        $summary = $this->payrollService->getYearlySummary($year);
        
        return Inertia::render('HRM/Payroll/Summary', [
            'summary' => $summary,
            'year' => $year,
            'years' => $this->payrollService->getAvailableYears(),
        ]);
    }
    
    public function taxReport(Request $request)
    {
        $this->checkPermission('payroll.reports');
        
        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);
        
        $report = $this->payrollService->generateTaxReport($year, $month);
        
        return Inertia::render('HRM/Payroll/TaxReport', [
            'report' => $report,
            'year' => $year,
            'month' => $month,
            'years' => $this->payrollService->getAvailableYears(),
            'months' => $this->getMonthsArray(),
        ]);
    }
    
    public function socialSecurityReport(Request $request)
    {
        $this->checkPermission('payroll.reports');
        
        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);
        
        $report = $this->payrollService->generateSocialSecurityReport($year, $month);
        
        return Inertia::render('HRM/Payroll/SocialSecurityReport', [
            'report' => $report,
            'year' => $year,
            'month' => $month,
            'years' => $this->payrollService->getAvailableYears(),
            'months' => $this->getMonthsArray(),
        ]);
    }
    
    public function export(Request $request)
    {
        $this->checkPermission('payroll.export');
        
        $filters = $request->only(['year', 'month', 'employee_id', 'department_id']);
        
        return $this->payrollService->exportPayroll($filters);
    }
    
    public function bankFile(Payroll $payroll)
    {
        $this->checkPermission('payroll.payments');
        
        if ($payroll->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        if ($payroll->status !== 'approved') {
            return back()->with('error', 'Solo se pueden generar archivos bancarios para nóminas aprobadas.');
        }
        
        return $this->payrollService->generateBankFile($payroll);
    }
    
    private function getMonthsArray(): array
    {
        return [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
        ];
    }
}