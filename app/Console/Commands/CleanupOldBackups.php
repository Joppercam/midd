<?php

namespace App\Console\Commands;

use App\Models\Backup;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CleanupOldBackups extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'backup:cleanup 
                           {--days=30 : Number of days to keep backups} 
                           {--tenant= : Specific tenant ID to cleanup}
                           {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up old backup files based on retention policy';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $retentionDays = (int) $this->option('days');
        $tenantId = $this->option('tenant');
        $dryRun = $this->option('dry-run');
        
        $this->info("Cleaning up backups older than {$retentionDays} days...");
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No files will be deleted');
        }
        
        $cutoffDate = Carbon::now()->subDays($retentionDays);
        
        try {
            $query = Backup::where('created_at', '<', $cutoffDate)
                ->where('status', 'completed');
                
            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
                $this->info("Filtering by tenant ID: {$tenantId}");
            }
            
            $oldBackups = $query->get();
            
            if ($oldBackups->isEmpty()) {
                $this->info('No old backups found to clean up.');
                return Command::SUCCESS;
            }
            
            $this->info("Found {$oldBackups->count()} old backups to clean up:");
            
            $totalSize = 0;
            $deletedCount = 0;
            $errorCount = 0;
            
            foreach ($oldBackups as $backup) {
                $this->line("- {$backup->name} (ID: {$backup->id}) - {$backup->created_at->format('Y-m-d H:i:s')} - " . $this->formatBytes($backup->file_size));
                $totalSize += $backup->file_size;
                
                if (!$dryRun) {
                    try {
                        $this->deleteBackup($backup);
                        $deletedCount++;
                    } catch (\Exception $e) {
                        $this->error("Failed to delete backup {$backup->id}: {$e->getMessage()}");
                        $errorCount++;
                    }
                }
            }
            
            if ($dryRun) {
                $this->info("\nDRY RUN SUMMARY:");
                $this->info("Would delete {$oldBackups->count()} backups");
                $this->info("Would free up " . $this->formatBytes($totalSize));
            } else {
                $this->info("\nCLEANUP SUMMARY:");
                $this->info("Deleted: {$deletedCount} backups");
                $this->info("Errors: {$errorCount}");
                $this->info("Freed up: " . $this->formatBytes($totalSize));
                
                if ($errorCount > 0) {
                    $this->warn("Some backups could not be deleted. Check logs for details.");
                    return Command::FAILURE;
                }
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Error during backup cleanup: {$e->getMessage()}");
            Log::error('Backup cleanup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Command::FAILURE;
        }
    }
    
    /**
     * Delete a backup and its files
     */
    private function deleteBackup(Backup $backup): void
    {
        // Delete from storage
        if ($backup->file_path && Storage::disk($backup->disk)->exists($backup->file_path)) {
            Storage::disk($backup->disk)->delete($backup->file_path);
        }
        
        // Delete record
        $backup->delete();
        
        Log::info('Old backup deleted', [
            'backup_id' => $backup->id,
            'tenant_id' => $backup->tenant_id,
            'file_path' => $backup->file_path,
            'created_at' => $backup->created_at
        ]);
    }
    
    /**
     * Format bytes into human readable format
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes == 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $exp = floor(log($bytes, 1024));
        
        return round($bytes / pow(1024, $exp), 2) . ' ' . $units[$exp];
    }
}