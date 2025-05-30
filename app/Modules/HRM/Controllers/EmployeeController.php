<?php

namespace App\Modules\HRM\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HRM\Models\Employee;
use App\Modules\HRM\Models\Department;
use App\Modules\HRM\Models\Position;
use App\Traits\ChecksPermissions;
use App\Modules\HRM\Services\EmployeeService;
use App\Modules\HRM\Requests\EmployeeRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    use ChecksPermissions;
    
    protected $employeeService;
    
    public function __construct(EmployeeService $employeeService)
    {
        $this->middleware(['auth', 'verified', 'check.subscription', 'check.module:hrm']);
        $this->employeeService = $employeeService;
    }
    
    public function index(Request $request)
    {
        $this->checkPermission('employees.view');
        
        $filters = $request->only(['search', 'department_id', 'status', 'contract_type']);
        $employees = $this->employeeService->getEmployeesList($filters);
        $statistics = $this->employeeService->getEmployeeStatistics();

        return Inertia::render('HRM/Employees/Index', [
            'employees' => $employees,
            'statistics' => $statistics,
            'filters' => $filters,
            'departments' => $this->employeeService->getDepartments(),
            'contractTypes' => config('hrm.documents.contract_templates'),
            'statusOptions' => [
                'active' => 'Activo',
                'on_leave' => 'De Licencia',
                'terminated' => 'Desvinculado',
                'suspended' => 'Suspendido'
            ]
        ]);
    }

    public function create()
    {
        $this->checkPermission('employees.create');
        
        return Inertia::render('HRM/Employees/Create', [
            'departments' => $this->employeeService->getDepartments(),
            'positions' => $this->employeeService->getPositions(),
            'managers' => $this->employeeService->getManagers(),
            'contractTypes' => config('hrm.documents.contract_templates'),
            'afpOptions' => config('hrm.payroll.social_security.afp'),
            'healthOptions' => config('hrm.payroll.social_security.health'),
            'countries' => $this->employeeService->getCountries(),
            'currencies' => ['CLP' => 'Peso Chileno', 'USD' => 'Dólar', 'EUR' => 'Euro'],
            'paymentFrequencies' => ['monthly' => 'Mensual', 'biweekly' => 'Quincenal', 'weekly' => 'Semanal'],
            'shiftTypes' => config('hrm.attendance.shift_types'),
        ]);
    }

    public function store(EmployeeRequest $request)
    {
        $this->checkPermission('employees.create');
        
        DB::beginTransaction();
        try {
            $employee = $this->employeeService->createEmployee($request->validated());
            
            DB::commit();
            
            return redirect()->route('hrm.employees.show', $employee)
                ->with('success', 'Empleado creado exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al crear el empleado: ' . $e->getMessage());
        }
    }

    public function show(Employee $employee)
    {
        $this->checkPermission('employees.view');
        
        if ($employee->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        $employeeData = $this->employeeService->getEmployeeDetails($employee);
        
        return Inertia::render('HRM/Employees/Show', $employeeData);
    }

    public function edit(Employee $employee)
    {
        $this->checkPermission('employees.edit');
        
        if ($employee->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        return Inertia::render('HRM/Employees/Edit', [
            'employee' => $employee->load(['department', 'position', 'manager']),
            'departments' => $this->employeeService->getDepartments(),
            'positions' => $this->employeeService->getPositions(),
            'managers' => $this->employeeService->getManagers($employee->id),
            'contractTypes' => config('hrm.documents.contract_templates'),
            'afpOptions' => config('hrm.payroll.social_security.afp'),
            'healthOptions' => config('hrm.payroll.social_security.health'),
            'countries' => $this->employeeService->getCountries(),
            'currencies' => ['CLP' => 'Peso Chileno', 'USD' => 'Dólar', 'EUR' => 'Euro'],
            'paymentFrequencies' => ['monthly' => 'Mensual', 'biweekly' => 'Quincenal', 'weekly' => 'Semanal'],
            'shiftTypes' => config('hrm.attendance.shift_types'),
        ]);
    }

    public function update(EmployeeRequest $request, Employee $employee)
    {
        $this->checkPermission('employees.edit');
        
        if ($employee->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        DB::beginTransaction();
        try {
            $employee = $this->employeeService->updateEmployee($employee, $request->validated());
            
            DB::commit();
            
            return redirect()->route('hrm.employees.show', $employee)
                ->with('success', 'Empleado actualizado exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al actualizar el empleado: ' . $e->getMessage());
        }
    }

    public function destroy(Employee $employee)
    {
        $this->checkPermission('employees.delete');
        
        if ($employee->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        $result = $this->employeeService->deleteEmployee($employee);
        
        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }
        
        return redirect()->route('hrm.employees.index')
            ->with('success', 'Empleado eliminado exitosamente.');
    }

    public function deactivate(Request $request, Employee $employee)
    {
        $this->checkPermission('employees.deactivate');
        
        if ($employee->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        $request->validate([
            'reason' => 'required|string|max:500',
            'last_working_date' => 'required|date|after_or_equal:today'
        ]);
        
        $result = $this->employeeService->deactivateEmployee(
            $employee,
            $request->get('reason'),
            $request->get('last_working_date')
        );
        
        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }
        
        return back()->with('success', 'Empleado desactivado exitosamente.');
    }

    public function reactivate(Employee $employee)
    {
        $this->checkPermission('employees.reactivate');
        
        if ($employee->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        $result = $this->employeeService->reactivateEmployee($employee);
        
        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }
        
        return back()->with('success', 'Empleado reactivado exitosamente.');
    }

    public function documents(Employee $employee)
    {
        $this->checkPermission('employees.documents');
        
        if ($employee->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        $documents = $this->employeeService->getEmployeeDocuments($employee);
        
        return Inertia::render('HRM/Employees/Documents', [
            'employee' => $employee,
            'documents' => $documents,
            'requiredDocuments' => config('hrm.documents.required_at_hiring')
        ]);
    }

    public function uploadDocument(Request $request, Employee $employee)
    {
        $this->checkPermission('employees.documents');
        
        if ($employee->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        $request->validate([
            'document_type' => 'required|string',
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx',
            'expiry_date' => 'nullable|date',
            'notes' => 'nullable|string|max:500'
        ]);
        
        $document = $this->employeeService->uploadDocument(
            $employee,
            $request->file('file'),
            $request->only(['document_type', 'expiry_date', 'notes'])
        );
        
        return back()->with('success', 'Documento cargado exitosamente.');
    }

    public function contracts(Employee $employee)
    {
        $this->checkPermission('employees.contracts');
        
        if ($employee->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        $contracts = $this->employeeService->getEmployeeContracts($employee);
        
        return Inertia::render('HRM/Employees/Contracts', [
            'employee' => $employee,
            'contracts' => $contracts,
            'contractTemplates' => config('hrm.documents.contract_templates')
        ]);
    }

    public function createContract(Request $request, Employee $employee)
    {
        $this->checkPermission('employees.contracts');
        
        if ($employee->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        $request->validate([
            'contract_type' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'base_salary' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'payment_frequency' => 'required|string',
            'work_hours_per_week' => 'required|numeric|min:1|max:48',
            'vacation_days' => 'required|integer|min:0',
            'probation_days' => 'nullable|integer|min:0',
            'notes' => 'nullable|string'
        ]);
        
        $contract = $this->employeeService->createContract($employee, $request->all());
        
        return back()->with('success', 'Contrato creado exitosamente.');
    }

    public function emergencyContacts(Employee $employee)
    {
        $this->checkPermission('employees.emergency_contacts');
        
        if ($employee->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        return response()->json([
            'success' => true,
            'emergency_contact' => [
                'name' => $employee->emergency_contact_name,
                'phone' => $employee->emergency_contact_phone,
                'relationship' => $employee->emergency_contact_relationship
            ]
        ]);
    }

    public function updateEmergencyContact(Request $request, Employee $employee)
    {
        $this->checkPermission('employees.emergency_contacts');
        
        if ($employee->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        $request->validate([
            'emergency_contact_name' => 'required|string|max:255',
            'emergency_contact_phone' => 'required|string|max:50',
            'emergency_contact_relationship' => 'required|string|max:100'
        ]);
        
        $employee->update($request->only([
            'emergency_contact_name',
            'emergency_contact_phone',
            'emergency_contact_relationship'
        ]));
        
        return response()->json([
            'success' => true,
            'message' => 'Contacto de emergencia actualizado exitosamente.'
        ]);
    }

    public function bankAccount(Employee $employee)
    {
        $this->checkPermission('employees.bank_accounts');
        
        if ($employee->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        return response()->json([
            'success' => true,
            'bank_account' => [
                'bank_name' => $employee->bank_name,
                'account_number' => $employee->bank_account_number,
                'account_type' => $employee->bank_account_type
            ]
        ]);
    }

    public function updateBankAccount(Request $request, Employee $employee)
    {
        $this->checkPermission('employees.bank_accounts');
        
        if ($employee->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        $request->validate([
            'bank_name' => 'required|string|max:255',
            'bank_account_number' => 'required|string|max:50',
            'bank_account_type' => 'required|in:savings,checking,vista'
        ]);
        
        $employee->update($request->only([
            'bank_name',
            'bank_account_number',
            'bank_account_type'
        ]));
        
        return response()->json([
            'success' => true,
            'message' => 'Información bancaria actualizada exitosamente.'
        ]);
    }

    public function organizationChart()
    {
        $this->checkPermission('employees.view');
        
        $chart = $this->employeeService->getOrganizationChart();
        
        return Inertia::render('HRM/Employees/OrganizationChart', [
            'chart' => $chart
        ]);
    }

    public function export(Request $request)
    {
        $this->checkPermission('employees.view');
        
        $filters = $request->only(['search', 'department_id', 'status', 'contract_type']);
        
        return $this->employeeService->exportEmployees($filters);
    }

    public function importTemplate()
    {
        $this->checkPermission('employees.create');
        
        return $this->employeeService->downloadImportTemplate();
    }

    public function import(Request $request)
    {
        $this->checkPermission('employees.create');
        
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'update_existing' => 'boolean'
        ]);
        
        $result = $this->employeeService->importEmployees(
            $request->file('file'),
            $request->boolean('update_existing', false)
        );
        
        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }
        
        return back()->with('success', $result['message']);
    }
}