<?php

namespace App\Modules\HRM\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HRM\Models\Attendance;
use App\Modules\HRM\Models\Employee;
use App\Modules\HRM\Models\EmploymentContract;
use App\Modules\HRM\Models\LeaveRequest;
use App\Modules\HRM\Models\Payroll;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HRMDashboardController extends Controller
{
    public function index(Request $request)
    {
        $tenant = auth()->user()->tenant;
        $today = Carbon::today();
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        // Estadísticas generales
        $stats = [
            'total_employees' => Employee::where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->count(),
            
            'new_employees_month' => EmploymentContract::where('tenant_id', $tenant->id)
                ->whereMonth('start_date', $currentMonth)
                ->whereYear('start_date', $currentYear)
                ->where('status', 'active')
                ->count(),
            
            'contracts_expiring' => EmploymentContract::where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->where('type', 'fixed_term')
                ->whereBetween('end_date', [$today, $today->copy()->addDays(30)])
                ->count(),
            
            'employees_on_leave' => LeaveRequest::where('tenant_id', $tenant->id)
                ->where('status', 'approved')
                ->where('start_date', '<=', $today)
                ->where('end_date', '>=', $today)
                ->count(),
        ];

        // Asistencia de hoy
        $attendanceToday = [
            'present' => Attendance::where('tenant_id', $tenant->id)
                ->whereDate('date', $today)
                ->where('status', 'present')
                ->count(),
            
            'absent' => Employee::where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->whereDoesntHave('attendances', function ($query) use ($today) {
                    $query->whereDate('date', $today);
                })
                ->count(),
            
            'late' => Attendance::where('tenant_id', $tenant->id)
                ->whereDate('date', $today)
                ->where('status', 'late')
                ->count(),
        ];

        // Nómina actual
        $currentPayroll = Payroll::where('tenant_id', $tenant->id)
            ->where('month', $currentMonth)
            ->where('year', $currentYear)
            ->first();

        // Solicitudes pendientes
        $pendingRequests = [
            'leave_requests' => LeaveRequest::where('tenant_id', $tenant->id)
                ->where('status', 'pending')
                ->count(),
            
            'documents_expiring' => \App\Modules\HRM\Models\EmployeeDocument::where('tenant_id', $tenant->id)
                ->whereNotNull('expiry_date')
                ->whereBetween('expiry_date', [$today, $today->copy()->addDays(30)])
                ->count(),
        ];

        // Empleados por departamento
        $employeesByDepartment = \App\Modules\HRM\Models\Department::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->withCount(['employees'])
            ->get()
            ->map(function ($dept) {
                return [
                    'name' => $dept->name,
                    'count' => $dept->employees_count
                ];
            });

        // Últimas contrataciones
        $recentHires = Employee::where('tenant_id', $tenant->id)
            ->with(['currentContract.position', 'currentContract.department'])
            ->whereHas('currentContract', function ($query) {
                $query->where('start_date', '>=', Carbon::now()->subDays(30));
            })
            ->latest()
            ->take(5)
            ->get();

        // Cumpleaños del mes
        $birthdays = Employee::where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->whereMonth('birth_date', $currentMonth)
            ->orderByRaw('DAY(birth_date)')
            ->get()
            ->map(function ($employee) {
                return [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                    'date' => $employee->birth_date->format('d/m'),
                    'age' => $employee->age + 1 // Edad que cumplirá
                ];
            });

        return Inertia::render('HRM/Dashboard', [
            'stats' => $stats,
            'attendanceToday' => $attendanceToday,
            'currentPayroll' => $currentPayroll,
            'pendingRequests' => $pendingRequests,
            'employeesByDepartment' => $employeesByDepartment,
            'recentHires' => $recentHires,
            'birthdays' => $birthdays,
        ]);
    }
}