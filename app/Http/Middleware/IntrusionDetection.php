<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\Security\IntrusionDetectionService;

class IntrusionDetection
{
    protected IntrusionDetectionService $ids;

    public function __construct(IntrusionDetectionService $ids)
    {
        $this->ids = $ids;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip IDS in development environment
        if (app()->environment('local', 'development')) {
            return $next($request);
        }

        // Skip IDS for whitelisted IPs
        if ($this->isWhitelisted($request->ip())) {
            return $next($request);
        }

        // Analyze request for threats
        $threats = $this->ids->analyzeRequest($request);

        // Block if critical threats detected
        if ($this->hasCriticalThreats($threats)) {
            abort(403, 'Access denied due to security policy');
        }

        // Add security context to request
        $request->attributes->set('threat_level', $this->ids->getThreatLevel($request->ip()));
        $request->attributes->set('is_suspicious', $this->ids->isIPSuspicious($request->ip()));

        $response = $next($request);

        // Add security headers for suspicious requests
        if ($request->attributes->get('is_suspicious')) {
            $response->headers->set('X-Security-Notice', 'Suspicious activity detected');
        }

        return $response;
    }

    /**
     * Check if IP is whitelisted
     */
    protected function isWhitelisted(string $ip): bool
    {
        $whitelist = config('security.ip_whitelist', []);
        
        return in_array($ip, $whitelist);
    }

    /**
     * Check if threats are critical
     */
    protected function hasCriticalThreats(array $threats): bool
    {
        $criticalTypes = ['sql_injection', 'automated_tool', 'command_injection'];
        
        foreach ($threats as $threat) {
            if (in_array($threat['type'], $criticalTypes)) {
                return true;
            }
        }
        
        return false;
    }
}