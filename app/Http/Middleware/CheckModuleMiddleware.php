<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleMiddleware
{
    public function handle(Request $request, Closure $next, string $module = null): Response
    {
        // Por ahora, simplemente permite el acceso
        // Aquí puedes implementar la lógica de validación de módulos
        
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        // Verificar si el tenant tiene acceso al módulo
        $user = auth()->user();
        
        if ($module && $user->tenant) {
            // Aquí puedes verificar si el tenant tiene acceso al módulo específico
            // Por ahora, permitimos acceso a todos los módulos
        }

        return $next($request);
    }
}