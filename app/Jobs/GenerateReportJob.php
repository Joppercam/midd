<?php

namespace App\Jobs;

use App\Models\ReportExecution;
use App\Services\ReportService;
use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected ReportExecution $execution;

    public $timeout = 300; // 5 minutes
    public $maxExceptions = 3;

    public function __construct(ReportExecution $execution)
    {
        $this->execution = $execution;
    }

    public function handle(ReportService $reportService, PushNotificationService $notificationService): void
    {
        try {
            Log::info("Starting report generation", [
                'execution_id' => $this->execution->id,
                'template' => $this->execution->reportTemplate->name,
                'tenant_id' => $this->execution->tenant_id
            ]);

            // Set current tenant context
            app()->instance('currentTenant', $this->execution->tenant);

            // Send progress notification (0%)
            $notificationService->sendProgressUpdate(
                $this->execution->user,
                "report_{$this->execution->id}",
                0,
                "Iniciando generación de reporte: {$this->execution->name}"
            );

            // Generate the report
            $success = $reportService->executeReport($this->execution);

            if ($success) {
                // Send completion notification
                $notificationService->sendProgressUpdate(
                    $this->execution->user,
                    "report_{$this->execution->id}",
                    100,
                    "Reporte generado exitosamente"
                );

                // Send success notification
                $notificationService->sendToUser($this->execution->user, [
                    'type' => 'report_generated',
                    'title' => 'Reporte Generado',
                    'message' => "El reporte '{$this->execution->name}' ha sido generado exitosamente",
                    'data' => [
                        'execution_id' => $this->execution->id,
                        'file_size' => $this->execution->formatted_file_size,
                        'total_records' => $this->execution->total_records,
                        'execution_time' => $this->execution->formatted_execution_time,
                    ],
                    'action_url' => route('reports.executions.show', $this->execution->id),
                    'icon' => 'document-text',
                    'priority' => 'medium'
                ]);

                Log::info("Report generation completed successfully", [
                    'execution_id' => $this->execution->id,
                    'file_size' => $this->execution->file_size,
                    'total_records' => $this->execution->total_records,
                    'execution_time' => $this->execution->execution_time_seconds
                ]);

            } else {
                $this->handleFailure();
            }

        } catch (\Exception $e) {
            Log::error("Report generation failed", [
                'execution_id' => $this->execution->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->execution->markAsFailed($e->getMessage());
            $this->handleFailure();
            
            throw $e;
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Report generation job failed", [
            'execution_id' => $this->execution->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        // Mark execution as failed if not already done
        if ($this->execution->status !== ReportExecution::STATUS_FAILED) {
            $this->execution->markAsFailed($exception->getMessage());
        }

        $this->handleFailure();
    }

    /**
     * Handle failure notifications
     */
    protected function handleFailure(): void
    {
        try {
            $notificationService = app(PushNotificationService::class);

            // Send failure notification to user
            $notificationService->sendToUser($this->execution->user, [
                'type' => 'report_failed',
                'title' => 'Error al Generar Reporte',
                'message' => "Falló la generación del reporte '{$this->execution->name}': {$this->execution->error_message}",
                'data' => [
                    'execution_id' => $this->execution->id,
                    'error_message' => $this->execution->error_message,
                ],
                'action_url' => route('reports.executions.show', $this->execution->id),
                'icon' => 'exclamation-triangle',
                'priority' => 'high'
            ]);

            // Notify admins for persistent failures
            if ($this->attempts() >= $this->maxExceptions) {
                $notificationService->sendToRole('admin', [
                    'type' => 'report_failure_admin',
                    'title' => 'Fallo Crítico en Generación de Reporte',
                    'message' => "El reporte '{$this->execution->name}' ha fallado {$this->attempts()} veces consecutivas",
                    'data' => [
                        'execution_id' => $this->execution->id,
                        'user_name' => $this->execution->user->name,
                        'template_name' => $this->execution->reportTemplate->name,
                        'error_message' => $this->execution->error_message,
                        'attempts' => $this->attempts(),
                    ],
                    'action_url' => route('admin.reports.executions.show', $this->execution->id),
                    'icon' => 'exclamation-circle',
                    'priority' => 'high'
                ], $this->execution->tenant);
            }

        } catch (\Exception $e) {
            Log::error("Failed to send failure notifications", [
                'execution_id' => $this->execution->id,
                'notification_error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Calculate the number of seconds to wait before retrying the job
     */
    public function backoff(): array
    {
        return [30, 60, 120]; // 30 seconds, 1 minute, 2 minutes
    }

    /**
     * Determine if the job should be retried
     */
    public function shouldRetry(\Throwable $exception): bool
    {
        // Don't retry validation errors or parameter errors
        if ($exception instanceof \InvalidArgumentException) {
            return false;
        }

        // Don't retry if template is not found
        if (str_contains($exception->getMessage(), 'not found')) {
            return false;
        }

        // Retry for other types of errors
        return $this->attempts() < $this->maxExceptions;
    }
}