<?php

namespace App\Http\Controllers\HRM;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Department;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class LeaveController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:hrm.leaves.view')->only(['index', 'show']);
        $this->middleware('permission:hrm.leaves.create')->only(['create', 'store', 'request']);
        $this->middleware('permission:hrm.leaves.edit')->only(['edit', 'update', 'approve', 'reject']);
    }
    
    /**
     * Display leave requests
     */
    public function index(Request $request)
    {
        try {
            $tenantId = auth()->user()->tenant_id;
            $filters = $request->only(['status', 'employee_id', 'department_id', 'type', 'date_from', 'date_to']);
            
            $query = LeaveRequest::with(['employee'])
                ->whereHas('employee', function($q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId);
                });

            // Apply filters
            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['employee_id'])) {
                $query->where('employee_id', $filters['employee_id']);
            }

            if (!empty($filters['department_id'])) {
                $query->whereHas('employee.currentContract', function($q) use ($filters) {
                    $q->where('department_id', $filters['department_id']);
                });
            }

            if (!empty($filters['type'])) {
                $query->where('type', $filters['type']);
            }

            if (!empty($filters['date_from'])) {
                $query->where('start_date', '>=', $filters['date_from']);
            }

            if (!empty($filters['date_to'])) {
                $query->where('end_date', '<=', $filters['date_to']);
            }

            $leaveRequests = $query->latest()->paginate(20);

            // Calculate statistics
            $statistics = [
                'total_requests' => LeaveRequest::whereHas('employee', function($q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId);
                })->count(),
                'pending_requests' => LeaveRequest::whereHas('employee', function($q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId);
                })->where('status', 'pending')->count(),
                'approved_requests' => LeaveRequest::whereHas('employee', function($q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId);
                })->where('status', 'approved')->count(),
                'rejected_requests' => LeaveRequest::whereHas('employee', function($q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId);
                })->where('status', 'rejected')->count(),
            ];

            $employees = Employee::where('tenant_id', $tenantId)
                ->select('id', 'first_name', 'last_name', 'employee_number')
                ->get();
                
            $departments = Department::where('tenant_id', $tenantId)
                ->select('id', 'name')
                ->get();
            
            return Inertia::render('HRM/Leaves/Index', [
                'leaveRequests' => $leaveRequests,
                'statistics' => $statistics,
                'employees' => $employees,
                'departments' => $departments,
                'filters' => $filters,
                'leaveTypes' => $this->getLeaveTypes(),
            ]);
        } catch (\Exception $e) {
            \Log::error('LeaveController index error: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Show create leave request form
     */
    public function create()
    {
        $tenantId = auth()->user()->tenant_id;
        
        $employees = Employee::where('tenant_id', $tenantId)
            ->active()
            ->select('id', 'first_name', 'last_name', 'employee_number')
            ->get();
        
        return Inertia::render('HRM/Leaves/Create', [
            'employees' => $employees,
            'leaveTypes' => $this->getLeaveTypes(),
        ]);
    }
    
    /**
     * Store new leave request
     */
    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'type' => 'required|in:vacation,sick,personal,maternity,paternity,other',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:500',
            'notes' => 'nullable|string|max:255',
        ]);

        try {
            $employee = Employee::findOrFail($request->employee_id);
            
            // Calculate days requested
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);
            $daysRequested = $startDate->diffInWeekdays($endDate) + 1;

            $leaveRequest = LeaveRequest::create([
                'employee_id' => $request->employee_id,
                'type' => $request->type,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'days_requested' => $daysRequested,
                'reason' => $request->reason,
                'notes' => $request->notes,
                'status' => 'pending',
                'requested_by' => auth()->id(),
                'requested_at' => now(),
            ]);
            
            return redirect()->route('hrm.leaves.index')
                ->with('success', 'Solicitud de vacaciones creada exitosamente para ' . $employee->full_name);
        } catch (\Exception $e) {
            return back()->withErrors(['submit' => $e->getMessage()]);
        }
    }
    
    /**
     * Show leave request details
     */
    public function show(LeaveRequest $leave)
    {
        $leave->load(['employee', 'requestedBy', 'approvedBy']);
        
        return Inertia::render('HRM/Leaves/Show', [
            'leaveRequest' => $leave,
        ]);
    }
    
    /**
     * Approve leave request
     */
    public function approve(Request $request, LeaveRequest $leave)
    {
        $request->validate([
            'notes' => 'nullable|string|max:255',
        ]);

        if ($leave->status !== 'pending') {
            return back()->withErrors(['approve' => 'Solo se pueden aprobar solicitudes pendientes.']);
        }

        try {
            $leave->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'admin_notes' => $request->notes,
            ]);
            
            return back()->with('success', 'Solicitud de vacaciones aprobada exitosamente.');
        } catch (\Exception $e) {
            return back()->withErrors(['approve' => $e->getMessage()]);
        }
    }
    
    /**
     * Reject leave request
     */
    public function reject(Request $request, LeaveRequest $leave)
    {
        $request->validate([
            'notes' => 'required|string|max:255',
        ]);

        if ($leave->status !== 'pending') {
            return back()->withErrors(['reject' => 'Solo se pueden rechazar solicitudes pendientes.']);
        }

        try {
            $leave->update([
                'status' => 'rejected',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'admin_notes' => $request->notes,
            ]);
            
            return back()->with('success', 'Solicitud de vacaciones rechazada.');
        } catch (\Exception $e) {
            return back()->withErrors(['reject' => $e->getMessage()]);
        }
    }
    
    /**
     * Cancel leave request
     */
    public function cancel(LeaveRequest $leave)
    {
        if ($leave->status === 'approved' && $leave->start_date <= now()) {
            return back()->withErrors(['cancel' => 'No se puede cancelar una vacación que ya comenzó.']);
        }

        try {
            $leave->update([
                'status' => 'cancelled',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'admin_notes' => 'Cancelado por el usuario',
            ]);
            
            return back()->with('success', 'Solicitud de vacaciones cancelada exitosamente.');
        } catch (\Exception $e) {
            return back()->withErrors(['cancel' => $e->getMessage()]);
        }
    }
    
    /**
     * Employee leave balance
     */
    public function balance(Employee $employee = null)
    {
        $user = auth()->user();
        
        if (!$employee) {
            $employee = Employee::where('user_id', $user->id)
                ->where('tenant_id', $user->tenant_id)
                ->first();
        }

        if (!$employee) {
            abort(404, 'No se encontró el perfil de empleado.');
        }

        $currentYear = now()->year;
        $balance = $this->calculateLeaveBalance($employee, $currentYear);
        
        return Inertia::render('HRM/Leaves/Balance', [
            'employee' => $employee,
            'balance' => $balance,
            'year' => $currentYear,
        ]);
    }
    
    /**
     * Leave calendar view
     */
    public function calendar(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $year = $request->year ?? now()->year;
        $month = $request->month ?? now()->month;
        
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        
        $leaves = LeaveRequest::with(['employee'])
            ->whereHas('employee', function($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
            })
            ->where('status', 'approved')
            ->where(function($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                  ->orWhereBetween('end_date', [$startDate, $endDate])
                  ->orWhere(function($query) use ($startDate, $endDate) {
                      $query->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                  });
            })
            ->get();
        
        return Inertia::render('HRM/Leaves/Calendar', [
            'leaves' => $leaves,
            'year' => $year,
            'month' => $month,
        ]);
    }
    
    /**
     * Get available leave types
     */
    protected function getLeaveTypes(): array
    {
        return [
            'vacation' => 'Vacaciones',
            'sick' => 'Permiso Médico',
            'personal' => 'Permiso Personal',
            'maternity' => 'Permiso Maternal',
            'paternity' => 'Permiso Paternal',
            'other' => 'Otro',
        ];
    }
    
    /**
     * Calculate leave balance for employee
     */
    protected function calculateLeaveBalance(Employee $employee, int $year): array
    {
        // Chilean law: 15 working days vacation per year + 1 extra day per year of service
        $contract = $employee->currentContract;
        if (!$contract) {
            return [
                'vacation_entitled' => 0,
                'vacation_used' => 0,
                'vacation_remaining' => 0,
                'sick_used' => 0,
                'personal_used' => 0,
            ];
        }
        
        $yearsSinceStart = now()->diffInYears($contract->start_date);
        $vacationEntitled = 15 + ($yearsSinceStart > 0 ? $yearsSinceStart : 0);
        
        $vacationUsed = LeaveRequest::where('employee_id', $employee->id)
            ->where('type', 'vacation')
            ->where('status', 'approved')
            ->whereYear('start_date', $year)
            ->sum('days_requested');
            
        $sickUsed = LeaveRequest::where('employee_id', $employee->id)
            ->where('type', 'sick')
            ->where('status', 'approved')
            ->whereYear('start_date', $year)
            ->sum('days_requested');
            
        $personalUsed = LeaveRequest::where('employee_id', $employee->id)
            ->where('type', 'personal')
            ->where('status', 'approved')
            ->whereYear('start_date', $year)
            ->sum('days_requested');
        
        return [
            'vacation_entitled' => $vacationEntitled,
            'vacation_used' => $vacationUsed,
            'vacation_remaining' => max(0, $vacationEntitled - $vacationUsed),
            'sick_used' => $sickUsed,
            'personal_used' => $personalUsed,
        ];
    }
}