<?php

namespace App\Modules\HRM\Services;

use App\Modules\HRM\Models\Employee;
use App\Modules\HRM\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function checkIn(Employee $employee, array $data): Attendance
    {
        // Check if already checked in today
        $existingAttendance = $employee->attendances()
            ->whereDate('date', now()->toDateString())
            ->whereNotNull('check_in')
            ->whereNull('check_out')
            ->first();

        if ($existingAttendance) {
            throw new \Exception('Ya has registrado tu entrada hoy.');
        }

        return $employee->attendances()->create([
            'date' => now()->toDateString(),
            'check_in' => now(),
            'check_in_location' => $data['location'] ?? null,
            'check_in_latitude' => $data['latitude'] ?? null,
            'check_in_longitude' => $data['longitude'] ?? null,
            'status' => $this->determineStatus(now()),
        ]);
    }

    public function checkOut(Employee $employee, array $data): Attendance
    {
        $attendance = $employee->attendances()
            ->whereDate('date', now()->toDateString())
            ->whereNotNull('check_in')
            ->whereNull('check_out')
            ->first();

        if (!$attendance) {
            throw new \Exception('No se encontró registro de entrada para hoy.');
        }

        $checkOut = now();
        $totalHours = $attendance->check_in->diffInHours($checkOut);
        $overtimeHours = max(0, $totalHours - 8);

        $attendance->update([
            'check_out' => $checkOut,
            'check_out_location' => $data['location'] ?? null,
            'check_out_latitude' => $data['latitude'] ?? null,
            'check_out_longitude' => $data['longitude'] ?? null,
            'total_hours' => $totalHours,
            'overtime_hours' => $overtimeHours,
        ]);

        return $attendance;
    }

    public function markManual(Employee $employee, array $data): Attendance
    {
        return DB::transaction(function () use ($employee, $data) {
            $checkIn = Carbon::parse($data['date'] . ' ' . $data['check_in']);
            $checkOut = isset($data['check_out']) ? Carbon::parse($data['date'] . ' ' . $data['check_out']) : null;
            
            $totalHours = $checkOut ? $checkIn->diffInHours($checkOut) : 0;
            $overtimeHours = max(0, $totalHours - 8);

            return $employee->attendances()->create([
                'date' => $data['date'],
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'total_hours' => $totalHours,
                'overtime_hours' => $overtimeHours,
                'status' => $data['status'] ?? $this->determineStatus($checkIn),
                'notes' => $data['notes'] ?? null,
                'is_manual' => true,
                'approved_by' => auth()->id(),
            ]);
        });
    }

    public function getMonthlyReport(Employee $employee, int $year, int $month): array
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        $attendances = $employee->attendances()
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get();

        $workDays = $this->getWorkDaysInMonth($year, $month);
        $presentDays = $attendances->where('status', 'present')->count();
        $lateDays = $attendances->where('status', 'late')->count();
        $absentDays = $workDays - $attendances->count();
        $totalHours = $attendances->sum('total_hours');
        $overtimeHours = $attendances->sum('overtime_hours');

        return [
            'employee' => $employee,
            'period' => $startDate->format('F Y'),
            'attendances' => $attendances,
            'summary' => [
                'work_days' => $workDays,
                'present_days' => $presentDays,
                'late_days' => $lateDays,
                'absent_days' => $absentDays,
                'total_hours' => $totalHours,
                'overtime_hours' => $overtimeHours,
                'attendance_rate' => $workDays > 0 ? round(($presentDays / $workDays) * 100, 2) : 0,
            ],
        ];
    }

    public function getTeamReport(int $departmentId = null, int $year = null, int $month = null): array
    {
        $year = $year ?? now()->year;
        $month = $month ?? now()->month;
        
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        $query = Employee::where('status', 'active')
            ->with(['attendances' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('date', [$startDate, $endDate]);
            }]);

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        $employees = $query->get();

        $report = [];
        foreach ($employees as $employee) {
            $attendances = $employee->attendances;
            $workDays = $this->getWorkDaysInMonth($year, $month);
            
            $report[] = [
                'employee' => [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                    'department' => $employee->department?->name,
                ],
                'present_days' => $attendances->where('status', 'present')->count(),
                'late_days' => $attendances->where('status', 'late')->count(),
                'absent_days' => $workDays - $attendances->count(),
                'total_hours' => $attendances->sum('total_hours'),
                'overtime_hours' => $attendances->sum('overtime_hours'),
                'attendance_rate' => $workDays > 0 ? round(($attendances->where('status', 'present')->count() / $workDays) * 100, 2) : 0,
            ];
        }

        return [
            'period' => $startDate->format('F Y'),
            'department' => $departmentId ? Department::find($departmentId)?->name : 'Toda la empresa',
            'employees' => $report,
            'summary' => [
                'total_employees' => count($report),
                'average_attendance_rate' => count($report) > 0 ? round(collect($report)->avg('attendance_rate'), 2) : 0,
                'total_overtime_hours' => collect($report)->sum('overtime_hours'),
            ],
        ];
    }

    public function importFromCsv($file): array
    {
        $results = [
            'success' => 0,
            'errors' => [],
        ];

        $csv = array_map('str_getcsv', file($file->getRealPath()));
        $headers = array_shift($csv);

        foreach ($csv as $row) {
            try {
                $data = array_combine($headers, $row);
                
                $employee = Employee::where('employee_code', $data['employee_code'])->first();
                if (!$employee) {
                    throw new \Exception("Empleado no encontrado: {$data['employee_code']}");
                }

                $this->markManual($employee, [
                    'date' => Carbon::parse($data['date'])->toDateString(),
                    'check_in' => $data['check_in'],
                    'check_out' => $data['check_out'] ?? null,
                    'status' => $data['status'] ?? 'present',
                ]);

                $results['success']++;
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'row' => $row,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    private function determineStatus(Carbon $checkInTime): string
    {
        $workStartTime = config('hrm.attendance.work_start_time', '09:00');
        $graceMinutes = config('hrm.attendance.grace_minutes', 15);
        
        $expectedTime = Carbon::parse($checkInTime->toDateString() . ' ' . $workStartTime);
        $graceTime = $expectedTime->copy()->addMinutes($graceMinutes);

        if ($checkInTime->lte($graceTime)) {
            return 'present';
        }

        return 'late';
    }

    private function getWorkDaysInMonth(int $year, int $month): int
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();
        
        $workDays = 0;
        $current = $startDate->copy();
        
        while ($current->lte($endDate)) {
            // Exclude weekends (Saturday = 6, Sunday = 0)
            if (!in_array($current->dayOfWeek, [0, 6])) {
                // TODO: Also check for holidays
                $workDays++;
            }
            $current->addDay();
        }

        return $workDays;
    }

    public function getAttendanceByDateRange(Employee $employee, Carbon $startDate, Carbon $endDate)
    {
        return $employee->attendances()
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get();
    }

    public function calculateOvertimeForPeriod(Employee $employee, Carbon $startDate, Carbon $endDate): float
    {
        return $employee->attendances()
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('overtime_hours');
    }

    public function getAbsentEmployees(Carbon $date = null): \Illuminate\Database\Eloquent\Collection
    {
        $date = $date ?? now();
        
        $employeesWithAttendance = Attendance::whereDate('date', $date)
            ->pluck('employee_id');

        return Employee::where('status', 'active')
            ->whereNotIn('id', $employeesWithAttendance)
            ->with('department')
            ->get();
    }
}