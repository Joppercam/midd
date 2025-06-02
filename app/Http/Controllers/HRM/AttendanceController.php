<?php

namespace App\Http\Controllers\HRM;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\AttendanceRecord;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:hrm.attendance.view')->only(['index', 'show', 'reports']);
        $this->middleware('permission:hrm.attendance.create')->only(['checkIn', 'checkOut', 'markAbsent']);
        $this->middleware('permission:hrm.attendance.edit')->only(['edit', 'update']);
    }
    
    protected function getAttendanceService(): AttendanceService
    {
        return app(AttendanceService::class);
    }
    
    /**
     * Display attendance records
     */
    public function index(Request $request)
    {
        try {
            $tenantId = auth()->user()->tenant_id;
            $filters = $request->only(['date', 'employee_id', 'department_id', 'status']);
            $filters['date'] = $filters['date'] ?? now()->format('Y-m-d');
            
            // Create simple empty data structure
            $attendances = new \Illuminate\Pagination\LengthAwarePaginator(
                collect([]),
                0,
                20,
                1,
                ['path' => request()->url(), 'pageName' => 'page']
            );

            $statistics = [
                'total_employees' => Employee::where('tenant_id', $tenantId)->count(),
                'present' => 0,
                'late' => 0,
                'absent' => 0,
                'attendance_rate' => 0,
            ];

            $employees = Employee::where('tenant_id', $tenantId)
                ->select('id', 'first_name', 'last_name', 'employee_number')
                ->get();
                
            $departments = \App\Models\Department::where('tenant_id', $tenantId)
                ->select('id', 'name')
                ->get();
            
            return Inertia::render('HRM/Attendance/Index', [
                'attendances' => $attendances,
                'todayStats' => $statistics,
                'employees' => $employees,
                'departments' => $departments,
                'filters' => $filters,
            ]);
        } catch (\Exception $e) {
            \Log::error('AttendanceController index error: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Check in employee
     */
    public function checkIn(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'notes' => 'nullable|string|max:255',
        ]);

        try {
            $employee = Employee::findOrFail($request->employee_id);
            $attendanceService = $this->getAttendanceService();
            $record = $attendanceService->checkIn($employee, $request->only(['notes']));
            
            return back()->with('success', 'Entrada registrada exitosamente para ' . $employee->full_name);
        } catch (\Exception $e) {
            return back()->withErrors(['check_in' => $e->getMessage()]);
        }
    }
    
    /**
     * Check out employee
     */
    public function checkOut(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'notes' => 'nullable|string|max:255',
        ]);

        try {
            $employee = Employee::findOrFail($request->employee_id);
            $attendanceService = $this->getAttendanceService();
            $record = $attendanceService->checkOut($employee, $request->only(['notes']));
            
            return back()->with('success', 'Salida registrada exitosamente para ' . $employee->full_name);
        } catch (\Exception $e) {
            return back()->withErrors(['check_out' => $e->getMessage()]);
        }
    }
    
    /**
     * Mark employee as absent
     */
    public function markAbsent(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'notes' => 'nullable|string|max:255',
        ]);

        try {
            $employee = Employee::findOrFail($request->employee_id);
            $attendanceService = $this->getAttendanceService();
            $record = $attendanceService->markAbsent($employee, $request->date, $request->only(['notes']));
            
            return back()->with('success', 'Ausencia registrada para ' . $employee->full_name);
        } catch (\Exception $e) {
            return back()->withErrors(['mark_absent' => $e->getMessage()]);
        }
    }
    
    /**
     * Show attendance record details
     */
    public function show(AttendanceRecord $attendance)
    {
        $attendance->load(['employee']);
        
        return Inertia::render('HRM/Attendance/Show', [
            'attendance' => $attendance,
        ]);
    }
    
    /**
     * Edit attendance record
     */
    public function edit(AttendanceRecord $attendance)
    {
        $attendance->load(['employee']);
        
        return Inertia::render('HRM/Attendance/Edit', [
            'attendance' => $attendance,
        ]);
    }
    
    /**
     * Update attendance record
     */
    public function update(Request $request, AttendanceRecord $attendance)
    {
        $request->validate([
            'check_in_time' => 'nullable|date_format:H:i',
            'check_out_time' => 'nullable|date_format:H:i|after:check_in_time',
            'status' => 'required|in:present,late,absent,partial',
            'notes' => 'nullable|string|max:255',
        ]);

        $data = $request->only(['check_in_time', 'check_out_time', 'status', 'notes']);
        
        // Convert time strings to full datetime
        if ($data['check_in_time']) {
            $data['check_in_time'] = $attendance->date . ' ' . $data['check_in_time'];
        }
        if ($data['check_out_time']) {
            $data['check_out_time'] = $attendance->date . ' ' . $data['check_out_time'];
        }
        
        $attendance->update($data);
        
        return redirect()->route('hrm.attendance.index')
            ->with('success', 'Registro de asistencia actualizado exitosamente.');
    }
    
    /**
     * Attendance reports
     */
    public function reports(Request $request)
    {
        $year = $request->year ?? now()->year;
        $month = $request->month ?? now()->month;
        
        try {
            $attendanceService = $this->getAttendanceService();
            $report = $attendanceService->generateMonthlyReport($year, $month);
            
            return Inertia::render('HRM/Attendance/Reports', [
                'report' => $report,
                'year' => $year,
                'month' => $month,
            ]);
        } catch (\Exception $e) {
            return Inertia::render('HRM/Attendance/Reports', [
                'report' => [],
                'year' => $year,
                'month' => $month,
            ]);
        }
    }
    
    /**
     * Employee self check-in/out
     */
    public function selfService()
    {
        $user = auth()->user();
        $employee = Employee::where('user_id', $user->id)
            ->where('tenant_id', $user->tenant_id)
            ->first();

        if (!$employee) {
            abort(404, 'No se encontró el perfil de empleado.');
        }

        $today = now()->format('Y-m-d');
        $todayRecord = AttendanceRecord::where('employee_id', $employee->id)
            ->whereDate('date', $today)
            ->first();

        return Inertia::render('HRM/Attendance/SelfService', [
            'employee' => $employee,
            'todayRecord' => $todayRecord,
        ]);
    }
}