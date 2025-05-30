<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class TenantStorageUsage extends TenantAwareModel
{
    protected $table = 'tenant_storage_usage';

    protected $fillable = [
        'file_type',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'uploaded_by_type',
        'uploaded_by_id',
        'related_model_type',
        'related_model_id',
        'metadata',
        'is_deleted',
        'deleted_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'metadata' => 'array',
        'is_deleted' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    /**
     * The tenant this storage usage belongs to
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * The user/model who uploaded this file
     */
    public function uploadedBy(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The model this file is related to
     */
    public function relatedModel(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get file size in human readable format
     */
    public function getFormattedSizeAttribute(): string
    {
        return $this->formatBytes($this->file_size);
    }

    /**
     * Get file URL
     */
    public function getUrlAttribute(): ?string
    {
        if ($this->is_deleted || !Storage::exists($this->file_path)) {
            return null;
        }

        return Storage::url($this->file_path);
    }

    /**
     * Check if file exists on disk
     */
    public function fileExists(): bool
    {
        return !$this->is_deleted && Storage::exists($this->file_path);
    }

    /**
     * Mark file as deleted (soft delete)
     */
    public function markAsDeleted(): void
    {
        $this->update([
            'is_deleted' => true,
            'deleted_at' => now(),
        ]);

        // Update tenant storage usage
        $this->tenant->decrementStorageUsed($this->file_size);
    }

    /**
     * Physically delete file from storage
     */
    public function deleteFile(): bool
    {
        if (Storage::exists($this->file_path)) {
            $deleted = Storage::delete($this->file_path);
            
            if ($deleted) {
                $this->markAsDeleted();
                return true;
            }
            
            return false;
        }

        // File doesn't exist, mark as deleted anyway
        $this->markAsDeleted();
        return true;
    }

    /**
     * Get file extension
     */
    public function getExtensionAttribute(): string
    {
        return pathinfo($this->original_name, PATHINFO_EXTENSION);
    }

    /**
     * Get file category based on mime type
     */
    public function getCategoryAttribute(): string
    {
        if (!$this->mime_type) {
            return 'unknown';
        }

        $type = explode('/', $this->mime_type)[0];
        
        return match($type) {
            'image' => 'image',
            'video' => 'video',
            'audio' => 'audio',
            'text' => 'document',
            'application' => $this->getApplicationCategory(),
            default => 'unknown',
        };
    }

    /**
     * Get application file category
     */
    protected function getApplicationCategory(): string
    {
        $documentTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];

        $archiveTypes = [
            'application/zip',
            'application/x-rar-compressed',
            'application/x-7z-compressed',
        ];

        if (in_array($this->mime_type, $documentTypes)) {
            return 'document';
        }

        if (in_array($this->mime_type, $archiveTypes)) {
            return 'archive';
        }

        return 'application';
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
     * Scope for active (not deleted) files
     */
    public function scopeActive($query)
    {
        return $query->where('is_deleted', false);
    }

    /**
     * Scope for deleted files
     */
    public function scopeDeleted($query)
    {
        return $query->where('is_deleted', true);
    }

    /**
     * Scope by file type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('file_type', $type);
    }

    /**
     * Scope by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->whereRaw("
            CASE 
                WHEN mime_type LIKE 'image/%' THEN 'image'
                WHEN mime_type LIKE 'video/%' THEN 'video'
                WHEN mime_type LIKE 'audio/%' THEN 'audio'
                WHEN mime_type IN ('application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') THEN 'document'
                ELSE 'other'
            END = ?
        ", [$category]);
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($usage) {
            // Update tenant storage usage when file is tracked
            $usage->tenant->incrementStorageUsed($usage->file_size);
        });

        static::deleting(function ($usage) {
            // When permanently deleting, update tenant storage
            if (!$usage->is_deleted) {
                $usage->tenant->decrementStorageUsed($usage->file_size);
            }
        });
    }
}