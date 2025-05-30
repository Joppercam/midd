<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait LogsActivity
{
    /**
     * Log de actividad estructurado
     */
    protected function logActivity(string $action, array $data = [], string $level = 'info'): void
    {
        $context = [
            'activity_id' => Str::uuid()->toString(),
            'action' => $action,
            'class' => static::class,
            'data' => $data,
            'user_id' => auth()->id(),
            'tenant_id' => auth()->user()?->tenant_id,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ];

        Log::channel('structured')->{$level}("Activity: {$action}", $context);
    }

    /**
     * Log de rendimiento
     */
    protected function logPerformance(string $operation, callable $callback, array $context = [])
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        try {
            $result = $callback();
            
            $duration = (microtime(true) - $startTime) * 1000; // en milisegundos
            $memoryUsed = (memory_get_usage(true) - $startMemory) / 1024 / 1024; // en MB

            $this->logPerformanceMetrics($operation, $duration, $memoryUsed, 'success', $context);

            return $result;
        } catch (\Exception $e) {
            $duration = (microtime(true) - $startTime) * 1000;
            $memoryUsed = (memory_get_usage(true) - $startMemory) / 1024 / 1024;

            $this->logPerformanceMetrics($operation, $duration, $memoryUsed, 'error', array_merge($context, [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
            ]));

            throw $e;
        }
    }

    /**
     * Log de métricas de rendimiento
     */
    protected function logPerformanceMetrics(
        string $operation, 
        float $duration, 
        float $memoryUsed, 
        string $status, 
        array $context = []
    ): void {
        $performanceData = [
            'operation' => $operation,
            'duration_ms' => round($duration, 2),
            'memory_used_mb' => round($memoryUsed, 2),
            'status' => $status,
            'threshold_exceeded' => $duration > 1000, // más de 1 segundo
            'class' => static::class,
            'context' => $context,
        ];

        $channel = $duration > 5000 ? 'error' : ($duration > 1000 ? 'warning' : 'info');
        
        Log::channel('performance')->{$channel}("Performance: {$operation}", $performanceData);
    }

    /**
     * Log de seguridad
     */
    protected function logSecurity(string $event, array $data = [], string $level = 'warning'): void
    {
        $context = [
            'security_event' => $event,
            'user_id' => auth()->id(),
            'user_email' => auth()->user()?->email,
            'tenant_id' => auth()->user()?->tenant_id,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'referer' => request()->header('referer'),
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
        ];

        Log::channel('security')->{$level}("Security Event: {$event}", $context);
    }

    /**
     * Log de auditoría
     */
    protected function logAudit(string $action, $model = null, array $changes = []): void
    {
        $context = [
            'audit_id' => Str::uuid()->toString(),
            'action' => $action,
            'user_id' => auth()->id(),
            'user_email' => auth()->user()?->email,
            'tenant_id' => auth()->user()?->tenant_id,
            'ip' => request()->ip(),
        ];

        if ($model) {
            $context['model'] = [
                'type' => get_class($model),
                'id' => $model->getKey(),
                'attributes' => $model->getAttributes(),
            ];
        }

        if (!empty($changes)) {
            $context['changes'] = $changes;
        }

        Log::channel('audit')->info("Audit: {$action}", $context);
    }

    /**
     * Log de errores con contexto adicional
     */
    protected function logError(string $message, \Throwable $exception = null, array $context = []): void
    {
        $errorContext = [
            'error_id' => Str::uuid()->toString(),
            'message' => $message,
            'class' => static::class,
            'user_id' => auth()->id(),
            'tenant_id' => auth()->user()?->tenant_id,
            'context' => $context,
        ];

        if ($exception) {
            $errorContext['exception'] = [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => collect($exception->getTrace())->take(5)->toArray(),
            ];
        }

        Log::channel('exceptions')->error($message, $errorContext);
    }
}