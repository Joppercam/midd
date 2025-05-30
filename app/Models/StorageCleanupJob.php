<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorageCleanupJob extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'type',
        'status',
        'parameters',
        'bytes_processed',
        'bytes_freed',
        'files_processed',
        'files_deleted',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'parameters' => 'array',
        'bytes_processed' => 'integer',
        'bytes_freed' => 'integer',
        'files_processed' => 'integer',
        'files_deleted' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    const TYPE_CLEANUP_TEMP = 'cleanup_temp';
    const TYPE_COMPRESS_IMAGES = 'compress_images';
    const TYPE_ARCHIVE_OLD = 'archive_old';
    const TYPE_DELETE_UNUSED = 'delete_unused';
    const TYPE_CLEANUP_DELETED = 'cleanup_deleted';
    const TYPE_CLEANUP_ORPHANED = 'cleanup_orphaned';

    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    /**
     * Tenant this cleanup job belongs to
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Mark job as started
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => self::STATUS_RUNNING,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark job as completed
     */
    public function markAsCompleted(array $results = []): void
    {
        $this->update(array_merge([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
        ], $results));
    }

    /**
     * Mark job as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    /**
     * Get duration in seconds
     */
    public function getDurationAttribute(): ?int
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }

        return $this->completed_at->diffInSeconds($this->started_at);
    }

    /**
     * Get formatted duration
     */
    public function getFormattedDurationAttribute(): string
    {
        $duration = $this->duration;
        
        if (!$duration) {
            return 'N/A';
        }

        if ($duration < 60) {
            return $duration . ' seconds';
        }

        if ($duration < 3600) {
            return floor($duration / 60) . ' minutes';
        }

        return floor($duration / 3600) . ' hours, ' . floor(($duration % 3600) / 60) . ' minutes';
    }

    /**
     * Get formatted bytes freed
     */
    public function getFormattedBytesFreedAttribute(): string
    {
        return $this->formatBytes($this->bytes_freed);
    }

    /**
     * Get formatted bytes processed
     */
    public function getFormattedBytesProcessedAttribute(): string
    {
        return $this->formatBytes($this->bytes_processed);
    }

    /**
     * Check if job is in progress
     */
    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    /**
     * Check if job is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if job has failed
     */
    public function hasFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Get job type label
     */
    public function getTypeLabel(): string
    {
        return match($this->type) {
            self::TYPE_CLEANUP_TEMP => 'Cleanup Temporary Files',
            self::TYPE_COMPRESS_IMAGES => 'Compress Images',
            self::TYPE_ARCHIVE_OLD => 'Archive Old Files',
            self::TYPE_DELETE_UNUSED => 'Delete Unused Files',
            self::TYPE_CLEANUP_DELETED => 'Cleanup Deleted Files',
            self::TYPE_CLEANUP_ORPHANED => 'Cleanup Orphaned Files',
            default => 'Unknown',
        };
    }

    /**
     * Get status color for UI
     */
    public function getStatusColor(): string
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
     * Scope for pending jobs
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for running jobs
     */
    public function scopeRunning($query)
    {
        return $query->where('status', self::STATUS_RUNNING);
    }

    /**
     * Scope for completed jobs
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for failed jobs
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
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
}