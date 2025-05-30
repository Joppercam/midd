<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiiEventLog extends Model
{
    protected $fillable = [
        'tenant_id',
        'tax_document_id',
        'event_type',
        'track_id',
        'status',
        'request_data',
        'response_data',
        'error_message',
        'ip_address',
        'user_agent',
        'response_time',
    ];

    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Event types
     */
    const TYPE_UPLOAD = 'upload';
    const TYPE_STATUS_CHECK = 'status_check';
    const TYPE_ACCEPTANCE = 'acceptance';
    const TYPE_REJECTION = 'rejection';
    const TYPE_ERROR = 'error';
    const TYPE_AUTHENTICATION = 'authentication';

    /**
     * Get the tenant that owns the event log
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the tax document associated with the event
     */
    public function taxDocument(): BelongsTo
    {
        return $this->belongsTo(TaxDocument::class);
    }

    /**
     * Scope for filtering by event type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('event_type', $type);
    }

    /**
     * Scope for filtering by track ID
     */
    public function scopeForTrackId($query, $trackId)
    {
        return $query->where('track_id', $trackId);
    }

    /**
     * Scope for recent events
     */
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope for error events
     */
    public function scopeErrors($query)
    {
        return $query->whereNotNull('error_message')
            ->orWhere('event_type', self::TYPE_ERROR);
    }

    /**
     * Get formatted event type
     */
    public function getFormattedEventTypeAttribute(): string
    {
        $types = [
            self::TYPE_UPLOAD => 'Envío de documento',
            self::TYPE_STATUS_CHECK => 'Consulta de estado',
            self::TYPE_ACCEPTANCE => 'Aceptación',
            self::TYPE_REJECTION => 'Rechazo',
            self::TYPE_ERROR => 'Error',
            self::TYPE_AUTHENTICATION => 'Autenticación',
        ];

        return $types[$this->event_type] ?? $this->event_type;
    }

    /**
     * Check if event is an error
     */
    public function isError(): bool
    {
        return $this->event_type === self::TYPE_ERROR || 
               $this->event_type === self::TYPE_REJECTION ||
               !empty($this->error_message);
    }

    /**
     * Get response time in seconds
     */
    public function getResponseTimeInSecondsAttribute(): ?float
    {
        return $this->response_time ? $this->response_time / 1000 : null;
    }
}