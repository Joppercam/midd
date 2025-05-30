<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Backup Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración del sistema de backups de CrecePyme
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Backup Settings
    |--------------------------------------------------------------------------
    */
    'default' => [
        'disk' => env('BACKUP_DISK', 'local'),
        'retention_days' => env('BACKUP_RETENTION_DAYS', 30),
        'compression' => env('BACKUP_COMPRESSION', true),
        'encryption' => env('BACKUP_ENCRYPTION', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Drivers
    |--------------------------------------------------------------------------
    |
    | Drivers de almacenamiento disponibles para backups
    |
    */
    'drivers' => [
        'local' => [
            'disk' => 'local',
            'path' => 'backups',
        ],
        's3' => [
            'disk' => 's3',
            'bucket' => env('AWS_BACKUP_BUCKET', env('AWS_BUCKET')),
            'path' => 'backups',
            'options' => [
                'ServerSideEncryption' => 'AES256',
                'StorageClass' => 'STANDARD_IA', // Cheaper storage for backups
            ],
        ],
        'dropbox' => [
            'disk' => 'dropbox',
            'path' => '/backups',
        ],
        'google' => [
            'disk' => 'google',
            'path' => 'backups',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Settings
    |--------------------------------------------------------------------------
    */
    'database' => [
        // Tables to always exclude from backups
        'exclude_tables' => [
            'migrations',
            'password_resets',
            'password_reset_tokens',
            'failed_jobs',
            'personal_access_tokens',
            'telescope_entries',
            'telescope_entries_tags',
            'telescope_monitoring',
            'sessions',
            'cache',
            'cache_locks',
            'job_batches',
        ],

        // Maximum records per table to backup (0 = no limit)
        'max_records_per_table' => env('BACKUP_MAX_RECORDS', 0),

        // Use gzip compression for SQL dumps
        'compress_sql' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Files Settings
    |--------------------------------------------------------------------------
    */
    'files' => [
        // Include uploaded files in backup
        'include_uploads' => true,

        // Include storage files
        'include_storage' => true,

        // Paths to include (relative to storage/app)
        'include_paths' => [
            'public/uploads',
            'sii/certificates',
            'private',
        ],

        // Paths to exclude
        'exclude_paths' => [
            'temp',
            'backup',
            'logs',
            'framework/cache',
            'framework/sessions',
            'framework/views',
        ],

        // File extensions to exclude
        'exclude_extensions' => [
            'log',
            'tmp',
            'cache',
        ],

        // Maximum file size to include (in bytes, 0 = no limit)
        'max_file_size' => env('BACKUP_MAX_FILE_SIZE', 50 * 1024 * 1024), // 50MB
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduling
    |--------------------------------------------------------------------------
    */
    'scheduling' => [
        // Default schedule frequency
        'default_frequency' => 'daily',

        // Default time for scheduled backups
        'default_time' => '02:00',

        // Timezone for schedules
        'timezone' => env('APP_TIMEZONE', 'America/Santiago'),

        // Maximum concurrent backup jobs
        'max_concurrent_jobs' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention Policy
    |--------------------------------------------------------------------------
    */
    'retention' => [
        // Keep daily backups for this many days
        'daily' => env('BACKUP_RETENTION_DAILY', 7),

        // Keep weekly backups for this many weeks
        'weekly' => env('BACKUP_RETENTION_WEEKLY', 4),

        // Keep monthly backups for this many months
        'monthly' => env('BACKUP_RETENTION_MONTHLY', 12),

        // Keep yearly backups for this many years
        'yearly' => env('BACKUP_RETENTION_YEARLY', 5),

        // Minimum number of backups to always keep
        'minimum_backups' => env('BACKUP_MINIMUM_KEEP', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        // Send notification on successful backup
        'on_success' => env('BACKUP_NOTIFY_SUCCESS', false),

        // Send notification on backup failure
        'on_failure' => env('BACKUP_NOTIFY_FAILURE', true),

        // Default notification channels
        'channels' => ['mail'],

        // Email addresses for notifications
        'emails' => [
            env('BACKUP_NOTIFICATION_EMAIL', env('MAIL_FROM_ADDRESS')),
        ],

        // Slack webhook URL
        'slack_webhook' => env('BACKUP_SLACK_WEBHOOK'),

        // Discord webhook URL
        'discord_webhook' => env('BACKUP_DISCORD_WEBHOOK'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    */
    'security' => [
        // Encrypt backups
        'encrypt' => env('BACKUP_ENCRYPT', false),

        // Encryption cipher
        'cipher' => 'AES-256-CBC',

        // Password for encryption (if not using app key)
        'password' => env('BACKUP_PASSWORD'),

        // Generate checksum for backup integrity
        'checksum' => true,

        // Checksum algorithm
        'checksum_algorithm' => 'sha256',
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance
    |--------------------------------------------------------------------------
    */
    'performance' => [
        // Memory limit for backup process (in MB)
        'memory_limit' => env('BACKUP_MEMORY_LIMIT', 512),

        // Time limit for backup process (in seconds)
        'time_limit' => env('BACKUP_TIME_LIMIT', 3600), // 1 hour

        // Chunk size for large files (in bytes)
        'chunk_size' => env('BACKUP_CHUNK_SIZE', 1024 * 1024), // 1MB

        // Use streaming for large backups
        'use_streaming' => env('BACKUP_USE_STREAMING', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup
    |--------------------------------------------------------------------------
    */
    'cleanup' => [
        // Auto cleanup old backups
        'auto_cleanup' => env('BACKUP_AUTO_CLEANUP', true),

        // Run cleanup after backup creation
        'cleanup_after_backup' => true,

        // Clean temp files after backup
        'clean_temp_files' => true,

        // Maximum storage usage before cleanup (in GB)
        'max_storage_usage' => env('BACKUP_MAX_STORAGE_GB', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        // Enable backup monitoring
        'enabled' => env('BACKUP_MONITORING', true),

        // Check backup health
        'health_checks' => [
            'file_exists',
            'file_size',
            'checksum_valid',
            'not_too_old',
        ],

        // Alert if no backup in X days
        'alert_after_days' => env('BACKUP_ALERT_DAYS', 2),

        // Monitor disk space
        'monitor_disk_space' => true,

        // Alert if disk space below X%
        'disk_space_threshold' => env('BACKUP_DISK_THRESHOLD', 10),
    ],
];