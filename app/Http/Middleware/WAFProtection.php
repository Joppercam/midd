<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class WAFProtection
{
    /**
     * Suspicious patterns to block
     */
    protected array $sqlInjectionPatterns = [
        '/(\bunion\b.*\bselect\b|\bselect\b.*\bfrom\b|\binsert\b.*\binto\b|\bupdate\b.*\bset\b|\bdelete\b.*\bfrom\b|\bdrop\b.*\btable\b|\bcreate\b.*\btable\b)/i',
        '/(\bexec\b|\bexecute\b|\bcast\b|\bdeclare\b|\bnvarchar\b|\bvarchar\b)/i',
        '/(;|\||`|<|>|\\$|\\{|\\}|\\[|\\]|\\\\|\/\*|\*\/|--)/i',
    ];

    protected array $xssPatterns = [
        '/<script[^>]*>.*?<\/script>/is',
        '/<iframe[^>]*>.*?<\/iframe>/is',
        '/javascript:/i',
        '/on(click|load|error|mouseover|focus|blur)\s*=/i',
        '/<img[^>]*src[\\s]*=[\\s]*["\']?javascript:/i',
    ];

    protected array $pathTraversalPatterns = [
        '/\.\.[\/\\\]/',
        '/\/etc\/passwd/i',
        '/\/windows\/system32/i',
        '/\/proc\/self/i',
    ];

    protected array $commandInjectionPatterns = [
        '/(\||;|`|>|<|\$\(|\${)/i',
        '/(wget|curl|nc|netcat|telnet|bash|sh|cmd|powershell)/i',
    ];

    /**
     * Blocked user agents
     */
    protected array $blockedUserAgents = [
        'sqlmap',
        'nikto',
        'nessus',
        'nmap',
        'masscan',
        'metasploit',
        'arachni',
        'acunetix',
        'burpsuite',
        'owasp',
    ];

    /**
     * Blocked file extensions for uploads
     */
    protected array $blockedExtensions = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'phps',
        'exe', 'com', 'bat', 'cmd', 'sh', 'bash',
        'dll', 'vbs', 'js', 'jar', 'scr',
        'app', 'vb', 'vbe', 'vbs', 'vbscript',
        'ws', 'wsf', 'wsc', 'wsh', 'ps1', 'ps2',
        'psc1', 'psc2', 'msh', 'msh1', 'msh2',
        'inf', 'reg', 'scf', 'msi', 'pif',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip WAF protection in development environment
        if (app()->environment('local', 'development') || !env('WAF_ENABLED', true)) {
            return $next($request);
        }

        // Check user agent
        if ($this->isBlockedUserAgent($request)) {
            return $this->blockRequest($request, 'Blocked user agent detected');
        }

        // Check for SQL injection
        if ($this->detectSQLInjection($request)) {
            return $this->blockRequest($request, 'SQL injection attempt detected');
        }

        // Check for XSS
        if ($this->detectXSS($request)) {
            return $this->blockRequest($request, 'XSS attempt detected');
        }

        // Check for path traversal
        if ($this->detectPathTraversal($request)) {
            return $this->blockRequest($request, 'Path traversal attempt detected');
        }

        // Check for command injection
        if ($this->detectCommandInjection($request)) {
            return $this->blockRequest($request, 'Command injection attempt detected');
        }

        // Check file uploads
        if ($request->hasFile('file') && $this->hasBlockedFileExtension($request)) {
            return $this->blockRequest($request, 'Blocked file type uploaded');
        }

        // Check request size
        if ($this->isOversizedRequest($request)) {
            return $this->blockRequest($request, 'Request size limit exceeded');
        }

        return $next($request);
    }

    /**
     * Check if user agent is blocked
     */
    protected function isBlockedUserAgent(Request $request): bool
    {
        $userAgent = strtolower($request->userAgent() ?? '');
        
        foreach ($this->blockedUserAgents as $blocked) {
            if (str_contains($userAgent, $blocked)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect SQL injection attempts
     */
    protected function detectSQLInjection(Request $request): bool
    {
        $input = $this->getAllInput($request);
        
        foreach ($this->sqlInjectionPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect XSS attempts
     */
    protected function detectXSS(Request $request): bool
    {
        $input = $this->getAllInput($request);
        
        foreach ($this->xssPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect path traversal attempts
     */
    protected function detectPathTraversal(Request $request): bool
    {
        $input = $this->getAllInput($request);
        $uri = $request->getRequestUri();
        
        foreach ($this->pathTraversalPatterns as $pattern) {
            if (preg_match($pattern, $input) || preg_match($pattern, $uri)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect command injection attempts
     */
    protected function detectCommandInjection(Request $request): bool
    {
        $input = $this->getAllInput($request);
        
        foreach ($this->commandInjectionPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check for blocked file extensions
     */
    protected function hasBlockedFileExtension(Request $request): bool
    {
        foreach ($request->files->all() as $file) {
            if (is_array($file)) {
                foreach ($file as $f) {
                    if ($this->isBlockedExtension($f->getClientOriginalExtension())) {
                        return true;
                    }
                }
            } else {
                if ($this->isBlockedExtension($file->getClientOriginalExtension())) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if extension is blocked
     */
    protected function isBlockedExtension(string $extension): bool
    {
        return in_array(strtolower($extension), $this->blockedExtensions);
    }

    /**
     * Check if request is oversized
     */
    protected function isOversizedRequest(Request $request): bool
    {
        $maxSize = 10 * 1024 * 1024; // 10MB
        return $request->header('Content-Length', 0) > $maxSize;
    }

    /**
     * Get all input as string
     */
    protected function getAllInput(Request $request): string
    {
        $input = array_merge(
            $request->all(),
            $request->query->all(),
            $request->server->all()
        );

        return json_encode($input);
    }

    /**
     * Block the request and log the attempt
     */
    protected function blockRequest(Request $request, string $reason): Response
    {
        // Log security event
        Log::channel('security')->warning('WAF blocked request', [
            'reason' => $reason,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'uri' => $request->getRequestUri(),
            'method' => $request->method(),
            'user_id' => $request->user()?->id,
            'tenant_id' => $request->user()?->tenant_id,
        ]);

        // Increment failed attempts counter for IP
        $key = 'waf_blocked:' . $request->ip();
        $attempts = cache()->increment($key);
        cache()->put($key, $attempts, now()->addHours(24));

        // Ban IP if too many attempts
        if ($attempts > 10) {
            $this->banIP($request->ip());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Request blocked for security reasons',
            ], 403);
        }

        abort(403, 'Request blocked for security reasons');
    }

    /**
     * Ban an IP address
     */
    protected function banIP(string $ip): void
    {
        cache()->put('banned_ip:' . $ip, true, now()->addDays(7));
        
        Log::channel('security')->error('IP banned due to repeated security violations', [
            'ip' => $ip,
            'banned_until' => now()->addDays(7),
        ]);
    }
}