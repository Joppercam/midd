<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // Log estructurado para todas las excepciones
            if ($this->shouldReport($e)) {
                $this->logStructuredException($e);
            }
        });

        $this->renderable(function (Throwable $e, $request) {
            return $this->handleInertiaExceptions($e, $request);
        });
    }

    /**
     * Log estructurado de excepciones
     */
    protected function logStructuredException(Throwable $e): void
    {
        $context = [
            'exception_id' => Str::uuid()->toString(),
            'exception_class' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => collect($e->getTrace())->take(5)->toArray(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'user_id' => auth()->id(),
            'tenant_id' => auth()->user()?->tenant_id,
            'input' => $this->sanitizeInput(request()->except($this->dontFlash)),
            'headers' => $this->sanitizeHeaders(request()->headers->all()),
            'timestamp' => now()->toIso8601String(),
        ];

        // Información adicional según el tipo de excepción
        if ($e instanceof ValidationException) {
            $context['validation_errors'] = $e->errors();
        } elseif ($e instanceof ModelNotFoundException) {
            $context['model'] = $e->getModel();
            $context['ids'] = $e->getIds();
        } elseif ($e instanceof HttpException) {
            $context['status_code'] = $e->getStatusCode();
            $context['headers'] = $e->getHeaders();
        }

        Log::channel('exceptions')->error('Exception occurred', $context);
    }

    /**
     * Manejo de excepciones para Inertia
     */
    protected function handleInertiaExceptions(Throwable $e, $request)
    {
        $response = parent::render($request, $e);

        // Si es una petición Inertia, personalizar la respuesta
        if ($request->header('X-Inertia')) {
            if ($e instanceof ValidationException) {
                return $response;
            }

            if ($e instanceof AuthenticationException) {
                return redirect()->guest(route('login'))
                    ->with('error', 'Tu sesión ha expirado. Por favor, inicia sesión nuevamente.');
            }

            if ($e instanceof AuthorizationException) {
                return back()->with('error', 'No tienes permisos para realizar esta acción.');
            }

            if ($e instanceof ModelNotFoundException) {
                return back()->with('error', 'El recurso solicitado no fue encontrado.');
            }

            if ($e instanceof NotFoundHttpException) {
                return Inertia::render('Error/404', [
                    'status' => 404,
                    'message' => 'Página no encontrada'
                ])->toResponse($request)->setStatusCode(404);
            }

            // Para errores del servidor en producción
            if (app()->environment('production') && $response->status() >= 500) {
                $errorId = Str::uuid()->toString();
                
                Log::channel('exceptions')->critical('Critical error occurred', [
                    'error_id' => $errorId,
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'user_id' => auth()->id(),
                ]);

                return Inertia::render('Error/500', [
                    'status' => 500,
                    'message' => 'Ha ocurrido un error en el servidor.',
                    'error_id' => $errorId
                ])->toResponse($request)->setStatusCode(500);
            }
        }

        return $response;
    }

    /**
     * Sanitizar input para logs
     */
    protected function sanitizeInput(array $input): array
    {
        $sensitive = ['password', 'password_confirmation', 'credit_card', 'cvv', 'token'];
        
        foreach ($input as $key => $value) {
            if (in_array($key, $sensitive) || str_contains($key, 'password')) {
                $input[$key] = '***REDACTED***';
            }
        }

        return $input;
    }

    /**
     * Sanitizar headers para logs
     */
    protected function sanitizeHeaders(array $headers): array
    {
        $sensitive = ['authorization', 'cookie', 'x-csrf-token'];
        
        foreach ($headers as $key => $value) {
            if (in_array(strtolower($key), $sensitive)) {
                $headers[$key] = ['***REDACTED***'];
            }
        }

        return $headers;
    }

    /**
     * Determinar si la excepción debe ser reportada
     */
    public function shouldReport(Throwable $e): bool
    {
        // No reportar estas excepciones comunes
        $dontReport = [
            ValidationException::class,
            AuthenticationException::class,
            AuthorizationException::class,
            NotFoundHttpException::class,
        ];

        foreach ($dontReport as $type) {
            if ($e instanceof $type) {
                return false;
            }
        }

        return parent::shouldReport($e);
    }
}