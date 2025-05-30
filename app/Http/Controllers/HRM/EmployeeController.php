<?php

namespace App\Http\Controllers\HRM;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Department;
use App\Models\Position;
use App\Models\EmploymentContract;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class EmployeeController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'tenant']);
        $this->middleware('permission:hrm.employees.view')->only(['index', 'show']);
        $this->middleware('permission:hrm.employees.create')->only(['create', 'store']);
        $this->middleware('permission:hrm.employees.edit')->only(['edit', 'update']);
        $this->middleware('permission:hrm.employees.delete')->only(['destroy']);
    }

    /**
     * Display employees list
     */
    public function index(Request $request)
    {
        $query = Employee::with(['currentContract.department', 'currentContract.position'])
            ->where('tenant_id', tenant()->id);

        // Apply filters
        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->department_id) {
            $query->whereHas('currentContract', function($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('employee_number', 'like', "%{$search}%")
                  ->orWhere('rut', 'like', "%{$search}%");
            });
        }

        $employees = $query->latest()->paginate(20);
        $departments = Department::where('tenant_id', tenant()->id)->active()->get();

        return Inertia::render('HRM/Employees/Index', [
            'employees' => $employees,
            'departments' => $departments,
            'filters' => $request->only(['status', 'department_id', 'search']),
        ]);
    }

    /**
     * Show employee details
     */
    public function show(Employee $employee)
    {
        $employee->load([
            'currentContract.department',
            'currentContract.position',
            'contracts.department',
            'contracts.position',
            'payslips' => function($query) {
                $query->latest()->limit(12);
            },
            'leaveRequests' => function($query) {
                $query->latest()->limit(10);
            },
            'attendanceRecords' => function($query) {
                $query->latest()->limit(30);
            }
        ]);

        return Inertia::render('HRM/Employees/Show', [
            'employee' => $employee,
        ]);
    }

    /**
     * Show create employee form
     */
    public function create()
    {
        $departments = Department::where('tenant_id', tenant()->id)->active()->get();
        $positions = Position::where('tenant_id', tenant()->id)->active()->get();

        return Inertia::render('HRM/Employees/Create', [
            'departments' => $departments,
            'positions' => $positions,
        ]);
    }

    /**
     * Store new employee
     */
    public function store(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'rut' => 'required|string|unique:employees,rut',
            'email' => 'nullable|email|unique:employees,email',
            'phone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'marital_status' => 'nullable|in:single,married,divorced,widowed',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'photo' => 'nullable|image|max:2048',
            
            // Contract information
            'department_id' => 'required|exists:departments,id',
            'position_id' => 'required|exists:positions,id',
            'contract_type' => 'required|in:indefinite,fixed_term,part_time,temporary,internship',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'base_salary' => 'required|numeric|min:0',
            'work_hours_per_week' => 'required|integer|min:1|max:45',
        ]);

        $employeeData = $request->only([
            'first_name', 'last_name', 'rut', 'email', 'phone', 'mobile',
            'address', 'birth_date', 'gender', 'marital_status',
            'emergency_contact_name', 'emergency_contact_phone'
        ]);

        $employeeData['tenant_id'] = tenant()->id;
        $employeeData['status'] = Employee::STATUS_ACTIVE;

        // Handle photo upload
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('employees/photos', 'public');
            $employeeData['photo_path'] = $path;
        }

        $employee = Employee::create($employeeData);

        // Create employment contract
        $contractData = $request->only([
            'department_id', 'position_id', 'contract_type',
            'start_date', 'end_date', 'base_salary', 'work_hours_per_week'
        ]);

        $contractData['tenant_id'] = tenant()->id;
        $contractData['employee_id'] = $employee->id;
        $contractData['status'] = EmploymentContract::STATUS_ACTIVE;

        EmploymentContract::create($contractData);

        return redirect()->route('hrm.employees.show', $employee)
            ->with('success', 'Empleado creado exitosamente.');
    }

    /**
     * Show edit employee form
     */
    public function edit(Employee $employee)
    {
        $employee->load('currentContract');
        $departments = Department::where('tenant_id', tenant()->id)->active()->get();
        $positions = Position::where('tenant_id', tenant()->id)->active()->get();

        return Inertia::render('HRM/Employees/Edit', [
            'employee' => $employee,
            'departments' => $departments,
            'positions' => $positions,
        ]);
    }

    /**
     * Update employee
     */
    public function update(Request $request, Employee $employee)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'rut' => 'required|string|unique:employees,rut,' . $employee->id,
            'email' => 'nullable|email|unique:employees,email,' . $employee->id,
            'phone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'marital_status' => 'nullable|in:single,married,divorced,widowed',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'photo' => 'nullable|image|max:2048',
            'status' => 'required|in:active,inactive,terminated',
        ]);

        $employeeData = $request->only([
            'first_name', 'last_name', 'rut', 'email', 'phone', 'mobile',
            'address', 'birth_date', 'gender', 'marital_status',
            'emergency_contact_name', 'emergency_contact_phone', 'status'
        ]);

        // Handle photo upload
        if ($request->hasFile('photo')) {
            // Delete old photo
            if ($employee->photo_path) {
                Storage::disk('public')->delete($employee->photo_path);
            }
            
            $path = $request->file('photo')->store('employees/photos', 'public');
            $employeeData['photo_path'] = $path;
        }

        $employee->update($employeeData);

        return redirect()->route('hrm.employees.show', $employee)
            ->with('success', 'Empleado actualizado exitosamente.');
    }

    /**
     * Delete employee
     */
    public function destroy(Employee $employee)
    {
        // Check if employee has active contracts
        if ($employee->contracts()->where('status', EmploymentContract::STATUS_ACTIVE)->exists()) {
            return back()->withErrors(['delete' => 'No se puede eliminar un empleado con contratos activos.']);
        }

        // Delete photo if exists
        if ($employee->photo_path) {
            Storage::disk('public')->delete($employee->photo_path);
        }

        $employee->delete();

        return redirect()->route('hrm.employees.index')
            ->with('success', 'Empleado eliminado exitosamente.');
    }

    /**
     * Employee dashboard/profile
     */
    public function profile()
    {
        $user = auth()->user();
        $employee = Employee::where('user_id', $user->id)
            ->where('tenant_id', tenant()->id)
            ->with([
                'currentContract.department',
                'currentContract.position',
                'payslips' => function($query) {
                    $query->latest()->limit(6);
                },
                'leaveRequests' => function($query) {
                    $query->latest()->limit(5);
                }
            ])
            ->first();

        if (!$employee) {
            abort(404, 'No se encontró el perfil de empleado.');
        }

        return Inertia::render('HRM/Employee/Profile', [
            'employee' => $employee,
        ]);
    }

    /**
     * Get employee statistics
     */
    public function statistics()
    {
        $tenant = tenant();
        
        $stats = [
            'total_employees' => Employee::where('tenant_id', $tenant->id)->count(),
            'active_employees' => Employee::where('tenant_id', $tenant->id)->active()->count(),
            'terminated_employees' => Employee::where('tenant_id', $tenant->id)
                ->where('status', Employee::STATUS_TERMINATED)->count(),
            'employees_by_department' => Department::where('tenant_id', $tenant->id)
                ->withCount(['activeContracts as employee_count'])
                ->get()
                ->map(function($dept) {
                    return [
                        'name' => $dept->name,
                        'count' => $dept->employee_count,
                    ];
                }),
            'contracts_expiring' => EmploymentContract::where('tenant_id', $tenant->id)
                ->expiringSoon(30)
                ->count(),
            'average_salary' => EmploymentContract::where('tenant_id', $tenant->id)
                ->active()
                ->avg('base_salary'),
        ];

        return response()->json($stats);
    }
}