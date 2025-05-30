<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use App\Models\Backup;
use Illuminate\Console\Command;

class ProcessBackups extends Command
{
    protected $signature = 'backups:process';
    protected $description = 'Process scheduled backups and cleanup expired ones';

    protected BackupService $backupService;

    public function __construct(BackupService $backupService)
    {
        parent::__construct();
        $this->backupService = $backupService;
    }

    public function handle()
    {
        $this->info('Processing scheduled backups...');
        
        // Process scheduled backups
        $results = $this->backupService->processScheduledBackups();
        
        $this->info(sprintf(
            'Processed %d schedules: %d successful, %d failed',
            $results['processed'],
            $results['successful'],
            $results['failed']
        ));
        
        // Cleanup expired backups
        $this->info('Cleaning up expired backups...');
        $cleaned = Backup::cleanup();
        $this->info("Removed {$cleaned} expired backups");
        
        return Command::SUCCESS;
    }
}