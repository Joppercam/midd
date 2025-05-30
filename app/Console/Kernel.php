<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Process scheduled backups every hour
        $schedule->command('backup:scheduled')
            ->hourly()
            ->withoutOverlapping()
            ->onFailure(function () {
                \Log::error('Scheduled backup processing failed');
            });
        
        // Clean up old backups daily at 3 AM
        $schedule->command('backup:cleanup')
            ->dailyAt('03:00')
            ->withoutOverlapping();
        
        // Clean up old audit logs daily at 2 AM
        $schedule->command('audit:cleanup')
            ->dailyAt('02:00')
            ->withoutOverlapping();
        
        // Process webhook calls every 5 minutes
        $schedule->command('webhooks:process')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->onFailure(function () {
                \Log::error('Webhook processing failed');
            });
        
        // Clean up temporary files daily at 4 AM
        $schedule->command('storage:cleanup --type=cleanup_temp --days-old=7')
            ->dailyAt('04:00')
            ->withoutOverlapping();
        
        // Clean up deleted files weekly on Sunday at 5 AM
        $schedule->command('storage:cleanup --type=cleanup_deleted')
            ->weeklyOn(0, '05:00')
            ->withoutOverlapping();
        
        // Archive old files monthly on the 1st at 6 AM
        $schedule->command('storage:cleanup --type=archive_old --days-old=365')
            ->monthlyOn(1, '06:00')
            ->withoutOverlapping();
        
        // Process scheduled reports every 30 minutes
        $schedule->command('reports:process-scheduled')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->onFailure(function () {
                \Log::error('Scheduled reports processing failed');
            });
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}