<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class IPBlacklist
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip IP blocking in development environment
        if (app()->environment('local', 'development')) {
            return $next($request);
        }

        $ip = $request->ip();

        // Check if IP is banned
        if ($this->isIPBanned($ip)) {
            abort(403, 'Access denied');
        }

        // Check if IP is in permanent blacklist
        if ($this->isIPBlacklisted($ip)) {
            abort(403, 'Access denied');
        }

        return $next($request);
    }

    /**
     * Check if IP is temporarily banned
     */
    protected function isIPBanned(string $ip): bool
    {
        return Cache::has('banned_ip:' . $ip);
    }

    /**
     * Check if IP is in permanent blacklist
     */
    protected function isIPBlacklisted(string $ip): bool
    {
        $blacklist = config('security.ip_blacklist', []);
        
        foreach ($blacklist as $pattern) {
            if ($this->matchesPattern($ip, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if IP matches pattern (supports wildcards)
     */
    protected function matchesPattern(string $ip, string $pattern): bool
    {
        $pattern = str_replace(['*', '.'], ['.*', '\.'], $pattern);
        return preg_match('/^' . $pattern . '$/', $ip) === 1;
    }
}