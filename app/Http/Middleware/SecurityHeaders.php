<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Security headers to add to responses
     */
    protected array $headers = [
        // Prevent XSS attacks
        'X-XSS-Protection' => '1; mode=block',
        
        // Prevent clickjacking
        'X-Frame-Options' => 'SAMEORIGIN',
        
        // Prevent MIME type sniffing
        'X-Content-Type-Options' => 'nosniff',
        
        // Control referrer information
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        
        // Remove server info
        'Server' => 'CrecePyme',
        
        // Permissions Policy (formerly Feature Policy)
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(self), payment=()',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Skip security headers in development if needed
        if (app()->environment('local', 'development') && !env('CSP_ENABLED', false)) {
            return $response;
        }

        // Add security headers
        foreach ($this->headers as $header => $value) {
            $response->headers->set($header, $value);
        }

        // Add Content Security Policy
        if (env('CSP_ENABLED', true)) {
            $response->headers->set('Content-Security-Policy', $this->getCSP($request));
        }

        // Add Strict Transport Security for HTTPS
        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        return $response;
    }

    /**
     * Generate Content Security Policy based on request
     */
    protected function getCSP(Request $request): string
    {
        $nonce = $this->generateNonce();
        
        // Store nonce in request for use in views
        $request->attributes->set('csp-nonce', $nonce);

        $policies = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net https://unpkg.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net",
            "font-src 'self' https://fonts.gstatic.com data:",
            "img-src 'self' data: https: blob:",
            "connect-src 'self' wss://*.crecepyme.cl https://api.crecepyme.cl",
            "media-src 'self'",
            "object-src 'none'",
            "frame-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "upgrade-insecure-requests",
        ];

        // Add report URI if configured
        if ($reportUri = config('security.csp_report_uri')) {
            $policies[] = "report-uri {$reportUri}";
        }

        return implode('; ', $policies);
    }

    /**
     * Generate a random nonce for CSP
     */
    protected function generateNonce(): string
    {
        return base64_encode(random_bytes(16));
    }
}