<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\ModuleManager;

class CheckModuleAccess
{
    protected ModuleManager $moduleManager;

    public function __construct(ModuleManager $moduleManager)
    {
        $this->moduleManager = $moduleManager;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $moduleCode): Response
    {
        $tenant = tenant();
        
        if (!$tenant) {
            abort(403, 'No se pudo identificar la empresa.');
        }

        // Verificar si el tenant tiene acceso al módulo
        if (!$this->moduleManager->hasAccess($tenant, $moduleCode)) {
            // Si es una petición AJAX/API, devolver JSON
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Módulo no disponible',
                    'message' => "No tienes acceso al módulo '{$moduleCode}'. Contacta al administrador para habilitarlo.",
                    'module_code' => $moduleCode,
                    'upgrade_required' => true
                ], 403);
            }

            // Si es web, redirigir con mensaje
            return redirect()->route('dashboard')->with('error', 
                "El módulo '{$moduleCode}' no está disponible en tu plan actual. Contacta al administrador para habilitarlo."
            );
        }

        // Registrar el uso del módulo
        if (auth()->check()) {
            $this->moduleManager->logUsage(
                auth()->user(),
                $moduleCode,
                'access',
                [
                    'route' => $request->route()?->getName(),
                    'method' => $request->method(),
                    'ip' => $request->ip(),
                ]
            );
        }

        return $next($request);
    }
}