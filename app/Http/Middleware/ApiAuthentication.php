<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Models\ApiLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class ApiAuthentication
{
    protected $startTime;

    public function handle(Request $request, Closure $next, string $ability = null)
    {
        $this->startTime = microtime(true);
        
        // Try Sanctum authentication first
        if ($request->bearerToken()) {
            $token = PersonalAccessToken::findToken($request->bearerToken());
            
            if (!$token || !$token->tokenable) {
                return $this->unauthorized('Invalid API token');
            }

            // Check token abilities
            if ($ability && !$token->can($ability)) {
                return $this->forbidden('Insufficient permissions for this action');
            }

            // Check if token is expired
            if ($token->expires_at && $token->expires_at->isPast()) {
                return $this->unauthorized('API token has expired');
            }

            // Set user context
            Auth::setUser($token->tokenable);
            $request->setUserResolver(function () use ($token) {
                return $token->tokenable;
            });

            // Update last used
            $token->forceFill(['last_used_at' => now()])->save();

            // Update our tracking
            $this->updateApiTokenTracking($token, $request);

            // Check rate limit
            if (!$this->checkRateLimit($token->tokenable, $request)) {
                return $this->tooManyRequests();
            }

            // Process request
            $response = $next($request);

            // Log the request
            $this->logRequest($token, $request, $response);

            return $response;
        }

        return $this->unauthorized('API token not provided');
    }

    protected function updateApiTokenTracking(PersonalAccessToken $token, Request $request): void
    {
        $tokenHash = hash('sha256', explode('|', $request->bearerToken())[1]);
        
        ApiToken::where('token_hash', $tokenHash)->update([
            'last_used_at' => now(),
            'last_used_ip' => $request->ip(),
        ]);
    }

    protected function checkRateLimit($user, Request $request): bool
    {
        $key = "rate_limit:user:{$user->id}";
        $limit = 1000; // Default rate limit per hour
        
        $attempts = Cache::get($key, 0);
        
        if ($attempts >= $limit) {
            return false;
        }
        
        Cache::put($key, $attempts + 1, 3600); // 1 hour
        
        return true;
    }

    protected function logRequest(PersonalAccessToken $token, Request $request, Response $response): void
    {
        $responseTime = round((microtime(true) - $this->startTime) * 1000);
        
        $logData = [
            'user_id' => $token->tokenable->id,
            'tenant_id' => $token->tokenable->tenant_id,
            'token_id' => $token->id,
            'method' => $request->method(),
            'endpoint' => $request->path(),
            'status_code' => $response->getStatusCode(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'response_time' => $responseTime,
            'request_size' => strlen(json_encode($request->all())),
            'response_size' => strlen($response->getContent()),
        ];

        if ($response->getStatusCode() >= 400) {
            $logData['error_message'] = substr($response->getContent(), 0, 1000);
        }

        ApiLog::create($logData);
    }

    protected function unauthorized(string $message): Response
    {
        return response()->json([
            'error' => 'Unauthorized',
            'message' => $message
        ], 401);
    }

    protected function forbidden(string $message): Response
    {
        return response()->json([
            'error' => 'Forbidden',
            'message' => $message
        ], 403);
    }

    protected function tooManyRequests(): Response
    {
        return response()->json([
            'error' => 'Too Many Requests',
            'message' => 'Límite de solicitudes excedido'
        ], 429);
    }
}