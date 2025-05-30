<?php

namespace App\Modules\HRM\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HRM\Models\Employee;
use App\Modules\HRM\Models\Attendance;
use App\Traits\ChecksPermissions;
use App\Modules\HRM\Services\AttendanceService;
use App\Modules\HRM\Requests\AttendanceRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    use ChecksPermissions;
    
    protected $attendanceService;
    
    public function __construct(AttendanceService $attendanceService)
    {
        $this->middleware(['auth', 'verified', 'check.subscription', 'check.module:hrm']);
        $this->attendanceService = $attendanceService;
    }
    
    public function index(Request $request)
    {
        $this->checkPermission('attendance.view');
        
        $filters = $request->only(['date', 'employee_id', 'department_id', 'status']);
        $attendances = $this->attendanceService->getDailyAttendance($filters);
        $statistics = $this->attendanceService->getAttendanceStatistics($filters['date'] ?? now()->format('Y-m-d'));
        
        return Inertia::render('HRM/Attendance/Index', [
            'attendances' => $attendances,
            'statistics' => $statistics,
            'filters' => $filters,
            'employees' => $this->attendanceService->getActiveEmployees(),
            'departments' => $this->attendanceService->getDepartments(),
        ]);
    }
    
    public function checkIn(Request $request)
    {
        $this->checkPermission('attendance.check_in');
        
        $employee = $this->getEmployeeForAttendance($request);
        
        $result = $this->attendanceService->checkIn($employee, [
            'location' => $request->get('location'),
            'notes' => $request->get('notes'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        
        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }
        
        return back()->with('success', 'Check-in registrado exitosamente.');
    }
    
    public function checkOut(Request $request)
    {
        $this->checkPermission('attendance.check_out');
        
        $employee = $this->getEmployeeForAttendance($request);
        
        $result = $this->attendanceService->checkOut($employee, [
            'location' => $request->get('location'),
            'notes' => $request->get('notes'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        
        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }
        
        return back()->with('success', 'Check-out registrado exitosamente.');
    }
    
    public function edit(Attendance $attendance)
    {
        $this->checkPermission('attendance.edit');
        
        if ($attendance->employee->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        return Inertia::render('HRM/Attendance/Edit', [
            'attendance' => $attendance->load('employee'),
            'shiftTypes' => config('hrm.attendance.shift_types'),
        ]);
    }
    
    public function update(AttendanceRequest $request, Attendance $attendance)
    {
        $this->checkPermission('attendance.edit');
        
        if ($attendance->employee->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        $attendance = $this->attendanceService->updateAttendance($attendance, $request->validated());
        
        return redirect()->route('hrm.attendance.index')
            ->with('success', 'Asistencia actualizada exitosamente.');
    }
    
    public function approve(Request $request, Attendance $attendance)
    {
        $this->checkPermission('attendance.approve');
        
        if ($attendance->employee->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        $request->validate([
            'notes' => 'nullable|string|max:500'
        ]);
        
        $result = $this->attendanceService->approveAttendance(
            $attendance,
            $request->get('notes')
        );
        
        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }
        
        return back()->with('success', 'Asistencia aprobada exitosamente.');
    }
    
    public function monthlyReport(Request $request)
    {
        $this->checkPermission('attendance.reports');
        
        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);
        $employeeId = $request->get('employee_id');
        $departmentId = $request->get('department_id');
        
        $report = $this->attendanceService->generateMonthlyReport(
            $year,
            $month,
            $employeeId,
            $departmentId
        );
        
        return Inertia::render('HRM/Attendance/MonthlyReport', [
            'report' => $report,
            'filters' => [
                'year' => $year,
                'month' => $month,
                'employee_id' => $employeeId,
                'department_id' => $departmentId,
            ],
            'employees' => $this->attendanceService->getActiveEmployees(),
            'departments' => $this->attendanceService->getDepartments(),
        ]);
    }
    
    public function calendar(Request $request)
    {
        $this->checkPermission('attendance.view');
        
        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);
        $employeeId = $request->get('employee_id');
        
        $calendar = $this->attendanceService->getAttendanceCalendar(
            $year,
            $month,
            $employeeId
        );
        
        return Inertia::render('HRM/Attendance/Calendar', [
            'calendar' => $calendar,
            'filters' => [
                'year' => $year,
                'month' => $month,
                'employee_id' => $employeeId,
            ],
            'employees' => $this->attendanceService->getActiveEmployees(),
        ]);
    }
    
    public function bulkImport()
    {
        $this->checkPermission('attendance.bulk_import');
        
        return Inertia::render('HRM/Attendance/BulkImport', [
            'importTemplate' => route('hrm.attendance.import-template'),
        ]);
    }
    
    public function importTemplate()
    {
        $this->checkPermission('attendance.bulk_import');
        
        return $this->attendanceService->downloadImportTemplate();
    }
    
    public function import(Request $request)
    {
        $this->checkPermission('attendance.bulk_import');
        
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'override_existing' => 'boolean'
        ]);
        
        $result = $this->attendanceService->importAttendance(
            $request->file('file'),
            $request->boolean('override_existing', false)
        );
        
        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }
        
        return back()->with('success', $result['message']);
    }
    
    public function overtime(Request $request)
    {
        $this->checkPermission('attendance.overtime');
        
        $filters = $request->only(['date_from', 'date_to', 'employee_id', 'department_id']);
        $overtimeReport = $this->attendanceService->getOvertimeReport($filters);
        
        return Inertia::render('HRM/Attendance/Overtime', [
            'report' => $overtimeReport,
            'filters' => $filters,
            'employees' => $this->attendanceService->getActiveEmployees(),
            'departments' => $this->attendanceService->getDepartments(),
            'overtimeRates' => config('hrm.attendance.overtime_rates'),
        ]);
    }
    
    public function lateReport(Request $request)
    {
        $this->checkPermission('attendance.reports');
        
        $filters = $request->only(['date_from', 'date_to', 'employee_id', 'department_id']);
        $lateReport = $this->attendanceService->getLateReport($filters);
        
        return Inertia::render('HRM/Attendance/LateReport', [
            'report' => $lateReport,
            'filters' => $filters,
            'employees' => $this->attendanceService->getActiveEmployees(),
            'departments' => $this->attendanceService->getDepartments(),
        ]);
    }
    
    public function export(Request $request)
    {
        $this->checkPermission('attendance.reports');
        
        $filters = $request->only(['date_from', 'date_to', 'employee_id', 'department_id']);
        
        return $this->attendanceService->exportAttendance($filters);
    }
    
    public function summary(Employee $employee)
    {
        $this->checkPermission('attendance.view');
        
        if ($employee->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        $year = request()->get('year', now()->year);
        $summary = $this->attendanceService->getEmployeeAttendanceSummary($employee, $year);
        
        return response()->json([
            'success' => true,
            'summary' => $summary
        ]);
    }
    
    public function todayStatus()
    {
        $this->checkPermission('attendance.view');
        
        $status = $this->attendanceService->getTodayAttendanceStatus();
        
        return response()->json([
            'success' => true,
            'status' => $status
        ]);
    }
    
    private function getEmployeeForAttendance(Request $request): Employee
    {
        // If employee_id is provided (for managers checking in employees)
        if ($request->has('employee_id')) {
            $this->checkPermission('attendance.edit');
            $employee = Employee::where('tenant_id', auth()->user()->tenant_id)
                ->findOrFail($request->get('employee_id'));
        } else {
            // Self check-in/out
            $employee = Employee::where('tenant_id', auth()->user()->tenant_id)
                ->where('user_id', auth()->id())
                ->firstOrFail();
        }
        
        return $employee;
    }
}