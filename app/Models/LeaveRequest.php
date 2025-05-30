<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends TenantAwareModel
{
    protected $fillable = [
        'employee_id',
        'approved_by',
        'type',
        'start_date',
        'end_date',
        'days_requested',
        'reason',
        'status',
        'approval_notes',
        'approved_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'days_requested' => 'decimal:1',
        'approved_at' => 'datetime',
    ];

    const TYPE_VACATION = 'vacation';
    const TYPE_SICK_LEAVE = 'sick_leave';
    const TYPE_MATERNITY = 'maternity';
    const TYPE_PATERNITY = 'paternity';
    const TYPE_PERSONAL = 'personal';
    const TYPE_BEREAVEMENT = 'bereavement';
    const TYPE_OTHER = 'other';

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    const TYPES = [
        self::TYPE_VACATION => 'Vacaciones',
        self::TYPE_SICK_LEAVE => 'Licencia Médica',
        self::TYPE_MATERNITY => 'Permiso Maternal',
        self::TYPE_PATERNITY => 'Permiso Paternal',
        self::TYPE_PERSONAL => 'Permiso Personal',
        self::TYPE_BEREAVEMENT => 'Duelo',
        self::TYPE_OTHER => 'Otro',
    ];

    const STATUSES = [
        self::STATUS_PENDING => 'Pendiente',
        self::STATUS_APPROVED => 'Aprobado',
        self::STATUS_REJECTED => 'Rechazado',
    ];

    /**
     * Employee requesting leave
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Employee who approved the request
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    /**
     * Get type label
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Approve request
     */
    public function approve(Employee $approver, ?string $notes = null): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'approval_notes' => $notes,
        ]);
    }

    /**
     * Reject request
     */
    public function reject(Employee $approver, ?string $notes = null): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'approval_notes' => $notes,
        ]);
    }
}