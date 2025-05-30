<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class CleanupAuditLogs extends Command
{
    protected $signature = 'audit:cleanup';
    protected $description = 'Clean up old audit logs based on retention settings';

    public function handle()
    {
        $this->info('Cleaning up old audit logs...');
        
        $count = AuditLog::cleanup();
        
        $this->info("Removed {$count} old audit log entries");
        
        return Command::SUCCESS;
    }
}