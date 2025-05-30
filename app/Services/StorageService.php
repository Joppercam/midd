<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantStorageUsage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorageService
{
    /**
     * Upload a file with storage tracking
     */
    public function uploadFile(
        UploadedFile $file,
        string $directory = 'uploads',
        ?string $fileType = null,
        ?string $relatedModelType = null,
        ?string $relatedModelId = null,
        ?array $metadata = null
    ): TenantStorageUsage {
        $tenant = tenant();
        
        // Check storage quota
        if (!$tenant->canUploadFile($file->getSize())) {
            throw new \Exception('Storage quota exceeded. Please upgrade your plan or free up space.');
        }
        
        // Generate unique filename
        $filename = $this->generateUniqueFilename($file, $directory);
        
        // Store the file
        $path = $file->storeAs($directory, $filename, 'public');
        
        if (!$path) {
            throw new \Exception('Failed to store file.');
        }
        
        // Create storage usage record
        $storageUsage = TenantStorageUsage::create([
            'tenant_id' => $tenant->id,
            'file_type' => $fileType ?? $this->detectFileType($file),
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by_type' => get_class(auth()->user()),
            'uploaded_by_id' => auth()->id(),
            'related_model_type' => $relatedModelType,
            'related_model_id' => $relatedModelId,
            'metadata' => $metadata,
        ]);
        
        return $storageUsage;
    }
    
    /**
     * Delete a file and update storage tracking
     */
    public function deleteFile(TenantStorageUsage $storageUsage): bool
    {
        return $storageUsage->deleteFile();
    }
    
    /**
     * Move a file to a new location
     */
    public function moveFile(TenantStorageUsage $storageUsage, string $newDirectory): bool
    {
        $oldPath = $storageUsage->file_path;
        $filename = basename($oldPath);
        $newPath = $newDirectory . '/' . $filename;
        
        // Check if file exists
        if (!Storage::disk('public')->exists($oldPath)) {
            throw new \Exception('Original file not found.');
        }
        
        // Move the file
        if (Storage::disk('public')->move($oldPath, $newPath)) {
            $storageUsage->update(['file_path' => $newPath]);
            return true;
        }
        
        return false;
    }
    
    /**
     * Copy a file to a new location
     */
    public function copyFile(TenantStorageUsage $storageUsage, string $newDirectory): ?TenantStorageUsage
    {
        $tenant = tenant();
        $oldPath = $storageUsage->file_path;
        $filename = $this->generateUniqueFilename(null, $newDirectory, $storageUsage->original_name);
        $newPath = $newDirectory . '/' . $filename;
        
        // Check storage quota
        if (!$tenant->canUploadFile($storageUsage->file_size)) {
            throw new \Exception('Storage quota exceeded for file copy.');
        }
        
        // Check if original file exists
        if (!Storage::disk('public')->exists($oldPath)) {
            throw new \Exception('Original file not found.');
        }
        
        // Copy the file
        if (Storage::disk('public')->copy($oldPath, $newPath)) {
            // Create new storage usage record
            return TenantStorageUsage::create([
                'tenant_id' => $tenant->id,
                'file_type' => $storageUsage->file_type,
                'file_path' => $newPath,
                'original_name' => $storageUsage->original_name,
                'mime_type' => $storageUsage->mime_type,
                'file_size' => $storageUsage->file_size,
                'uploaded_by_type' => get_class(auth()->user()),
                'uploaded_by_id' => auth()->id(),
                'related_model_type' => $storageUsage->related_model_type,
                'related_model_id' => $storageUsage->related_model_id,
                'metadata' => array_merge($storageUsage->metadata ?? [], ['copied_from' => $oldPath]),
            ]);
        }
        
        return null;
    }
    
    /**
     * Get storage statistics for tenant
     */
    public function getStorageStats(Tenant $tenant): array
    {
        $totalFiles = $tenant->activeStorageUsage()->count();
        $totalSize = $tenant->storage_used;
        $usageByCategory = $tenant->getStorageUsageByCategory();
        
        // Get largest files
        $largestFiles = $tenant->activeStorageUsage()
            ->orderBy('file_size', 'desc')
            ->limit(10)
            ->get();
        
        // Get recent uploads
        $recentUploads = $tenant->activeStorageUsage()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->with(['uploadedBy', 'relatedModel'])
            ->get();
        
        return [
            'total_files' => $totalFiles,
            'total_size' => $totalSize,
            'formatted_total_size' => $tenant->formatted_storage_used,
            'max_storage' => $tenant->max_storage,
            'formatted_max_storage' => $tenant->formatted_max_storage,
            'usage_percentage' => $tenant->storage_usage_percentage,
            'remaining_storage' => $tenant->remaining_storage,
            'usage_by_category' => $usageByCategory,
            'largest_files' => $largestFiles,
            'recent_uploads' => $recentUploads,
            'needs_cleanup' => $tenant->needsStorageCleanup(),
        ];
    }
    
    /**
     * Clean up deleted files
     */
    public function cleanupDeletedFiles(Tenant $tenant): array
    {
        $deletedFiles = $tenant->storageUsage()->deleted()->get();
        $cleanedCount = 0;
        $freedSpace = 0;
        
        foreach ($deletedFiles as $file) {
            // Remove from disk if still exists
            if (Storage::disk('public')->exists($file->file_path)) {
                Storage::disk('public')->delete($file->file_path);
            }
            
            $freedSpace += $file->file_size;
            $file->delete(); // Permanently delete record
            $cleanedCount++;
        }
        
        return [
            'cleaned_files' => $cleanedCount,
            'freed_space' => $freedSpace,
            'formatted_freed_space' => $this->formatBytes($freedSpace),
        ];
    }
    
    /**
     * Find orphaned files (files on disk not tracked in database)
     */
    public function findOrphanedFiles(Tenant $tenant): array
    {
        $trackedFiles = $tenant->storageUsage()
            ->pluck('file_path')
            ->toArray();
        
        $tenantDirectory = 'tenant_' . $tenant->id;
        $allFiles = Storage::disk('public')->allFiles($tenantDirectory);
        
        $orphanedFiles = array_diff($allFiles, $trackedFiles);
        
        $orphanedData = [];
        foreach ($orphanedFiles as $file) {
            $size = Storage::disk('public')->size($file);
            $orphanedData[] = [
                'path' => $file,
                'size' => $size,
                'formatted_size' => $this->formatBytes($size),
                'last_modified' => Storage::disk('public')->lastModified($file),
            ];
        }
        
        return $orphanedData;
    }
    
    /**
     * Generate unique filename
     */
    protected function generateUniqueFilename(?UploadedFile $file = null, string $directory = '', ?string $originalName = null): string
    {
        $name = $originalName ?? ($file ? $file->getClientOriginalName() : 'file');
        $extension = $file ? $file->getClientOriginalExtension() : pathinfo($name, PATHINFO_EXTENSION);
        $baseName = pathinfo($name, PATHINFO_FILENAME);
        
        // Sanitize filename
        $baseName = Str::slug($baseName);
        $timestamp = now()->format('Y-m-d_H-i-s');
        $random = Str::random(8);
        
        return "{$baseName}_{$timestamp}_{$random}.{$extension}";
    }
    
    /**
     * Detect file type based on mime type
     */
    protected function detectFileType(UploadedFile $file): string
    {
        $mimeType = $file->getMimeType();
        
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }
        
        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }
        
        if (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }
        
        if (in_array($mimeType, [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])) {
            return 'document';
        }
        
        if (in_array($mimeType, [
            'application/zip',
            'application/x-rar-compressed',
            'application/x-7z-compressed',
        ])) {
            return 'archive';
        }
        
        return 'other';
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
     * Validate file upload constraints
     */
    public function validateFileUpload(UploadedFile $file, ?array $allowedTypes = null, ?int $maxSize = null): bool
    {
        $tenant = tenant();
        
        // Check file size against tenant limit
        if (!$tenant->canUploadFile($file->getSize())) {
            throw new \Exception('File size exceeds available storage quota.');
        }
        
        // Check max file size
        if ($maxSize && $file->getSize() > $maxSize) {
            throw new \Exception('File size exceeds maximum allowed size.');
        }
        
        // Check allowed file types
        if ($allowedTypes && !in_array($file->getMimeType(), $allowedTypes)) {
            throw new \Exception('File type not allowed.');
        }
        
        return true;
    }
}