<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\AttendanceRecord;
use App\Models\Department;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceService
{
    /**
     * Get daily attendance records
     */
    public function getDailyAttendance(array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        $tenantId = auth()->user()->tenant_id;
        $date = $filters['date'] ?? now()->format('Y-m-d');

        $query = AttendanceRecord::with(['employee'])
            ->whereHas('employee', function($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
            })
            ->whereDate('date', $date);

        if (!empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (!empty($filters['department_id'])) {
            $query->whereHas('employee.currentContract', function($q) use ($filters) {
                $q->where('department_id', $filters['department_id']);
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest('check_in_time')->paginate(20);
    }

    /**
     * Get attendance statistics for a date
     */
    public function getAttendanceStatistics(string $date): array
    {
        $tenantId = auth()->user()->tenant_id;

        $totalEmployees = Employee::where('tenant_id', $tenantId)->active()->count();
        
        $attendanceCount = AttendanceRecord::whereHas('employee', function($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })->whereDate('date', $date)->count();

        $presentCount = AttendanceRecord::whereHas('employee', function($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })->whereDate('date', $date)->where('status', 'present')->count();

        $lateCount = AttendanceRecord::whereHas('employee', function($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })->whereDate('date', $date)->where('status', 'late')->count();

        $absentCount = $totalEmployees - $attendanceCount;

        return [
            'total_employees' => $totalEmployees,
            'present' => $presentCount,
            'late' => $lateCount,
            'absent' => $absentCount,
            'attendance_rate' => $totalEmployees > 0 ? round(($attendanceCount / $totalEmployees) * 100, 1) : 0,
        ];
    }

    /**
     * Get active employees
     */
    public function getActiveEmployees(): Collection
    {
        $tenantId = auth()->user()->tenant_id;
        
        return Employee::where('tenant_id', $tenantId)
            ->active()
            ->select('id', 'first_name', 'last_name', 'employee_number')
            ->get();
    }

    /**
     * Get departments
     */
    public function getDepartments(): Collection
    {
        $tenantId = auth()->user()->tenant_id;
        
        return Department::where('tenant_id', $tenantId)
            ->active()
            ->select('id', 'name')
            ->get();
    }

    /**
     * Check in employee
     */
    public function checkIn(Employee $employee, array $data = []): AttendanceRecord
    {
        $today = now()->format('Y-m-d');
        
        // Check if already checked in today
        $existingRecord = AttendanceRecord::where('employee_id', $employee->id)
            ->whereDate('date', $today)
            ->first();

        if ($existingRecord) {
            throw new \Exception('El empleado ya registró entrada hoy');
        }

        $checkInTime = now();
        $workStartTime = Carbon::parse($today . ' 09:00:00'); // Default work start time
        
        $status = 'present';
        if ($checkInTime->gt($workStartTime->addMinutes(15))) { // 15 minutes tolerance
            $status = 'late';
        }

        return AttendanceRecord::create([
            'employee_id' => $employee->id,
            'date' => $today,
            'check_in_time' => $checkInTime,
            'status' => $status,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Check out employee
     */
    public function checkOut(Employee $employee, array $data = []): AttendanceRecord
    {
        $today = now()->format('Y-m-d');
        
        $record = AttendanceRecord::where('employee_id', $employee->id)
            ->whereDate('date', $today)
            ->whereNull('check_out_time')
            ->first();

        if (!$record) {
            throw new \Exception('No se encontró registro de entrada para hoy');
        }

        $checkOutTime = now();
        $record->update([
            'check_out_time' => $checkOutTime,
            'notes' => $data['notes'] ?? $record->notes,
        ]);

        // Calculate worked hours
        if ($record->check_in_time) {
            $workedMinutes = $record->check_in_time->diffInMinutes($checkOutTime);
            $record->update([
                'regular_hours' => round($workedMinutes / 60, 2),
            ]);
        }

        return $record;
    }

    /**
     * Mark employee as absent
     */
    public function markAbsent(Employee $employee, string $date, array $data = []): AttendanceRecord
    {
        return AttendanceRecord::create([
            'employee_id' => $employee->id,
            'date' => $date,
            'status' => 'absent',
            'notes' => $data['notes'] ?? 'Marcado como ausente',
        ]);
    }

    /**
     * Get employee attendance for date range
     */
    public function getEmployeeAttendance(Employee $employee, Carbon $startDate, Carbon $endDate): Collection
    {
        return AttendanceRecord::where('employee_id', $employee->id)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->orderBy('date', 'desc')
            ->get();
    }

    /**
     * Generate monthly attendance report
     */
    public function generateMonthlyReport(int $year, int $month): array
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $employees = Employee::where('tenant_id', $tenantId)->active()->get();
        $report = [];

        foreach ($employees as $employee) {
            $attendance = $this->getEmployeeAttendance($employee, $startDate, $endDate);
            
            $report[] = [
                'employee' => $employee,
                'total_days' => $attendance->count(),
                'present_days' => $attendance->where('status', 'present')->count(),
                'late_days' => $attendance->where('status', 'late')->count(),
                'absent_days' => $attendance->where('status', 'absent')->count(),
                'total_hours' => $attendance->sum('regular_hours'),
                'overtime_hours' => $attendance->sum('overtime_hours'),
            ];
        }

        return $report;
    }
}