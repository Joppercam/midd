<?php

namespace App\Console\Commands;

use App\Services\ReportService;
use App\Models\ScheduledReport;
use Illuminate\Console\Command;

class ProcessScheduledReports extends Command
{
    protected $signature = 'reports:process-scheduled 
                            {--tenant= : Process reports for specific tenant}
                            {--report= : Process specific scheduled report}
                            {--dry-run : Show what would be processed without executing}
                            {--force : Force processing even if not due}';

    protected $description = 'Process scheduled reports that are due for execution';

    public function handle(ReportService $reportService): int
    {
        $tenantId = $this->option('tenant');
        $reportId = $this->option('report');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info('Processing scheduled reports...');

        // Build query
        $query = ScheduledReport::with(['reportTemplate', 'user', 'tenant']);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if ($reportId) {
            $query->where('id', $reportId);
        } else {
            // Only get due reports unless forced
            if (!$force) {
                $query->due();
            } else {
                $query->active();
            }
        }

        $scheduledReports = $query->get();

        if ($scheduledReports->isEmpty()) {
            $this->info('No scheduled reports to process.');
            return 0;
        }

        $this->info("Found {$scheduledReports->count()} scheduled report(s) to process:");

        foreach ($scheduledReports as $scheduledReport) {
            $this->showReportInfo($scheduledReport);
        }

        if ($dryRun) {
            $this->info('DRY RUN: No reports were actually processed.');
            return 0;
        }

        // Confirm processing unless specific report was requested
        if (!$reportId && !$this->confirm('Proceed with processing these reports?')) {
            $this->info('Operation cancelled.');
            return 0;
        }

        // Process reports
        $processed = 0;
        $failed = 0;

        foreach ($scheduledReports as $scheduledReport) {
            try {
                // Set tenant context
                app()->instance('currentTenant', $scheduledReport->tenant);

                $this->info("Processing: {$scheduledReport->name}");

                $execution = $reportService->generateReport(
                    $scheduledReport->reportTemplate,
                    $scheduledReport->parameters ?? [],
                    $scheduledReport->format,
                    $scheduledReport->user,
                    $scheduledReport
                );

                // Update next run time
                if (!$force) {
                    $scheduledReport->markAsExecuted();
                }

                $this->info("✓ Queued report generation (Execution ID: {$execution->id})");
                $processed++;

            } catch (\Exception $e) {
                $this->error("✗ Failed to process {$scheduledReport->name}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Processing completed:");
        $this->info("- Successfully processed: {$processed}");
        if ($failed > 0) {
            $this->error("- Failed: {$failed}");
        }

        return $failed > 0 ? 1 : 0;
    }

    protected function showReportInfo(ScheduledReport $scheduledReport): void
    {
        $this->table(
            ['Property', 'Value'],
            [
                ['Name', $scheduledReport->name],
                ['Tenant', $scheduledReport->tenant->name],
                ['Template', $scheduledReport->reportTemplate->name],
                ['Frequency', $scheduledReport->frequency_description],
                ['Format', strtoupper($scheduledReport->format)],
                ['Recipients', $scheduledReport->recipients_count],
                ['Next Run', $scheduledReport->next_run_at ? $scheduledReport->next_run_at->format('Y-m-d H:i:s') : 'Not scheduled'],
                ['Last Run', $scheduledReport->last_run_at ? $scheduledReport->last_run_at->format('Y-m-d H:i:s') : 'Never'],
                ['Status', $scheduledReport->is_active ? 'Active' : 'Inactive'],
            ]
        );
        $this->line('');
    }
}