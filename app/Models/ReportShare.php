<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportShare extends Model
{
    use HasUuids;

    protected $fillable = [
        'scheduled_report_id',
        'user_id',
        'role',
        'email',
        'permission',
        'receive_emails',
    ];

    protected $casts = [
        'receive_emails' => 'boolean',
    ];

    const PERMISSION_VIEW = 'view';
    const PERMISSION_EDIT = 'edit';
    const PERMISSION_ADMIN = 'admin';

    const PERMISSIONS = [
        self::PERMISSION_VIEW => 'Solo Ver',
        self::PERMISSION_EDIT => 'Ver y Editar',
        self::PERMISSION_ADMIN => 'Administrador',
    ];

    /**
     * Scheduled report this share belongs to
     */
    public function scheduledReport(): BelongsTo
    {
        return $this->belongsTo(ScheduledReport::class);
    }

    /**
     * User this share is for (if user-based)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get permission label
     */
    public function getPermissionLabelAttribute(): string
    {
        return self::PERMISSIONS[$this->permission] ?? $this->permission;
    }

    /**
     * Get share type (user, role, or email)
     */
    public function getShareTypeAttribute(): string
    {
        if ($this->user_id) {
            return 'user';
        } elseif ($this->role) {
            return 'role';
        } elseif ($this->email) {
            return 'email';
        }
        
        return 'unknown';
    }

    /**
     * Get display name for the share
     */
    public function getDisplayNameAttribute(): string
    {
        switch ($this->share_type) {
            case 'user':
                return $this->user->name ?? 'Usuario Desconocido';
            case 'role':
                return ucfirst($this->role);
            case 'email':
                return $this->email;
            default:
                return 'Desconocido';
        }
    }

    /**
     * Check if user can view
     */
    public function canView(): bool
    {
        return in_array($this->permission, [
            self::PERMISSION_VIEW,
            self::PERMISSION_EDIT,
            self::PERMISSION_ADMIN
        ]);
    }

    /**
     * Check if user can edit
     */
    public function canEdit(): bool
    {
        return in_array($this->permission, [
            self::PERMISSION_EDIT,
            self::PERMISSION_ADMIN
        ]);
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->permission === self::PERMISSION_ADMIN;
    }

    /**
     * Scope by permission
     */
    public function scopeByPermission($query, string $permission)
    {
        return $query->where('permission', $permission);
    }

    /**
     * Scope by user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope by role
     */
    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Scope for email recipients
     */
    public function scopeEmailRecipients($query)
    {
        return $query->where('receive_emails', true);
    }
}