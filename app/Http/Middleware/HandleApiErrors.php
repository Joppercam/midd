<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class HandleApiErrors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
            
            // Si es una respuesta de error, formatearla
            if ($response instanceof JsonResponse && $response->getStatusCode() >= 400) {
                return $this->formatErrorResponse($response, $request);
            }
            
            return $response;
        } catch (\Exception $e) {
            return $this->handleException($e, $request);
        }
    }

    /**
     * Formatear respuesta de error
     */
    protected function formatErrorResponse(JsonResponse $response, Request $request): JsonResponse
    {
        $data = $response->getData(true);
        $statusCode = $response->getStatusCode();
        
        $errorResponse = [
            'success' => false,
            'error' => [
                'code' => $this->getErrorCode($statusCode),
                'message' => $data['message'] ?? $this->getDefaultMessage($statusCode),
                'status' => $statusCode,
                'timestamp' => now()->toIso8601String(),
                'path' => $request->path(),
                'method' => $request->method(),
                'request_id' => $request->header('X-Request-ID') ?? Str::uuid()->toString(),
            ]
        ];

        // Agregar detalles adicionales si existen
        if (isset($data['errors'])) {
            $errorResponse['error']['details'] = $data['errors'];
        }

        if (app()->environment('local') && isset($data['exception'])) {
            $errorResponse['error']['debug'] = [
                'exception' => $data['exception'],
                'file' => $data['file'] ?? null,
                'line' => $data['line'] ?? null,
                'trace' => $data['trace'] ?? null,
            ];
        }

        return response()->json($errorResponse, $statusCode);
    }

    /**
     * Manejar excepciones
     */
    protected function handleException(\Exception $e, Request $request): JsonResponse
    {
        $errorId = Str::uuid()->toString();
        $statusCode = $this->getStatusCode($e);
        
        // Log del error
        Log::channel('exceptions')->error('API Exception', [
            'error_id' => $errorId,
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_id' => auth()->id(),
        ]);

        $errorResponse = [
            'success' => false,
            'error' => [
                'id' => $errorId,
                'code' => $this->getErrorCode($statusCode),
                'message' => $this->getExceptionMessage($e),
                'status' => $statusCode,
                'timestamp' => now()->toIso8601String(),
                'path' => $request->path(),
                'method' => $request->method(),
            ]
        ];

        // En desarrollo, agregar información de debug
        if (app()->environment('local')) {
            $errorResponse['error']['debug'] = [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => collect($e->getTrace())->take(5)->toArray(),
            ];
        }

        return response()->json($errorResponse, $statusCode);
    }

    /**
     * Obtener código de estado HTTP según la excepción
     */
    protected function getStatusCode(\Exception $e): int
    {
        if ($e instanceof \Illuminate\Validation\ValidationException) {
            return 422;
        }
        
        if ($e instanceof \Illuminate\Auth\AuthenticationException) {
            return 401;
        }
        
        if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
            return 403;
        }
        
        if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            return 404;
        }
        
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
            return $e->getStatusCode();
        }
        
        return 500;
    }

    /**
     * Obtener código de error según el status HTTP
     */
    protected function getErrorCode(int $statusCode): string
    {
        $codes = [
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            422 => 'VALIDATION_ERROR',
            429 => 'TOO_MANY_REQUESTS',
            500 => 'INTERNAL_SERVER_ERROR',
            502 => 'BAD_GATEWAY',
            503 => 'SERVICE_UNAVAILABLE',
        ];

        return $codes[$statusCode] ?? 'UNKNOWN_ERROR';
    }

    /**
     * Obtener mensaje por defecto según el status HTTP
     */
    protected function getDefaultMessage(int $statusCode): string
    {
        $messages = [
            400 => 'La solicitud contiene datos inválidos.',
            401 => 'No estás autenticado.',
            403 => 'No tienes permisos para acceder a este recurso.',
            404 => 'El recurso solicitado no fue encontrado.',
            405 => 'Método HTTP no permitido.',
            422 => 'Los datos proporcionados no son válidos.',
            429 => 'Demasiadas solicitudes. Por favor, intenta más tarde.',
            500 => 'Error interno del servidor.',
            502 => 'Error de gateway.',
            503 => 'Servicio no disponible temporalmente.',
        ];

        return $messages[$statusCode] ?? 'Ha ocurrido un error inesperado.';
    }

    /**
     * Obtener mensaje de excepción apropiado
     */
    protected function getExceptionMessage(\Exception $e): string
    {
        // En producción, no revelar detalles de excepciones internas
        if (app()->environment('production') && $this->getStatusCode($e) >= 500) {
            return 'Ha ocurrido un error interno. Por favor, contacta al soporte si el problema persiste.';
        }

        return $e->getMessage() ?: $this->getDefaultMessage($this->getStatusCode($e));
    }
}