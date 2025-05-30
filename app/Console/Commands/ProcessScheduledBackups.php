<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BackupSchedule;
use App\Services\BackupService;
use App\Services\EmailNotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ProcessScheduledBackups extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'backups:process-scheduled
                            {--force : Force execution regardless of schedule}
                            {--tenant= : Process only for specific tenant}';

    /**
     * The console command description.
     */
    protected $description = 'Process scheduled backups for all tenants';

    protected BackupService $backupService;
    protected EmailNotificationService $emailService;

    public function __construct(BackupService $backupService, EmailNotificationService $emailService)
    {
        parent::__construct();
        $this->backupService = $backupService;
        $this->emailService = $emailService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting scheduled backup processing...');
        
        $tenantFilter = $this->option('tenant');
        $force = $this->option('force');
        
        // Get scheduled backups that need to run
        $query = BackupSchedule::where('is_active', true);
        
        if ($tenantFilter) {
            $query->where('tenant_id', $tenantFilter);
        }
        
        if (!$force) {
            $query->where('next_run', '<=', now());
        }
        
        $schedules = $query->with('tenant')->get();
        
        if ($schedules->isEmpty()) {
            $this->info('No scheduled backups found to process.');
            return self::SUCCESS;
        }
        
        $this->info("Found {$schedules->count()} scheduled backup(s) to process.");
        
        $successful = 0;
        $failed = 0;
        
        foreach ($schedules as $schedule) {
            $this->processSchedule($schedule, $successful, $failed);
        }
        
        $this->info("Backup processing completed. Successful: {$successful}, Failed: {$failed}");
        
        return self::SUCCESS;
    }

    /**
     * Process individual backup schedule
     */
    protected function processSchedule(BackupSchedule $schedule, int &$successful, int &$failed): void
    {
        try {
            $this->line("Processing backup: {$schedule->name} (Tenant: {$schedule->tenant->name})");
            
            // Set tenant context
            app()->instance('currentTenant', $schedule->tenant);
            
            // Create backup
            $backup = $this->backupService->createBackup(
                $schedule->type,
                "Programado: {$schedule->name}"
            );
            
            if ($backup && $backup->status === 'completed') {
                $this->info("✓ Backup completed successfully: {$backup->filename}");
                $successful++;
                
                // Send success notification
                $this->sendNotification($schedule, $backup, true);
                
                // Update next run time
                $this->updateNextRun($schedule);
                
                // Cleanup old backups based on retention
                $this->cleanupOldBackups($schedule);
                
            } else {
                throw new \Exception('Backup creation failed or incomplete');
            }
            
        } catch (\Exception $e) {
            $this->error("✗ Backup failed for {$schedule->name}: " . $e->getMessage());
            $failed++;
            
            // Log the error
            Log::channel('exceptions')->error('Scheduled backup failed', [
                'schedule_id' => $schedule->id,
                'schedule_name' => $schedule->name,
                'tenant_id' => $schedule->tenant_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Send failure notification
            $this->sendNotification($schedule, null, false, $e->getMessage());
            
            // Update failure count
            $schedule->increment('consecutive_failures');
            
            // Deactivate if too many failures
            if ($schedule->consecutive_failures >= 5) {
                $schedule->update(['is_active' => false]);
                $this->warn("Schedule {$schedule->name} deactivated after 5 consecutive failures");
            }
        }
    }

    /**
     * Update next run time for schedule
     */
    protected function updateNextRun(BackupSchedule $schedule): void
    {
        $now = now();
        $time = Carbon::createFromFormat('H:i', $schedule->time);
        
        switch ($schedule->frequency) {
            case 'daily':
                $nextRun = $now->copy()->addDay()->setTime($time->hour, $time->minute);
                break;
                
            case 'weekly':
                $dayOfWeek = $schedule->day_of_week ?? 1;
                $nextRun = $now->copy()->setTime($time->hour, $time->minute);
                $nextRun = $nextRun->next(Carbon::MONDAY + $dayOfWeek - 1);
                break;
                
            case 'monthly':
                $dayOfMonth = $schedule->day_of_month ?? 1;
                $nextRun = $now->copy()->addMonth()->startOfMonth()
                    ->addDays($dayOfMonth - 1)
                    ->setTime($time->hour, $time->minute);
                break;
                
            default:
                $nextRun = $now->copy()->addDay();
        }
        
        $schedule->update([
            'next_run' => $nextRun,
            'last_run' => $now,
            'consecutive_failures' => 0 // Reset on successful run
        ]);
        
        $this->line("Next run scheduled for: {$nextRun->format('Y-m-d H:i:s')}");
    }

    /**
     * Cleanup old backups based on retention policy
     */
    protected function cleanupOldBackups(BackupSchedule $schedule): void
    {
        try {
            $cutoffDate = now()->subDays($schedule->retention_days);
            
            $deleted = $this->backupService->cleanupOldBackups($schedule->retention_days);
            
            if ($deleted > 0) {
                $this->line("Cleaned up {$deleted} old backup(s) based on retention policy");
            }
            
        } catch (\Exception $e) {
            $this->warn("Failed to cleanup old backups: " . $e->getMessage());
        }
    }

    /**
     * Send notification for backup result
     */
    protected function sendNotification(BackupSchedule $schedule, $backup = null, bool $success = true, string $errorMessage = ''): void
    {
        if (empty($schedule->notifications)) {
            return;
        }
        
        try {
            // Set tenant context for email service
            app()->instance('currentTenant', $schedule->tenant);
            
            $subject = $success 
                ? "✅ Backup Completado: {$schedule->name}"
                : "❌ Backup Fallido: {$schedule->name}";
            
            $data = [
                'schedule' => $schedule,
                'backup' => $backup,
                'success' => $success,
                'error_message' => $errorMessage,
                'tenant' => $schedule->tenant
            ];
            
            foreach ($schedule->notifications as $email) {
                $this->emailService->sendNotification(
                    $email,
                    $subject,
                    'emails.backup-notification',
                    $data
                );
            }
            
        } catch (\Exception $e) {
            $this->warn("Failed to send notification: " . $e->getMessage());
        }
    }

    /**
     * Get backup statistics for reporting
     */
    protected function getBackupStatistics(): array
    {
        $stats = [
            'total_schedules' => BackupSchedule::where('is_active', true)->count(),
            'pending_backups' => BackupSchedule::where('is_active', true)
                ->where('next_run', '<=', now())
                ->count(),
            'failed_schedules' => BackupSchedule::where('consecutive_failures', '>=', 3)->count(),
        ];
        
        return $stats;
    }

    /**
     * Display backup schedule status
     */
    protected function displayScheduleStatus(): void
    {
        $stats = $this->getBackupStatistics();
        
        $this->table(
            ['Metric', 'Count'],
            [
                ['Active Schedules', $stats['total_schedules']],
                ['Pending Backups', $stats['pending_backups']],
                ['Failed Schedules', $stats['failed_schedules']]
            ]
        );
    }
}