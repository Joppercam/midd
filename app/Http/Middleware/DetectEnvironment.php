<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DetectEnvironment
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        
        // Detectar entorno basado en subdomain
        if (str_contains($host, 'demo.')) {
            // Configurar entorno de demo
            config(['app.env' => 'demo']);
            config(['app.name' => 'MIDD Demo']);
            
            // Forzar configuraciones de demo
            config(['demo.active' => true]);
            config(['mail.default' => 'log']); // Solo logs en demo
            config(['sii.environment' => 'certification']); // Solo certificación
            
            // Marcar en el request que es demo
            $request->attributes->set('is_demo', true);
            
        } elseif (str_contains($host, 'app.') || str_contains($host, 'crecepyme.cl')) {
            // Configurar entorno de producción
            config(['app.env' => 'production']);
            config(['demo.active' => false]);
            
            // Marcar en el request que es producción
            $request->attributes->set('is_demo', false);
        }
        
        return $next($request);
    }
}
