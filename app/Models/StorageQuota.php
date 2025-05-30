<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorageQuota extends Model
{
    use HasUuids;

    protected $fillable = [
        'subscription_plan_id',
        'storage_limit',
        'file_upload_limit',
        'allowed_file_types',
        'max_files_per_month',
    ];

    protected $casts = [
        'storage_limit' => 'integer',
        'file_upload_limit' => 'integer',
        'allowed_file_types' => 'array',
        'max_files_per_month' => 'integer',
    ];

    /**
     * Subscription plan this quota belongs to
     */
    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    /**
     * Get formatted storage limit
     */
    public function getFormattedStorageLimitAttribute(): string
    {
        return $this->formatBytes($this->storage_limit);
    }

    /**
     * Get formatted file upload limit
     */
    public function getFormattedFileUploadLimitAttribute(): string
    {
        return $this->file_upload_limit ? $this->formatBytes($this->file_upload_limit) : 'No limit';
    }

    /**
     * Check if file type is allowed
     */
    public function isFileTypeAllowed(string $mimeType): bool
    {
        if (!$this->allowed_file_types) {
            return true; // No restrictions
        }

        return in_array($mimeType, $this->allowed_file_types);
    }

    /**
     * Check if file size is within limit
     */
    public function isFileSizeAllowed(int $fileSize): bool
    {
        if (!$this->file_upload_limit) {
            return true; // No limit
        }

        return $fileSize <= $this->file_upload_limit;
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