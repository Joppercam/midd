<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends TenantAwareModel
{
    protected $fillable = [
        'employee_id',
        'date',
        'check_in',
        'check_out',
        'break_start',
        'break_end',
        'regular_hours',
        'overtime_hours',
        'break_hours',
        'status',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'check_in' => 'datetime:H:i',
        'check_out' => 'datetime:H:i',
        'break_start' => 'datetime:H:i',
        'break_end' => 'datetime:H:i',
        'regular_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'break_hours' => 'decimal:2',
    ];

    const STATUS_PRESENT = 'present';
    const STATUS_ABSENT = 'absent';
    const STATUS_LATE = 'late';
    const STATUS_PARTIAL = 'partial';
    const STATUS_HOLIDAY = 'holiday';
    const STATUS_SICK_LEAVE = 'sick_leave';
    const STATUS_VACATION = 'vacation';

    const STATUSES = [
        self::STATUS_PRESENT => 'Presente',
        self::STATUS_ABSENT => 'Ausente',
        self::STATUS_LATE => 'Tarde',
        self::STATUS_PARTIAL => 'Parcial',
        self::STATUS_HOLIDAY => 'Feriado',
        self::STATUS_SICK_LEAVE => 'Licencia Médica',
        self::STATUS_VACATION => 'Vacaciones',
    ];

    /**
     * Employee this record belongs to
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Get total worked hours
     */
    public function getTotalHoursAttribute(): float
    {
        return $this->regular_hours + $this->overtime_hours;
    }

    /**
     * Check if late
     */
    public function isLate(): bool
    {
        return $this->status === self::STATUS_LATE;
    }

    /**
     * Check if present
     */
    public function isPresent(): bool
    {
        return in_array($this->status, [self::STATUS_PRESENT, self::STATUS_LATE, self::STATUS_PARTIAL]);
    }
}