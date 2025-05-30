<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Traits\LogsActivity;

class ErrorHandlingService
{
    use LogsActivity;

    /**
     * Manejar error de validación
     */
    public function handleValidationError(array $errors, string $context = ''): array
    {
        $errorId = Str::uuid()->toString();
        
        $this->logActivity('validation_error', [
            'error_id' => $errorId,
            'context' => $context,
            'errors' => $errors,
        ], 'warning');

        return [
            'success' => false,
            'error_id' => $errorId,
            'message' => 'Los datos proporcionados no son válidos.',
            'errors' => $errors,
        ];
    }

    /**
     * Manejar error de negocio
     */
    public function handleBusinessError(string $message, string $code = 'BUSINESS_ERROR', array $details = []): array
    {
        $errorId = Str::uuid()->toString();
        
        $this->logActivity('business_error', [
            'error_id' => $errorId,
            'code' => $code,
            'message' => $message,
            'details' => $details,
        ], 'error');

        return [
            'success' => false,
            'error_id' => $errorId,
            'code' => $code,
            'message' => $message,
            'details' => $details,
        ];
    }

    /**
     * Manejar error de sistema
     */
    public function handleSystemError(\Exception $exception, string $userMessage = null): array
    {
        $errorId = Str::uuid()->toString();
        
        $this->logError('System error occurred', $exception, [
            'error_id' => $errorId,
        ]);

        // Notificar a los administradores si es crítico
        if ($this->isCriticalError($exception)) {
            $this->notifyAdministrators($errorId, $exception);
        }

        $message = $userMessage ?? 'Ha ocurrido un error inesperado. Por favor, intenta nuevamente.';
        
        if (app()->environment('production')) {
            return [
                'success' => false,
                'error_id' => $errorId,
                'message' => $message,
                'support_message' => "Si el problema persiste, contacta al soporte con el ID: {$errorId}",
            ];
        }

        // En desarrollo, incluir más detalles
        return [
            'success' => false,
            'error_id' => $errorId,
            'message' => $message,
            'debug' => [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ],
        ];
    }

    /**
     * Manejar error de autorización
     */
    public function handleAuthorizationError(string $action = '', $resource = null): array
    {
        $errorId = Str::uuid()->toString();
        
        $this->logSecurity('unauthorized_access', [
            'error_id' => $errorId,
            'action' => $action,
            'resource' => $resource ? get_class($resource) : null,
            'resource_id' => $resource?->getKey(),
        ]);

        return [
            'success' => false,
            'error_id' => $errorId,
            'message' => 'No tienes permisos para realizar esta acción.',
            'code' => 'UNAUTHORIZED',
        ];
    }

    /**
     * Manejar error de recurso no encontrado
     */
    public function handleNotFoundError(string $resourceType, $identifier = null): array
    {
        $errorId = Str::uuid()->toString();
        
        $this->logActivity('resource_not_found', [
            'error_id' => $errorId,
            'resource_type' => $resourceType,
            'identifier' => $identifier,
        ], 'warning');

        return [
            'success' => false,
            'error_id' => $errorId,
            'message' => "El {$resourceType} solicitado no fue encontrado.",
            'code' => 'NOT_FOUND',
        ];
    }

    /**
     * Manejar error de límite excedido
     */
    public function handleRateLimitError(string $action, int $limit, int $current): array
    {
        $errorId = Str::uuid()->toString();
        
        $this->logSecurity('rate_limit_exceeded', [
            'error_id' => $errorId,
            'action' => $action,
            'limit' => $limit,
            'current' => $current,
        ]);

        return [
            'success' => false,
            'error_id' => $errorId,
            'message' => 'Has excedido el límite de solicitudes. Por favor, intenta más tarde.',
            'code' => 'RATE_LIMIT_EXCEEDED',
            'retry_after' => 60, // segundos
        ];
    }

    /**
     * Manejar error de timeout
     */
    public function handleTimeoutError(string $operation, float $duration): array
    {
        $errorId = Str::uuid()->toString();
        
        $this->logPerformanceMetrics($operation, $duration * 1000, 0, 'timeout', [
            'error_id' => $errorId,
        ]);

        return [
            'success' => false,
            'error_id' => $errorId,
            'message' => 'La operación tardó demasiado tiempo en completarse. Por favor, intenta nuevamente.',
            'code' => 'TIMEOUT',
        ];
    }

    /**
     * Verificar si es un error crítico
     */
    protected function isCriticalError(\Exception $exception): bool
    {
        $criticalExceptions = [
            \PDOException::class,
            \Illuminate\Database\QueryException::class,
            \Illuminate\Queue\MaxAttemptsExceededException::class,
            \Illuminate\Redis\Connections\ConnectionException::class,
        ];

        foreach ($criticalExceptions as $criticalException) {
            if ($exception instanceof $criticalException) {
                return true;
            }
        }

        return false;
    }

    /**
     * Notificar a los administradores
     */
    protected function notifyAdministrators(string $errorId, \Exception $exception): void
    {
        try {
            // Aquí se podría implementar notificación por email, Slack, etc.
            Log::channel('slack')->critical('Critical error in production', [
                'error_id' => $errorId,
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'url' => request()->fullUrl(),
                'user_id' => auth()->id(),
            ]);
        } catch (\Exception $e) {
            // Si falla la notificación, solo loguear
            Log::error('Failed to notify administrators', [
                'original_error_id' => $errorId,
                'notification_error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Formatear respuesta de éxito con manejo de errores
     */
    public function wrapResponse($data, string $message = 'Operación exitosa'): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}