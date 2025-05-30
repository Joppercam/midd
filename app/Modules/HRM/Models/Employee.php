<?php

namespace App\Modules\HRM\Models;

use App\Models\TenantAwareModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends TenantAwareModel
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'employee_code',
        'first_name',
        'last_name',
        'middle_name',
        'email',
        'personal_email',
        'phone',
        'mobile',
        'rut',
        'birth_date',
        'gender',
        'marital_status',
        'nationality',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'department_id',
        'position_id',
        'manager_id',
        'hire_date',
        'probation_end_date',
        'contract_type',
        'contract_end_date',
        'work_location',
        'shift_type',
        'employment_status',
        'resignation_date',
        'resignation_reason',
        'bank_name',
        'bank_account_number',
        'bank_account_type',
        'afp_name',
        'health_insurance_name',
        'health_insurance_plan',
        'base_salary',
        'currency',
        'payment_frequency',
        'photo_url',
        'bio',
        'skills',
        'languages',
        'education_level',
        'linkedin_url',
        'is_active',
        'last_review_date',
        'next_review_date',
        'notes',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'birth_date' => 'date',
        'hire_date' => 'date',
        'probation_end_date' => 'date',
        'contract_end_date' => 'date',
        'resignation_date' => 'date',
        'last_review_date' => 'date',
        'next_review_date' => 'date',
        'base_salary' => 'decimal:2',
        'skills' => 'array',
        'languages' => 'array',
        'is_active' => 'boolean'
    ];

    protected $appends = ['full_name', 'age', 'years_of_service'];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(EmploymentContract::class);
    }

    public function currentContract(): HasOne
    {
        return $this->hasOne(EmploymentContract::class)
            ->where('is_active', true)
            ->latest();
    }

    public function performanceReviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class);
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(EmployeeTraining::class);
    }

    public function benefits(): HasMany
    {
        return $this->hasMany(EmployeeBenefit::class);
    }

    // Accessors
    public function getFullNameAttribute(): string
    {
        $name = $this->first_name;
        if ($this->middle_name) {
            $name .= ' ' . $this->middle_name;
        }
        $name .= ' ' . $this->last_name;
        return $name;
    }

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date ? $this->birth_date->age : null;
    }

    public function getYearsOfServiceAttribute(): float
    {
        if (!$this->hire_date) {
            return 0;
        }
        
        $endDate = $this->resignation_date ?? now();
        return round($this->hire_date->diffInYears($endDate), 1);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('employment_status', '!=', 'terminated');
    }

    public function scopeByDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeByManager($query, $managerId)
    {
        return $query->where('manager_id', $managerId);
    }

    public function scopeInProbation($query)
    {
        return $query->where('probation_end_date', '>=', now());
    }

    public function scopeContractExpiringSoon($query, $days = 30)
    {
        return $query->whereNotNull('contract_end_date')
            ->whereBetween('contract_end_date', [now(), now()->addDays($days)]);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
              ->orWhere('last_name', 'like', "%{$search}%")
              ->orWhere('employee_code', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('rut', 'like', "%{$search}%");
        });
    }

    // Methods
    public function isOnLeave(): bool
    {
        return $this->leaves()
            ->where('status', 'approved')
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->exists();
    }

    public function getCurrentLeave()
    {
        return $this->leaves()
            ->where('status', 'approved')
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();
    }

    public function getLeaveBalance(string $leaveType = 'annual'): array
    {
        $year = now()->year;
        $allocated = config("hrm.leaves.types.{$leaveType}.days", 0);
        
        $used = $this->leaves()
            ->where('type', $leaveType)
            ->whereIn('status', ['approved', 'taken'])
            ->whereYear('start_date', $year)
            ->sum('days');
        
        $pending = $this->leaves()
            ->where('type', $leaveType)
            ->where('status', 'pending')
            ->whereYear('start_date', $year)
            ->sum('days');
        
        return [
            'allocated' => $allocated,
            'used' => $used,
            'pending' => $pending,
            'available' => $allocated - $used - $pending
        ];
    }

    public function canTakeLeave(string $leaveType, int $days): bool
    {
        $balance = $this->getLeaveBalance($leaveType);
        return $balance['available'] >= $days;
    }

    public function getAttendanceForMonth($year, $month)
    {
        return $this->attendances()
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->orderBy('date')
            ->get();
    }

    public function calculateMonthlyWorkHours($year, $month): array
    {
        $attendances = $this->getAttendanceForMonth($year, $month);
        
        $totalHours = 0;
        $overtimeHours = 0;
        $lateMinutes = 0;
        $earlyLeaveMinutes = 0;
        
        foreach ($attendances as $attendance) {
            $totalHours += $attendance->work_hours;
            $overtimeHours += $attendance->overtime_hours;
            $lateMinutes += $attendance->late_minutes;
            $earlyLeaveMinutes += $attendance->early_leave_minutes;
        }
        
        return [
            'total_hours' => $totalHours,
            'regular_hours' => $totalHours - $overtimeHours,
            'overtime_hours' => $overtimeHours,
            'late_minutes' => $lateMinutes,
            'early_leave_minutes' => $earlyLeaveMinutes,
            'attendance_count' => $attendances->count()
        ];
    }

    public function getUpcomingReviewDate()
    {
        if ($this->next_review_date) {
            return $this->next_review_date;
        }
        
        // Calculate based on review frequency
        $frequency = config('hrm.performance.review_frequency', 'annual');
        $lastReview = $this->last_review_date ?? $this->hire_date;
        
        if (!$lastReview) {
            return null;
        }
        
        switch ($frequency) {
            case 'monthly':
                return $lastReview->addMonth();
            case 'quarterly':
                return $lastReview->addMonths(3);
            case 'semi-annual':
                return $lastReview->addMonths(6);
            case 'annual':
            default:
                return $lastReview->addYear();
        }
    }

    public static function generateEmployeeCode(): string
    {
        $prefix = 'EMP';
        $year = now()->format('y');
        
        $lastEmployee = static::where('employee_code', 'like', "{$prefix}{$year}%")
            ->orderBy('employee_code', 'desc')
            ->first();
        
        if ($lastEmployee) {
            $lastNumber = intval(substr($lastEmployee->employee_code, -4));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . $year . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }
}