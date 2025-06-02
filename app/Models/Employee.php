<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class Employee extends TenantAwareModel
{
    protected $fillable = [
        'user_id',
        'employee_number',
        'first_name',
        'last_name',
        'rut',
        'email',
        'phone',
        'mobile',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'birth_date',
        'gender',
        'marital_status',
        'photo_path',
        'status',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    protected $appends = [
        'full_name',
        'current_salary',
        'department',
        'position',
        'hire_date'
    ];

    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_TERMINATED = 'terminated';

    const STATUSES = [
        self::STATUS_ACTIVE => 'Activo',
        self::STATUS_INACTIVE => 'Inactivo',
        self::STATUS_TERMINATED => 'Terminado',
    ];

    const GENDERS = [
        'male' => 'Masculino',
        'female' => 'Femenino',
        'other' => 'Otro',
    ];

    const MARITAL_STATUSES = [
        'single' => 'Soltero/a',
        'married' => 'Casado/a',
        'divorced' => 'Divorciado/a',
        'widowed' => 'Viudo/a',
    ];

    /**
     * User account associated with this employee
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Employment contracts
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(EmploymentContract::class);
    }

    /**
     * Current active contract
     */
    public function currentContract(): HasOne
    {
        return $this->hasOne(EmploymentContract::class)
            ->where('status', EmploymentContract::STATUS_ACTIVE)
            ->latest('start_date');
    }

    /**
     * Attendance records
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * Leave requests
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * Payslips
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    /**
     * Employee benefits
     */
    public function benefits(): HasMany
    {
        return $this->hasMany(EmployeeBenefit::class);
    }

    /**
     * Departments managed by this employee
     */
    public function managedDepartments(): HasMany
    {
        return $this->hasMany(Department::class, 'manager_id');
    }

    /**
     * Get full name
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Get display name (includes employee number)
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->employee_number} - {$this->full_name}";
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Get gender label
     */
    public function getGenderLabelAttribute(): string
    {
        return self::GENDERS[$this->gender] ?? $this->gender;
    }

    /**
     * Get marital status label
     */
    public function getMaritalStatusLabelAttribute(): string
    {
        return self::MARITAL_STATUSES[$this->marital_status] ?? $this->marital_status;
    }

    /**
     * Get photo URL
     */
    public function getPhotoUrlAttribute(): ?string
    {
        if (!$this->photo_path) {
            return null;
        }

        return Storage::url($this->photo_path);
    }

    /**
     * Get age
     */
    public function getAgeAttribute(): ?int
    {
        if (!$this->birth_date) {
            return null;
        }

        return now()->diffInYears($this->birth_date);
    }

    /**
     * Get current department
     */
    public function getCurrentDepartment()
    {
        return $this->currentContract?->department;
    }

    /**
     * Get current position
     */
    public function getCurrentPosition()
    {
        return $this->currentContract?->position;
    }

    /**
     * Get current salary
     */
    public function getCurrentSalary(): ?float
    {
        return $this->currentContract?->base_salary;
    }

    /**
     * Get current salary as attribute
     */
    public function getCurrentSalaryAttribute(): ?float
    {
        return $this->getCurrentSalary();
    }

    /**
     * Get department as attribute
     */
    public function getDepartmentAttribute()
    {
        return $this->getCurrentDepartment();
    }

    /**
     * Get position as attribute
     */
    public function getPositionAttribute()
    {
        return $this->getCurrentPosition();
    }

    /**
     * Get hire date from current contract
     */
    public function getHireDateAttribute()
    {
        return $this->currentContract?->start_date;
    }

    /**
     * Check if employee is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if employee has system access
     */
    public function hasSystemAccess(): bool
    {
        return $this->user_id !== null && $this->user && $this->user->is_active;
    }

    /**
     * Calculate worked days in period
     */
    public function getWorkedDaysInPeriod(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate): float
    {
        return $this->attendanceRecords()
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('status', ['present', 'late', 'partial'])
            ->sum('regular_hours') / 8; // Assuming 8 hours per day
    }

    /**
     * Calculate overtime hours in period
     */
    public function getOvertimeHoursInPeriod(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate): float
    {
        return $this->attendanceRecords()
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('overtime_hours');
    }

    /**
     * Get pending leave requests
     */
    public function getPendingLeaveRequests()
    {
        return $this->leaveRequests()
            ->where('status', LeaveRequest::STATUS_PENDING)
            ->where('start_date', '>=', now())
            ->get();
    }

    /**
     * Get remaining vacation days
     */
    public function getRemainingVacationDays(): float
    {
        $contract = $this->currentContract;
        if (!$contract) {
            return 0;
        }

        // Calculate vacation days based on Chilean law (15 working days per year)
        $yearsSinceStart = now()->diffInYears($contract->start_date);
        $totalVacationDays = 15 + ($yearsSinceStart > 0 ? $yearsSinceStart : 0); // Extra day per year

        // Subtract used vacation days this year
        $usedVacationDays = $this->leaveRequests()
            ->where('type', LeaveRequest::TYPE_VACATION)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereYear('start_date', now()->year)
            ->sum('days_requested');

        return max(0, $totalVacationDays - $usedVacationDays);
    }

    /**
     * Scope for active employees
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for employees with system access
     */
    public function scopeWithSystemAccess($query)
    {
        return $query->whereNotNull('user_id')
                    ->whereHas('user', function($q) {
                        $q->where('is_active', true);
                    });
    }

    /**
     * Scope by department
     */
    public function scopeInDepartment($query, $departmentId)
    {
        return $query->whereHas('currentContract', function($q) use ($departmentId) {
            $q->where('department_id', $departmentId);
        });
    }

    /**
     * Scope by position
     */
    public function scopeInPosition($query, $positionId)
    {
        return $query->whereHas('currentContract', function($q) use ($positionId) {
            $q->where('position_id', $positionId);
        });
    }

    /**
     * Generate unique employee number
     */
    public static function generateEmployeeNumber(): string
    {
        $tenant = tenant();
        $year = now()->format('Y');
        $prefix = $tenant->getSetting('employee_number_prefix', 'EMP');
        
        $lastEmployee = static::where('tenant_id', $tenant->id)
            ->where('employee_number', 'like', "{$prefix}{$year}%")
            ->orderBy('employee_number', 'desc')
            ->first();

        if ($lastEmployee) {
            $lastNumber = (int) substr($lastEmployee->employee_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . $year . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($employee) {
            if (!$employee->employee_number) {
                $employee->employee_number = static::generateEmployeeNumber();
            }
        });
    }
}