<?php

namespace App\Services;

use App\Models\ReportTemplate;
use App\Models\ScheduledReport;
use App\Models\ReportExecution;
use App\Models\ReportFilter;
use App\Models\Tenant;
use App\Models\User;
use App\Jobs\GenerateReportJob;
use App\Mail\ReportGeneratedMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ReportService
{
    /**
     * Generate a report manually
     */
    public function generateReport(
        ReportTemplate $template,
        array $parameters = [],
        string $format = 'pdf',
        ?User $user = null,
        ?ScheduledReport $scheduledReport = null
    ): ReportExecution {
        $user = $user ?? auth()->user();
        $tenant = tenant();

        // Validate parameters
        $errors = $template->validateParameters($parameters);
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Invalid parameters: ' . implode(', ', $errors));
        }

        // Create execution record
        $execution = ReportExecution::create([
            'tenant_id' => $tenant->id,
            'scheduled_report_id' => $scheduledReport?->id,
            'report_template_id' => $template->id,
            'user_id' => $user->id,
            'name' => $this->generateReportName($template, $parameters),
            'status' => ReportExecution::STATUS_PENDING,
            'parameters' => $parameters,
            'format' => $format,
        ]);

        // Dispatch job to generate report
        GenerateReportJob::dispatch($execution);

        return $execution;
    }

    /**
     * Execute a report generation synchronously
     */
    public function executeReport(ReportExecution $execution): bool
    {
        try {
            $execution->markAsStarted();

            $template = $execution->reportTemplate;
            $queryInstance = $template->getQueryInstance();

            // Get report data
            $data = $queryInstance->getData($execution->parameters);
            $totalRecords = count($data);

            // Generate file
            $filePath = $this->generateReportFile($template, $data, $execution->parameters, $execution->format);
            $fileSize = Storage::size($filePath);

            // Mark as completed
            $execution->markAsCompleted($filePath, $fileSize, $totalRecords);

            // Send email if it's a scheduled report
            if ($execution->scheduledReport && $execution->scheduledReport->auto_send) {
                $this->sendReportEmail($execution);
            }

            return true;

        } catch (\Exception $e) {
            $execution->markAsFailed($e->getMessage());
            return false;
        }
    }

    /**
     * Generate report file
     */
    protected function generateReportFile(
        ReportTemplate $template,
        array $data,
        array $parameters,
        string $format
    ): string {
        $tenant = tenant();
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = Str::slug($template->name) . "_{$timestamp}";
        
        $directory = "reports/tenant_{$tenant->id}/" . now()->format('Y/m');
        
        switch ($format) {
            case 'pdf':
                return $this->generatePdfReport($template, $data, $parameters, $directory, $filename);
            case 'excel':
                return $this->generateExcelReport($template, $data, $parameters, $directory, $filename);
            case 'csv':
                return $this->generateCsvReport($template, $data, $parameters, $directory, $filename);
            default:
                throw new \InvalidArgumentException("Unsupported format: {$format}");
        }
    }

    /**
     * Generate PDF report
     */
    protected function generatePdfReport(
        ReportTemplate $template,
        array $data,
        array $parameters,
        string $directory,
        string $filename
    ): string {
        $viewTemplate = $template->view_template ?? 'reports.default';
        
        $html = view($viewTemplate, [
            'template' => $template,
            'data' => $data,
            'parameters' => $parameters,
            'tenant' => tenant(),
            'generated_at' => now(),
        ])->render();

        // Use DomPDF or similar library
        $pdf = \PDF::loadHTML($html)->setPaper('a4', 'portrait');
        
        $filePath = "{$directory}/{$filename}.pdf";
        Storage::put($filePath, $pdf->output());

        return $filePath;
    }

    /**
     * Generate Excel report
     */
    protected function generateExcelReport(
        ReportTemplate $template,
        array $data,
        array $parameters,
        string $directory,
        string $filename
    ): string {
        // Use PhpSpreadsheet or Laravel Excel
        $export = new \App\Exports\ReportExport($template, $data, $parameters);
        
        $filePath = "{$directory}/{$filename}.xlsx";
        $fullPath = storage_path('app/' . $filePath);
        
        // Ensure directory exists
        Storage::makeDirectory(dirname($filePath));
        
        \Excel::store($export, $filePath);

        return $filePath;
    }

    /**
     * Generate CSV report
     */
    protected function generateCsvReport(
        ReportTemplate $template,
        array $data,
        array $parameters,
        string $directory,
        string $filename
    ): string {
        $filePath = "{$directory}/{$filename}.csv";
        
        // Ensure directory exists
        Storage::makeDirectory($directory);
        
        $handle = fopen(storage_path('app/' . $filePath), 'w');
        
        // Write headers
        if (!empty($data)) {
            $headers = array_keys($data[0]);
            fputcsv($handle, $headers);
            
            // Write data
            foreach ($data as $row) {
                fputcsv($handle, $row);
            }
        }
        
        fclose($handle);

        return $filePath;
    }

    /**
     * Send report via email
     */
    protected function sendReportEmail(ReportExecution $execution): void
    {
        $scheduledReport = $execution->scheduledReport;
        if (!$scheduledReport) {
            return;
        }

        $recipients = $scheduledReport->recipients ?? [];
        
        foreach ($recipients as $email) {
            try {
                Mail::to($email)->send(new ReportGeneratedMail($execution));
            } catch (\Exception $e) {
                \Log::error('Failed to send report email', [
                    'execution_id' => $execution->id,
                    'email' => $email,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $execution->markAsEmailed();
    }

    /**
     * Create scheduled report
     */
    public function createScheduledReport(array $data): ScheduledReport
    {
        $tenant = tenant();
        $user = auth()->user();

        $scheduledReport = ScheduledReport::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'report_template_id' => $data['report_template_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'frequency' => $data['frequency'],
            'frequency_details' => $data['frequency_details'] ?? [],
            'parameters' => $data['parameters'] ?? [],
            'format' => $data['format'] ?? 'pdf',
            'recipients' => $data['recipients'] ?? [],
            'auto_send' => $data['auto_send'] ?? true,
            'store_file' => $data['store_file'] ?? true,
            'is_active' => $data['is_active'] ?? true,
        ]);

        // Calculate and set next run time
        $scheduledReport->updateNextRun();

        return $scheduledReport;
    }

    /**
     * Process due scheduled reports
     */
    public function processDueReports(): array
    {
        $dueReports = ScheduledReport::due()->get();
        $results = ['processed' => 0, 'failed' => 0];

        foreach ($dueReports as $scheduledReport) {
            try {
                $this->generateReport(
                    $scheduledReport->reportTemplate,
                    $scheduledReport->parameters ?? [],
                    $scheduledReport->format,
                    $scheduledReport->user,
                    $scheduledReport
                );

                $scheduledReport->markAsExecuted();
                $results['processed']++;

            } catch (\Exception $e) {
                \Log::error('Failed to process scheduled report', [
                    'scheduled_report_id' => $scheduledReport->id,
                    'error' => $e->getMessage()
                ]);
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Get report statistics
     */
    public function getReportStatistics(?Tenant $tenant = null): array
    {
        $tenant = $tenant ?? tenant();

        $baseQuery = ReportExecution::where('tenant_id', $tenant->id);

        return [
            'total_executions' => $baseQuery->count(),
            'successful_executions' => $baseQuery->completed()->count(),
            'failed_executions' => $baseQuery->failed()->count(),
            'executions_today' => $baseQuery->whereDate('created_at', today())->count(),
            'executions_this_week' => $baseQuery->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'executions_this_month' => $baseQuery->whereMonth('created_at', now()->month)->count(),
            'active_schedules' => ScheduledReport::where('tenant_id', $tenant->id)->active()->count(),
            'total_schedules' => ScheduledReport::where('tenant_id', $tenant->id)->count(),
            'average_execution_time' => $baseQuery->completed()->avg('execution_time_seconds'),
            'total_storage_used' => $baseQuery->completed()->sum('file_size'),
        ];
    }

    /**
     * Clean up old report files
     */
    public function cleanupOldReports(int $daysOld = 30): array
    {
        $cutoffDate = now()->subDays($daysOld);
        $tenant = tenant();

        $oldExecutions = ReportExecution::where('tenant_id', $tenant->id)
            ->completed()
            ->where('created_at', '<', $cutoffDate)
            ->whereNotNull('file_path')
            ->get();

        $deletedCount = 0;
        $freedSpace = 0;

        foreach ($oldExecutions as $execution) {
            if ($execution->fileExists()) {
                $freedSpace += $execution->file_size;
                $execution->deleteFile();
                $deletedCount++;
            }

            // Clear file path
            $execution->update(['file_path' => null, 'file_size' => null]);
        }

        return [
            'deleted_files' => $deletedCount,
            'freed_space' => $freedSpace,
            'formatted_freed_space' => $this->formatBytes($freedSpace),
        ];
    }

    /**
     * Generate report name
     */
    protected function generateReportName(ReportTemplate $template, array $parameters): string
    {
        $name = $template->name;
        
        // Add date range if present
        if (isset($parameters['date_from']) || isset($parameters['date_to'])) {
            $dateFrom = $parameters['date_from'] ?? 'inicio';
            $dateTo = $parameters['date_to'] ?? 'fin';
            $name .= " ({$dateFrom} - {$dateTo})";
        }

        // Add current timestamp
        $name .= ' - ' . now()->format('Y-m-d H:i');

        return $name;
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
     * Create default report templates
     */
    public function createDefaultTemplates(): void
    {
        $templates = [
            [
                'name' => 'Reporte de Ventas',
                'slug' => 'sales-report',
                'description' => 'Reporte detallado de ventas por período',
                'type' => 'sales',
                'format' => 'pdf',
                'query_class' => 'App\\Services\\Reports\\SalesReportQuery',
                'view_template' => 'reports.sales',
                'default_parameters' => [
                    'date_from' => now()->startOfMonth()->format('Y-m-d'),
                    'date_to' => now()->endOfMonth()->format('Y-m-d'),
                    'include_details' => true,
                ],
                'available_parameters' => [
                    'date_from' => ['type' => 'date', 'required' => true, 'label' => 'Fecha Desde'],
                    'date_to' => ['type' => 'date', 'required' => true, 'label' => 'Fecha Hasta'],
                    'customer_id' => ['type' => 'integer', 'required' => false, 'label' => 'Cliente'],
                    'include_details' => ['type' => 'boolean', 'required' => false, 'label' => 'Incluir Detalles'],
                ],
            ],
            [
                'name' => 'Estado Financiero',
                'slug' => 'financial-statement',
                'description' => 'Estado de resultados y balance general',
                'type' => 'financial',
                'format' => 'pdf',
                'query_class' => 'App\\Services\\Reports\\FinancialReportQuery',
                'view_template' => 'reports.financial',
                'default_parameters' => [
                    'date_from' => now()->startOfYear()->format('Y-m-d'),
                    'date_to' => now()->endOfYear()->format('Y-m-d'),
                ],
                'available_parameters' => [
                    'date_from' => ['type' => 'date', 'required' => true, 'label' => 'Fecha Desde'],
                    'date_to' => ['type' => 'date', 'required' => true, 'label' => 'Fecha Hasta'],
                    'include_balance' => ['type' => 'boolean', 'required' => false, 'label' => 'Incluir Balance'],
                ],
            ],
            [
                'name' => 'Inventario Actual',
                'slug' => 'inventory-report',
                'description' => 'Estado actual del inventario',
                'type' => 'inventory',
                'format' => 'excel',
                'query_class' => 'App\\Services\\Reports\\InventoryReportQuery',
                'view_template' => 'reports.inventory',
                'default_parameters' => [
                    'include_zero_stock' => false,
                    'category_id' => null,
                ],
                'available_parameters' => [
                    'category_id' => ['type' => 'integer', 'required' => false, 'label' => 'Categoría'],
                    'include_zero_stock' => ['type' => 'boolean', 'required' => false, 'label' => 'Incluir Sin Stock'],
                    'low_stock_only' => ['type' => 'boolean', 'required' => false, 'label' => 'Solo Stock Bajo'],
                ],
            ],
        ];

        foreach ($templates as $templateData) {
            ReportTemplate::updateOrCreate(
                ['slug' => $templateData['slug']],
                $templateData
            );
        }
    }
}