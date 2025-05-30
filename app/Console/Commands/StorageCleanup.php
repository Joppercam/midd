<?php

namespace App\Console\Commands;

use App\Jobs\StorageCleanupJob;
use App\Models\Tenant;
use Illuminate\Console\Command;

class StorageCleanup extends Command
{
    protected $signature = 'storage:cleanup 
                            {--tenant= : Specific tenant ID to clean up}
                            {--type=cleanup_temp : Type of cleanup (cleanup_temp, cleanup_deleted, compress_images, archive_old)}
                            {--days-old=30 : Days old for cleanup operations}
                            {--quality=80 : Image compression quality (1-100)}
                            {--dry-run : Show what would be cleaned without actually doing it}';

    protected $description = 'Clean up storage for tenants';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $type = $this->option('type');
        $daysOld = (int) $this->option('days-old');
        $quality = (int) $this->option('quality');
        $dryRun = $this->option('dry-run');

        // Validate cleanup type
        $validTypes = ['cleanup_temp', 'cleanup_deleted', 'compress_images', 'archive_old', 'cleanup_orphaned'];
        if (!in_array($type, $validTypes)) {
            $this->error("Invalid cleanup type. Valid types: " . implode(', ', $validTypes));
            return 1;
        }

        // Get tenants to process
        if ($tenantId) {
            $tenants = Tenant::where('id', $tenantId)->get();
            if ($tenants->isEmpty()) {
                $this->error("Tenant not found: {$tenantId}");
                return 1;
            }
        } else {
            $tenants = Tenant::where('is_active', true)->get();
        }

        $this->info("Processing {$tenants->count()} tenant(s) for {$type} cleanup...");

        $parameters = [
            'days_old' => $daysOld,
            'quality' => $quality,
        ];

        foreach ($tenants as $tenant) {
            if ($dryRun) {
                $this->info("DRY RUN: Would clean up storage for tenant {$tenant->name} ({$tenant->id})");
                $this->showCleanupPreview($tenant, $type, $parameters);
            } else {
                $this->info("Dispatching cleanup job for tenant {$tenant->name} ({$tenant->id})");
                StorageCleanupJob::dispatch($tenant, $type, $parameters);
            }
        }

        if (!$dryRun) {
            $this->info("Cleanup jobs dispatched successfully!");
        }

        return 0;
    }

    /**
     * Show what would be cleaned up in dry run mode
     */
    protected function showCleanupPreview(Tenant $tenant, string $type, array $parameters): void
    {
        switch ($type) {
            case 'cleanup_temp':
                $cutoffDate = now()->subDays($parameters['days_old']);
                $count = $tenant->storageUsage()
                    ->where('file_type', 'temp')
                    ->where('created_at', '<', $cutoffDate)
                    ->count();
                $this->line("  - Would clean {$count} temporary files older than {$parameters['days_old']} days");
                break;

            case 'cleanup_deleted':
                $count = $tenant->storageUsage()->deleted()->count();
                $this->line("  - Would clean {$count} soft-deleted files");
                break;

            case 'compress_images':
                $count = $tenant->storageUsage()
                    ->where('file_type', 'image')
                    ->where('file_size', '>', 1024 * 1024) // 1MB
                    ->where('is_deleted', false)
                    ->count();
                $this->line("  - Would compress {$count} large images");
                break;

            case 'archive_old':
                $cutoffDate = now()->subDays($parameters['days_old']);
                $count = $tenant->storageUsage()
                    ->where('created_at', '<', $cutoffDate)
                    ->where('is_deleted', false)
                    ->whereNotIn('file_type', ['archive', 'backup'])
                    ->count();
                $this->line("  - Would archive {$count} files older than {$parameters['days_old']} days");
                break;
        }

        // Show current storage usage
        $this->line("  - Current storage: {$tenant->formatted_storage_used} / {$tenant->formatted_max_storage} ({$tenant->storage_usage_percentage}%)");
    }
}