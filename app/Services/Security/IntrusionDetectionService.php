<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Models\SecurityEvent;
use App\Events\SecurityAlertDetected;

class IntrusionDetectionService
{
    protected array $suspiciousPatterns = [
        'paths' => [
            '/wp-admin', '/wp-login', '/administrator', '/phpmyadmin',
            '/.env', '/.git', '/config.php', '/web.config',
            '/backup', '/.sql', '/.bak', '/.zip', '/.rar',
        ],
        'parameters' => [
            'cmd', 'exec', 'system', 'passthru', 'eval',
            'file_get_contents', 'include', 'require', 'phpinfo',
        ],
        'payloads' => [
            'base64_decode', 'rot13', 'hexdec', 'gzinflate',
            'str_rot13', 'convert_uudecode', 'urldecode',
        ],
    ];

    protected array $thresholds = [
        'failed_logins' => 5,
        'rate_limit_hits' => 10,
        'waf_blocks' => 5,
        '404_errors' => 20,
        'suspicious_requests' => 3,
    ];

    /**
     * Analyze request for intrusion attempts
     */
    public function analyzeRequest(Request $request): array
    {
        $threats = [];
        
        // Check suspicious paths
        if ($path = $this->checkSuspiciousPaths($request)) {
            $threats[] = ['type' => 'suspicious_path', 'detail' => $path];
        }
        
        // Check suspicious parameters
        if ($params = $this->checkSuspiciousParameters($request)) {
            $threats[] = ['type' => 'suspicious_parameters', 'detail' => $params];
        }
        
        // Check for automated tools
        if ($tool = $this->detectAutomatedTools($request)) {
            $threats[] = ['type' => 'automated_tool', 'detail' => $tool];
        }
        
        // Check request anomalies
        if ($anomaly = $this->detectRequestAnomalies($request)) {
            $threats[] = ['type' => 'request_anomaly', 'detail' => $anomaly];
        }
        
        // Check brute force patterns
        if ($this->detectBruteForce($request)) {
            $threats[] = ['type' => 'brute_force', 'detail' => 'Multiple failed attempts detected'];
        }
        
        // Log and handle threats
        if (!empty($threats)) {
            $this->handleThreats($request, $threats);
        }
        
        return $threats;
    }

    /**
     * Check for suspicious paths
     */
    protected function checkSuspiciousPaths(Request $request): ?string
    {
        $path = strtolower($request->path());
        
        foreach ($this->suspiciousPatterns['paths'] as $pattern) {
            if (str_contains($path, $pattern)) {
                return $pattern;
            }
        }
        
        return null;
    }

    /**
     * Check for suspicious parameters
     */
    protected function checkSuspiciousParameters(Request $request): ?array
    {
        $suspicious = [];
        $params = $request->all();
        
        foreach ($params as $key => $value) {
            foreach ($this->suspiciousPatterns['parameters'] as $pattern) {
                if (str_contains(strtolower($key), $pattern) || 
                    (is_string($value) && str_contains(strtolower($value), $pattern))) {
                    $suspicious[] = $pattern;
                }
            }
        }
        
        return empty($suspicious) ? null : $suspicious;
    }

    /**
     * Detect automated scanning tools
     */
    protected function detectAutomatedTools(Request $request): ?string
    {
        $userAgent = strtolower($request->userAgent() ?? '');
        $scannerSignatures = [
            'sqlmap' => 'SQL injection tool',
            'nikto' => 'Web scanner',
            'nmap' => 'Port scanner',
            'masscan' => 'Port scanner',
            'wpscan' => 'WordPress scanner',
            'dirbuster' => 'Directory scanner',
            'gobuster' => 'Directory scanner',
        ];
        
        foreach ($scannerSignatures as $signature => $tool) {
            if (str_contains($userAgent, $signature)) {
                return $tool;
            }
        }
        
        return null;
    }

    /**
     * Detect request anomalies
     */
    protected function detectRequestAnomalies(Request $request): ?string
    {
        // Check for missing or suspicious headers
        if (!$request->hasHeader('User-Agent')) {
            return 'Missing User-Agent header';
        }
        
        // Check for suspicious content types
        if ($request->isMethod('POST') && !$request->hasHeader('Content-Type')) {
            return 'POST request without Content-Type';
        }
        
        // Check for excessive parameters
        if (count($request->all()) > 100) {
            return 'Excessive number of parameters';
        }
        
        // Check for very long values
        foreach ($request->all() as $value) {
            if (is_string($value) && strlen($value) > 10000) {
                return 'Excessively long parameter value';
            }
        }
        
        return null;
    }

    /**
     * Detect brute force attempts
     */
    protected function detectBruteForce(Request $request): bool
    {
        $ip = $request->ip();
        $key = "failed_attempts:{$ip}";
        
        $attempts = Cache::get($key, 0);
        
        return $attempts > $this->thresholds['failed_logins'];
    }

    /**
     * Handle detected threats
     */
    protected function handleThreats(Request $request, array $threats): void
    {
        $ip = $request->ip();
        
        // Log security event
        $this->logSecurityEvent($request, $threats);
        
        // Increment threat counter for IP
        $threatKey = "threat_score:{$ip}";
        $score = Cache::increment($threatKey);
        Cache::put($threatKey, $score, now()->addHours(24));
        
        // Take action based on threat score
        if ($score >= 10) {
            $this->blockIP($ip, 'High threat score');
        } elseif ($score >= 5) {
            $this->raiseSuspicionLevel($ip);
        }
        
        // Dispatch alert for critical threats
        foreach ($threats as $threat) {
            if (in_array($threat['type'], ['sql_injection', 'automated_tool', 'brute_force'])) {
                event(new SecurityAlertDetected($threat, $request));
            }
        }
    }

    /**
     * Log security event
     */
    protected function logSecurityEvent(Request $request, array $threats): void
    {
        Log::channel('security')->warning('Intrusion detection alert', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'uri' => $request->getRequestUri(),
            'method' => $request->method(),
            'threats' => $threats,
            'user_id' => $request->user()?->id,
            'tenant_id' => $request->user()?->tenant_id,
        ]);

        // Store in database for analysis
        if (class_exists(SecurityEvent::class)) {
            SecurityEvent::create([
                'type' => 'intrusion_attempt',
                'severity' => 'high',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'uri' => $request->getRequestUri(),
                'method' => $request->method(),
                'threats' => $threats,
                'user_id' => $request->user()?->id,
                'tenant_id' => $request->user()?->tenant_id,
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Block IP address
     */
    protected function blockIP(string $ip, string $reason): void
    {
        Cache::put("banned_ip:{$ip}", true, now()->addDays(7));
        
        Log::channel('security')->error('IP blocked by IDS', [
            'ip' => $ip,
            'reason' => $reason,
            'blocked_until' => now()->addDays(7),
        ]);
    }

    /**
     * Raise suspicion level for IP
     */
    protected function raiseSuspicionLevel(string $ip): void
    {
        Cache::put("suspicious_ip:{$ip}", true, now()->addHours(6));
        
        Log::channel('security')->warning('IP marked as suspicious', [
            'ip' => $ip,
            'duration' => '6 hours',
        ]);
    }

    /**
     * Check if IP is suspicious
     */
    public function isIPSuspicious(string $ip): bool
    {
        return Cache::has("suspicious_ip:{$ip}");
    }

    /**
     * Get current threat level for IP
     */
    public function getThreatLevel(string $ip): int
    {
        return Cache::get("threat_score:{$ip}", 0);
    }

    /**
     * Reset threat score for IP
     */
    public function resetThreatScore(string $ip): void
    {
        Cache::forget("threat_score:{$ip}");
        Cache::forget("suspicious_ip:{$ip}");
    }
}