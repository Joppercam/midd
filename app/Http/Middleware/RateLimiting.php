<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimiting
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $limiterName = 'global'): Response
    {
        // Skip rate limiting in development environment
        if (app()->environment('local', 'development')) {
            return $next($request);
        }

        $key = $this->resolveRequestKey($request, $limiterName);
        
        if (RateLimiter::tooManyAttempts($key, $this->getMaxAttempts($limiterName))) {
            return $this->buildResponse($request, $key, $limiterName);
        }

        RateLimiter::hit($key, $this->getDecayMinutes($limiterName) * 60);

        $response = $next($request);

        return $this->addHeaders(
            $response,
            $this->getMaxAttempts($limiterName),
            $this->calculateRemainingAttempts($key, $limiterName)
        );
    }

    /**
     * Resolve the request key
     */
    protected function resolveRequestKey(Request $request, string $limiterName): string
    {
        if ($user = $request->user()) {
            return $limiterName . ':authenticated:' . $user->id;
        }

        return $limiterName . ':' . $request->ip();
    }

    /**
     * Get max attempts based on limiter name
     */
    protected function getMaxAttempts(string $limiterName): int
    {
        return match ($limiterName) {
            'api' => 60,              // 60 requests per minute for API
            'auth' => 5,              // 5 attempts per minute for auth
            'password-reset' => 3,    // 3 attempts per minute for password reset
            'sii' => 10,              // 10 requests per minute for SII operations
            'export' => 5,            // 5 exports per minute
            'webhook' => 100,         // 100 webhook calls per minute
            default => 60,            // Default global limit
        };
    }

    /**
     * Get decay minutes based on limiter name
     */
    protected function getDecayMinutes(string $limiterName): int
    {
        return match ($limiterName) {
            'auth' => 5,              // 5 minutes for auth attempts
            'password-reset' => 15,   // 15 minutes for password reset
            'export' => 5,            // 5 minutes for exports
            default => 1,             // 1 minute for most limits
        };
    }

    /**
     * Calculate remaining attempts
     */
    protected function calculateRemainingAttempts(string $key, string $limiterName): int
    {
        return RateLimiter::remaining($key, $this->getMaxAttempts($limiterName));
    }

    /**
     * Create a 'too many attempts' response
     */
    protected function buildResponse(Request $request, string $key, string $limiterName): Response
    {
        $retryAfter = RateLimiter::availableIn($key);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('Too many attempts. Please try again later.'),
                'retry_after' => $retryAfter,
            ], 429);
        }

        return back()
            ->with('error', __('Too many attempts. Please try again in :seconds seconds.', ['seconds' => $retryAfter]))
            ->withInput()
            ->setStatusCode(429);
    }

    /**
     * Add rate limit headers to response
     */
    protected function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts): Response
    {
        return $response->withHeaders([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => max(0, $remainingAttempts),
        ]);
    }
}