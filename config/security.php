<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | This file contains all security-related configuration for the application
    |
    */

    /*
    |--------------------------------------------------------------------------
    | IP Blacklist
    |--------------------------------------------------------------------------
    |
    | IP addresses or patterns to block. Supports wildcards (*).
    |
    */
    'ip_blacklist' => explode(',', env('SECURITY_IP_BLACKLIST', '')),

    /*
    |--------------------------------------------------------------------------
    | IP Whitelist
    |--------------------------------------------------------------------------
    |
    | IP addresses that bypass security checks (use with caution).
    |
    */
    'ip_whitelist' => explode(',', env('SECURITY_IP_WHITELIST', '')),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limit settings for different endpoints
    |
    */
    'rate_limits' => [
        'global' => [
            'attempts' => env('RATE_LIMIT_GLOBAL', 60),
            'decay_minutes' => 1,
        ],
        'api' => [
            'attempts' => env('RATE_LIMIT_API', 60),
            'decay_minutes' => 1,
        ],
        'auth' => [
            'attempts' => env('RATE_LIMIT_AUTH', 5),
            'decay_minutes' => 5,
        ],
        'password_reset' => [
            'attempts' => env('RATE_LIMIT_PASSWORD_RESET', 3),
            'decay_minutes' => 15,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Two-Factor Authentication
    |--------------------------------------------------------------------------
    */
    'two_factor' => [
        'enabled' => env('2FA_ENABLED', true),
        'issuer' => env('2FA_ISSUER', 'CrecePyme'),
        'recovery_codes' => 8,
        'window' => 1, // Time window in 30 second intervals
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Security
    |--------------------------------------------------------------------------
    */
    'session' => [
        'timeout' => env('SESSION_TIMEOUT', 120), // minutes
        'single_device' => env('SESSION_SINGLE_DEVICE', true),
        'regenerate_on_login' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Requirements
    |--------------------------------------------------------------------------
    */
    'password' => [
        'min_length' => 8,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_numbers' => true,
        'require_symbols' => true,
        'check_pwned' => env('CHECK_PWNED_PASSWORDS', true),
        'history' => 5, // Number of previous passwords to check
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    */
    'csp' => [
        'enabled' => env('CSP_ENABLED', true),
        'report_only' => env('CSP_REPORT_ONLY', false),
        'report_uri' => env('CSP_REPORT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Headers
    |--------------------------------------------------------------------------
    */
    'headers' => [
        'hsts' => [
            'enabled' => env('HSTS_ENABLED', true),
            'max_age' => 31536000,
            'include_subdomains' => true,
            'preload' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | WAF Settings
    |--------------------------------------------------------------------------
    */
    'waf' => [
        'enabled' => env('WAF_ENABLED', true),
        'block_suspicious_requests' => true,
        'log_blocked_requests' => true,
        'ban_threshold' => 10, // Ban IP after this many blocked requests
        'ban_duration' => 7 * 24 * 60, // Ban duration in minutes (7 days)
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload Security
    |--------------------------------------------------------------------------
    */
    'uploads' => [
        'max_size' => 10 * 1024 * 1024, // 10MB
        'allowed_mimes' => [
            'jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx',
            'xls', 'xlsx', 'csv', 'txt', 'zip', 'rar',
        ],
        'scan_viruses' => env('SCAN_UPLOADS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Encryption Settings
    |--------------------------------------------------------------------------
    */
    'encryption' => [
        'algorithm' => 'AES-256-GCM',
        'rotate_keys' => env('ROTATE_ENCRYPTION_KEYS', true),
        'key_rotation_days' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit and Logging
    |--------------------------------------------------------------------------
    */
    'audit' => [
        'log_security_events' => true,
        'log_failed_logins' => true,
        'log_permission_denials' => true,
        'retention_days' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | API Security
    |--------------------------------------------------------------------------
    */
    'api' => [
        'require_https' => env('API_REQUIRE_HTTPS', true),
        'token_expiry' => 60 * 24 * 30, // 30 days
        'refresh_token_expiry' => 60 * 24 * 60, // 60 days
        'max_tokens_per_user' => 5,
    ],
];