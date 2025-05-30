<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ReportExecution extends TenantAwareModel
{
    protected $fillable = [
        'scheduled_report_id',
        'report_template_id',
        'user_id',
        'name',
        'status',
        'parameters',
        'format',
        'file_path',
        'file_size',
        'total_records',
        'started_at',
        'completed_at',
        'execution_time_seconds',
        'error_message',
        'metadata',
        'was_emailed',
        'emailed_at',
    ];

    protected $casts = [
        'parameters' => 'array',
        'file_size' => 'integer',
        'total_records' => 'integer',
        'execution_time_seconds' => 'integer',
        'metadata' => 'array',
        'was_emailed' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'emailed_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    const STATUSES = [
        self::STATUS_PENDING => 'Pendiente',
        self::STATUS_RUNNING => 'Ejecutando',
        self::STATUS_COMPLETED => 'Completado',
        self::STATUS_FAILED => 'Fallido',
    ];

    /**
     * Scheduled report this execution belongs to
     */
    public function scheduledReport(): BelongsTo
    {
        return $this->belongsTo(ScheduledReport::class);
    }

    /**
     * Report template used for this execution
     */
    public function reportTemplate(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class);
    }

    /**
     * User who triggered this execution
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mark execution as started
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => self::STATUS_RUNNING,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark execution as completed
     */
    public function markAsCompleted(string $filePath, int $fileSize, int $totalRecords = 0, array $metadata = []): void
    {
        $startedAt = $this->started_at ?? now();
        $completedAt = now();
        $executionTime = $completedAt->diffInSeconds($startedAt);

        $this->update([
            'status' => self::STATUS_COMPLETED,
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'total_records' => $totalRecords,
            'completed_at' => $completedAt,
            'execution_time_seconds' => $executionTime,
            'metadata' => array_merge($this->metadata ?? [], $metadata),
        ]);
    }

    /**
     * Mark execution as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $startedAt = $this->started_at ?? now();
        $completedAt = now();
        $executionTime = $completedAt->diffInSeconds($startedAt);

        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'completed_at' => $completedAt,
            'execution_time_seconds' => $executionTime,
        ]);
    }

    /**
     * Mark as emailed
     */
    public function markAsEmailed(): void
    {
        $this->update([
            'was_emailed' => true,
            'emailed_at' => now(),
        ]);
    }

    /**
     * Get file URL
     */
    public function getFileUrlAttribute(): ?string
    {
        if (!$this->file_path || !Storage::exists($this->file_path)) {
            return null;
        }

        return Storage::url($this->file_path);
    }

    /**
     * Get formatted file size
     */
    public function getFormattedFileSizeAttribute(): string
    {
        if (!$this->file_size) {
            return 'N/A';
        }

        return $this->formatBytes($this->file_size);
    }

    /**
     * Get formatted execution time
     */
    public function getFormattedExecutionTimeAttribute(): string
    {
        if (!$this->execution_time_seconds) {
            return 'N/A';
        }

        $seconds = $this->execution_time_seconds;
        
        if ($seconds < 60) {
            return $seconds . ' segundos';
        }

        if ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $remainingSeconds = $seconds % 60;
            return $minutes . 'm ' . $remainingSeconds . 's';
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return $hours . 'h ' . $minutes . 'm';
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Get status color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'gray',
            self::STATUS_RUNNING => 'blue',
            self::STATUS_COMPLETED => 'green',
            self::STATUS_FAILED => 'red',
            default => 'gray',
        };
    }

    /**
     * Check if execution is in progress
     */
    public function isInProgress(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_RUNNING]);
    }

    /**
     * Check if execution is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if execution has failed
     */
    public function hasFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if file exists
     */
    public function fileExists(): bool
    {
        return $this->file_path && Storage::exists($this->file_path);
    }

    /**
     * Delete the associated file
     */
    public function deleteFile(): bool
    {
        if ($this->fileExists()) {
            return Storage::delete($this->file_path);
        }

        return true;
    }

    /**
     * Get download filename
     */
    public function getDownloadFilename(): string
    {
        $name = \Str::slug($this->name);
        $timestamp = $this->created_at->format('Y-m-d_H-i');
        $extension = pathinfo($this->file_path, PATHINFO_EXTENSION);

        return "{$name}_{$timestamp}.{$extension}";
    }

    /**
     * Format bytes to human readable format
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Scope for completed executions
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for failed executions
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for in progress executions
     */
    public function scopeInProgress($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_RUNNING]);
    }

    /**
     * Scope by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope by date range
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}