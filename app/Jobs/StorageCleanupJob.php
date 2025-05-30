<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\StorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class StorageCleanupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Tenant $tenant;
    protected string $cleanupType;
    protected array $parameters;

    public function __construct(Tenant $tenant, string $cleanupType = 'cleanup_temp', array $parameters = [])
    {
        $this->tenant = $tenant;
        $this->cleanupType = $cleanupType;
        $this->parameters = $parameters;
    }

    public function handle(StorageService $storageService): void
    {
        try {
            Log::info("Starting storage cleanup for tenant {$this->tenant->id}", [
                'type' => $this->cleanupType,
                'parameters' => $this->parameters
            ]);

            $result = match($this->cleanupType) {
                'cleanup_temp' => $this->cleanupTempFiles($storageService),
                'cleanup_deleted' => $this->cleanupDeletedFiles($storageService),
                'cleanup_orphaned' => $this->cleanupOrphanedFiles($storageService),
                'compress_images' => $this->compressImages($storageService),
                'archive_old' => $this->archiveOldFiles($storageService),
                default => throw new \InvalidArgumentException("Unknown cleanup type: {$this->cleanupType}")
            };

            Log::info("Storage cleanup completed for tenant {$this->tenant->id}", $result);

        } catch (\Exception $e) {
            Log::error("Storage cleanup failed for tenant {$this->tenant->id}", [
                'error' => $e->getMessage(),
                'type' => $this->cleanupType
            ]);
            
            throw $e;
        }
    }

    /**
     * Clean up temporary files older than specified days
     */
    protected function cleanupTempFiles(StorageService $storageService): array
    {
        $daysOld = $this->parameters['days_old'] ?? 7;
        $cutoffDate = now()->subDays($daysOld);
        
        $tempFiles = $this->tenant->storageUsage()
            ->where('file_type', 'temp')
            ->where('created_at', '<', $cutoffDate)
            ->get();

        $cleanedCount = 0;
        $freedSpace = 0;

        foreach ($tempFiles as $file) {
            if ($storageService->deleteFile($file)) {
                $cleanedCount++;
                $freedSpace += $file->file_size;
            }
        }

        return [
            'type' => 'cleanup_temp',
            'cleaned_files' => $cleanedCount,
            'freed_space' => $freedSpace,
            'cutoff_date' => $cutoffDate->toDateString()
        ];
    }

    /**
     * Clean up soft-deleted files
     */
    protected function cleanupDeletedFiles(StorageService $storageService): array
    {
        return array_merge(
            ['type' => 'cleanup_deleted'],
            $storageService->cleanupDeletedFiles($this->tenant)
        );
    }

    /**
     * Clean up orphaned files (files on disk but not in database)
     */
    protected function cleanupOrphanedFiles(StorageService $storageService): array
    {
        $orphanedFiles = $storageService->findOrphanedFiles($this->tenant);
        $cleanedCount = 0;
        $freedSpace = 0;

        foreach ($orphanedFiles as $file) {
            if (\Storage::disk('public')->delete($file['path'])) {
                $cleanedCount++;
                $freedSpace += $file['size'];
            }
        }

        return [
            'type' => 'cleanup_orphaned',
            'cleaned_files' => $cleanedCount,
            'freed_space' => $freedSpace,
            'total_orphaned' => count($orphanedFiles)
        ];
    }

    /**
     * Compress images to save space
     */
    protected function compressImages(StorageService $storageService): array
    {
        $maxSize = $this->parameters['max_size'] ?? 1024 * 1024; // 1MB default
        
        $largeImages = $this->tenant->storageUsage()
            ->where('file_type', 'image')
            ->where('file_size', '>', $maxSize)
            ->where('is_deleted', false)
            ->get();

        $compressedCount = 0;
        $savedSpace = 0;

        foreach ($largeImages as $image) {
            try {
                $originalSize = $image->file_size;
                if ($this->compressImage($image)) {
                    $newSize = \Storage::disk('public')->size($image->file_path);
                    $saved = $originalSize - $newSize;
                    
                    if ($saved > 0) {
                        $image->update(['file_size' => $newSize]);
                        $this->tenant->decrementStorageUsed($saved);
                        $compressedCount++;
                        $savedSpace += $saved;
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Failed to compress image {$image->id}: {$e->getMessage()}");
            }
        }

        return [
            'type' => 'compress_images',
            'compressed_files' => $compressedCount,
            'saved_space' => $savedSpace,
            'total_candidates' => $largeImages->count()
        ];
    }

    /**
     * Archive old files (move to archive storage)
     */
    protected function archiveOldFiles(StorageService $storageService): array
    {
        $daysOld = $this->parameters['days_old'] ?? 365; // 1 year default
        $cutoffDate = now()->subDays($daysOld);
        
        $oldFiles = $this->tenant->storageUsage()
            ->where('created_at', '<', $cutoffDate)
            ->where('is_deleted', false)
            ->whereNotIn('file_type', ['archive', 'backup']) // Don't archive already archived files
            ->get();

        $archivedCount = 0;
        $archivedSpace = 0;

        foreach ($oldFiles as $file) {
            try {
                // Move to archive directory
                $archivePath = 'archive/' . date('Y/m', strtotime($file->created_at));
                if ($storageService->moveFile($file, $archivePath)) {
                    $file->update([
                        'file_type' => 'archive',
                        'metadata' => array_merge($file->metadata ?? [], [
                            'archived_at' => now()->toISOString(),
                            'original_type' => $file->file_type
                        ])
                    ]);
                    
                    $archivedCount++;
                    $archivedSpace += $file->file_size;
                }
            } catch (\Exception $e) {
                Log::warning("Failed to archive file {$file->id}: {$e->getMessage()}");
            }
        }

        return [
            'type' => 'archive_old',
            'archived_files' => $archivedCount,
            'archived_space' => $archivedSpace,
            'total_candidates' => $oldFiles->count(),
            'cutoff_date' => $cutoffDate->toDateString()
        ];
    }

    /**
     * Compress a single image file
     */
    protected function compressImage($imageUsage): bool
    {
        $path = storage_path('app/public/' . $imageUsage->file_path);
        
        if (!file_exists($path)) {
            return false;
        }

        $mimeType = $imageUsage->mime_type;
        $quality = $this->parameters['quality'] ?? 80;

        try {
            switch ($mimeType) {
                case 'image/jpeg':
                    $image = imagecreatefromjpeg($path);
                    if ($image) {
                        imagejpeg($image, $path, $quality);
                        imagedestroy($image);
                        return true;
                    }
                    break;

                case 'image/png':
                    $image = imagecreatefrompng($path);
                    if ($image) {
                        // Convert PNG quality (0-9) from JPEG quality (0-100)
                        $pngQuality = floor((100 - $quality) / 10);
                        imagepng($image, $path, $pngQuality);
                        imagedestroy($image);
                        return true;
                    }
                    break;

                case 'image/webp':
                    $image = imagecreatefromwebp($path);
                    if ($image) {
                        imagewebp($image, $path, $quality);
                        imagedestroy($image);
                        return true;
                    }
                    break;
            }
        } catch (\Exception $e) {
            Log::warning("Image compression failed: {$e->getMessage()}");
        }

        return false;
    }
}