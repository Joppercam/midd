<?php

namespace App\Services\SuperAdmin;

use App\Models\Tenant;
use App\Models\User;
use App\Models\TaxDocument;
use App\Models\ApiLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class SystemMonitoringService
{
    /**
     * Get overall system metrics
     */
    public function getSystemMetrics(): array
    {
        return [
            'server' => $this->getServerMetrics(),
            'database' => $this->getDatabaseMetrics(),
            'cache' => $this->getCacheMetrics(),
            'queue' => $this->getQueueMetrics(),
            'storage' => $this->getStorageMetrics(),
            'performance' => $this->getPerformanceMetrics(),
        ];
    }

    /**
     * Get server metrics
     */
    private function getServerMetrics(): array
    {
        $load = sys_getloadavg();
        $memory = $this->getMemoryUsage();
        $cpu = $this->getCpuUsage();
        
        return [
            'load_average' => [
                '1min' => $load[0],
                '5min' => $load[1],
                '15min' => $load[2],
            ],
            'memory' => [
                'used' => $memory['used'],
                'total' => $memory['total'],
                'percentage' => $memory['percentage'],
            ],
            'cpu' => [
                'usage' => $cpu,
                'cores' => $this->getCpuCores(),
            ],
            'uptime' => $this->getUptime(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ];
    }

    /**
     * Get database metrics
     */
    private function getDatabaseMetrics(): array
    {
        $driver = DB::getDriverName();
        $connections = 1; // Default fallback
        $sizeData = $this->getDatabaseSize();
        
        // Get connection count based on database driver
        try {
            if ($driver === 'mysql') {
                $connectionList = DB::select('SHOW PROCESSLIST');
                $connections = count($connectionList);
            } elseif ($driver === 'pgsql') {
                $connectionList = DB::select('SELECT * FROM pg_stat_activity');
                $connections = count($connectionList);
            }
            // For SQLite, connections are not really applicable in the same way
        } catch (\Exception $e) {
            // If we can't get connection info, use default
            $connections = 1;
        }
        
        return [
            'connections' => $connections,
            'size_mb' => $sizeData['size_mb'],
            'slow_queries' => $this->getSlowQueries(),
            'tables_count' => $this->getTablesCount(),
            'records' => [
                'tenants' => Tenant::count(),
                'users' => User::count(),
                'documents' => TaxDocument::count(),
            ],
        ];
    }
    
    private function getDatabaseSize(): array
    {
        try {
            $driver = DB::getDriverName();
            
            if ($driver === 'mysql') {
                $size = DB::select('SELECT 
                    table_schema as database_name,
                    SUM(data_length + index_length) / 1024 / 1024 AS size_mb
                    FROM information_schema.TABLES 
                    WHERE table_schema = ?
                    GROUP BY table_schema', [config('database.connections.mysql.database')]);
                
                return [
                    'size_mb' => $size[0]->size_mb ?? 0
                ];
            } elseif ($driver === 'sqlite') {
                $dbPath = config('database.connections.sqlite.database');
                if (file_exists($dbPath)) {
                    $sizeBytes = filesize($dbPath);
                    return [
                        'size_mb' => round($sizeBytes / 1024 / 1024, 2)
                    ];
                }
            } elseif ($driver === 'pgsql') {
                $dbName = config('database.connections.pgsql.database');
                $size = DB::select("SELECT pg_size_pretty(pg_database_size(?)) as size", [$dbName]);
                // Parse the size string to get MB
                $sizeStr = $size[0]->size ?? '0 MB';
                preg_match('/(\d+(?:\.\d+)?)\s*(\w+)/', $sizeStr, $matches);
                $value = (float)($matches[1] ?? 0);
                $unit = strtoupper($matches[2] ?? 'MB');
                
                // Convert to MB
                $sizeMb = match($unit) {
                    'KB' => $value / 1024,
                    'GB' => $value * 1024,
                    'TB' => $value * 1024 * 1024,
                    default => $value
                };
                
                return [
                    'size_mb' => round($sizeMb, 2)
                ];
            }
        } catch (\Exception $e) {
            // Fallback: estimate based on record counts
            $tenantCount = Tenant::count();
            $userCount = User::count();
            $docCount = TaxDocument::count();
            
            // Very rough estimate: 1KB per record average
            $estimatedSizeMb = ($tenantCount + $userCount + $docCount) / 1024;
            
            return [
                'size_mb' => max(0.1, round($estimatedSizeMb, 2))
            ];
        }
        
        return ['size_mb' => 0];
    }

    /**
     * Get cache metrics
     */
    private function getCacheMetrics(): array
    {
        $driver = config('cache.default');
        $metrics = [
            'driver' => $driver,
            'hit_rate' => 0,
            'miss_rate' => 0,
            'keys' => 0,
            'memory_usage' => 0,
        ];
        
        if ($driver === 'redis') {
            try {
                $info = Redis::info();
                $stats = Redis::info('stats');
                
                $hits = $stats['keyspace_hits'] ?? 0;
                $misses = $stats['keyspace_misses'] ?? 0;
                $total = $hits + $misses;
                
                $metrics['hit_rate'] = $total > 0 ? ($hits / $total) * 100 : 0;
                $metrics['miss_rate'] = $total > 0 ? ($misses / $total) * 100 : 0;
                $metrics['keys'] = $info['db0']['keys'] ?? 0;
                $metrics['memory_usage'] = $info['used_memory_human'] ?? '0B';
            } catch (\Exception $e) {
                // Redis not available
            }
        }
        
        return $metrics;
    }

    /**
     * Get queue metrics
     */
    private function getQueueMetrics(): array
    {
        return [
            'jobs' => [
                'pending' => DB::table('jobs')->count(),
                'failed' => DB::table('failed_jobs')->count(),
                'processed_today' => $this->getProcessedJobsToday(),
            ],
            'workers' => $this->getQueueWorkers(),
            'queues' => [
                'default' => DB::table('jobs')->where('queue', 'default')->count(),
                'high' => DB::table('jobs')->where('queue', 'high')->count(),
                'low' => DB::table('jobs')->where('queue', 'low')->count(),
            ],
        ];
    }

    /**
     * Get storage metrics
     */
    private function getStorageMetrics(): array
    {
        $diskTotal = disk_total_space('/');
        $diskFree = disk_free_space('/');
        $diskUsed = $diskTotal - $diskFree;
        
        return [
            'disk' => [
                'total' => $this->formatBytes($diskTotal),
                'used' => $this->formatBytes($diskUsed),
                'free' => $this->formatBytes($diskFree),
                'percentage' => ($diskUsed / $diskTotal) * 100,
            ],
            'uploads' => [
                'total_files' => $this->countUploadedFiles(),
                'total_size' => $this->getUploadedFilesSize(),
            ],
            'backups' => [
                'count' => DB::table('backups')->count(),
                'total_size' => $this->getBackupsSize(),
                'last_backup' => DB::table('backups')->latest()->value('created_at'),
            ],
        ];
    }

    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        $now = Carbon::now();
        $hourAgo = $now->copy()->subHour();
        
        return [
            'response_time' => [
                'average' => $this->getAverageResponseTime(),
                'p95' => $this->getPercentileResponseTime(95),
                'p99' => $this->getPercentileResponseTime(99),
            ],
            'requests' => [
                'per_minute' => $this->getRequestsPerMinute(),
                'per_hour' => $this->getRequestsPerHour(),
                'errors_per_hour' => $this->getErrorsPerHour(),
            ],
            'api' => [
                'calls_today' => ApiLog::whereDate('created_at', today())->count(),
                'average_response_time' => ApiLog::whereDate('created_at', today())->avg('response_time'),
                'error_rate' => $this->getApiErrorRate(),
            ],
        ];
    }

    /**
     * Get health checks
     */
    public function getHealthChecks(): array
    {
        return [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'storage' => $this->checkStorage(),
            'services' => $this->checkExternalServices(),
        ];
    }

    /**
     * Helper methods
     */
    private function getMemoryUsage(): array
    {
        // Check if running on Linux
        if (PHP_OS_FAMILY === 'Linux') {
            $free = shell_exec('free');
            if ($free) {
                $free = (string)trim($free);
                $free_arr = explode("\n", $free);
                if (isset($free_arr[1])) {
                    $mem = explode(" ", $free_arr[1]);
                    $mem = array_filter($mem);
                    $mem = array_merge($mem);
                    
                    $total = $mem[1] ?? 0;
                    $used = $mem[2] ?? 0;
                    
                    return [
                        'total' => $total,
                        'used' => $used,
                        'percentage' => $total > 0 ? ($used / $total) * 100 : 0,
                    ];
                }
            }
        }
        
        // Fallback for macOS and other systems using memory_get_usage()
        $used = memory_get_usage(true);
        $total = memory_get_peak_usage(true);
        
        return [
            'total' => $total,
            'used' => $used,
            'percentage' => $total > 0 ? ($used / $total) * 100 : 0,
        ];
    }

    private function getCpuUsage(): float
    {
        $load = sys_getloadavg();
        $cores = $this->getCpuCores();
        return ($load[0] / $cores) * 100;
    }

    private function getCpuCores(): int
    {
        $cores = 1;
        
        // Try Linux method first
        if (is_file('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            preg_match_all('/^processor/m', $cpuinfo, $matches);
            $cores = count($matches[0]);
        } 
        // Try macOS method
        elseif (PHP_OS_FAMILY === 'Darwin') {
            $output = shell_exec('sysctl -n hw.ncpu');
            if ($output) {
                $cores = (int)trim($output);
            }
        }
        // Try Windows method
        elseif (PHP_OS_FAMILY === 'Windows') {
            $output = shell_exec('echo %NUMBER_OF_PROCESSORS%');
            if ($output) {
                $cores = (int)trim($output);
            }
        }
        
        return max(1, $cores);
    }

    private function getUptime(): string
    {
        // Try different uptime commands based on OS
        if (PHP_OS_FAMILY === 'Linux') {
            $uptime = shell_exec('uptime -p');
            if ($uptime) {
                return trim($uptime);
            }
        }
        
        // Fallback for macOS and other systems
        $uptime = shell_exec('uptime');
        if ($uptime) {
            // Extract just the uptime portion
            if (preg_match('/up\s+(.+?),\s+\d+\s+user/', $uptime, $matches)) {
                return 'up ' . trim($matches[1]);
            }
            return trim($uptime);
        }
        
        return 'Unknown';
    }

    private function getSlowQueries(): int
    {
        // This would typically check MySQL slow query log
        return 0;
    }

    private function getTablesCount(): int
    {
        try {
            $driver = DB::getDriverName();
            
            if ($driver === 'mysql') {
                return count(DB::select('SHOW TABLES'));
            } elseif ($driver === 'sqlite') {
                return count(DB::select("SELECT name FROM sqlite_master WHERE type='table'"));
            } elseif ($driver === 'pgsql') {
                return count(DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'"));
            }
            
            // Fallback: try to count common tables
            $commonTables = [
                'users', 'tenants', 'customers', 'products', 'tax_documents'
            ];
            
            $count = 0;
            foreach ($commonTables as $table) {
                try {
                    DB::table($table)->limit(1)->get();
                    $count++;
                } catch (\Exception $e) {
                    // Table doesn't exist, skip
                }
            }
            
            return $count;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getProcessedJobsToday(): int
    {
        // This would track processed jobs
        return Cache::get('processed_jobs_today', 0);
    }

    private function getQueueWorkers(): int
    {
        // Check running queue workers
        $processes = shell_exec('ps aux | grep -c "artisan queue:work"');
        return max(0, (int)$processes - 1); // Subtract grep itself
    }

    private function countUploadedFiles(): int
    {
        $count = 0;
        $directories = ['invoices', 'quotes', 'receipts', 'backups'];
        
        foreach ($directories as $dir) {
            $path = storage_path("app/public/{$dir}");
            if (is_dir($path)) {
                $count += count(glob("{$path}/*"));
            }
        }
        
        return $count;
    }

    private function getUploadedFilesSize(): string
    {
        $size = 0;
        $directories = ['invoices', 'quotes', 'receipts', 'backups'];
        
        foreach ($directories as $dir) {
            $path = storage_path("app/public/{$dir}");
            if (is_dir($path)) {
                $size += $this->getDirectorySize($path);
            }
        }
        
        return $this->formatBytes($size);
    }

    private function getBackupsSize(): string
    {
        $path = storage_path('app/backups');
        $size = is_dir($path) ? $this->getDirectorySize($path) : 0;
        return $this->formatBytes($size);
    }

    private function getDirectorySize($path): int
    {
        $size = 0;
        foreach (glob(rtrim($path, '/').'/*', GLOB_NOSORT) as $each) {
            $size += is_file($each) ? filesize($each) : $this->getDirectorySize($each);
        }
        return $size;
    }

    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    private function getAverageResponseTime(): float
    {
        return Cache::get('average_response_time', 0);
    }

    private function getPercentileResponseTime($percentile): float
    {
        return Cache::get("response_time_p{$percentile}", 0);
    }

    private function getRequestsPerMinute(): int
    {
        return Cache::get('requests_per_minute', 0);
    }

    private function getRequestsPerHour(): int
    {
        return Cache::get('requests_per_hour', 0);
    }

    private function getErrorsPerHour(): int
    {
        return Cache::get('errors_per_hour', 0);
    }

    private function getApiErrorRate(): float
    {
        $total = ApiLog::whereDate('created_at', today())->count();
        $errors = ApiLog::whereDate('created_at', today())
            ->where('response_code', '>=', 400)
            ->count();
        
        return $total > 0 ? ($errors / $total) * 100 : 0;
    }

    private function checkDatabase(): array
    {
        try {
            DB::select('SELECT 1');
            return ['status' => 'healthy', 'message' => 'Database connection OK'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => $e->getMessage()];
        }
    }

    private function checkCache(): array
    {
        try {
            Cache::put('health_check', true, 1);
            $result = Cache::get('health_check');
            return ['status' => 'healthy', 'message' => 'Cache system OK'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => $e->getMessage()];
        }
    }

    private function checkQueue(): array
    {
        $failed = DB::table('failed_jobs')->count();
        
        if ($failed > 100) {
            return ['status' => 'warning', 'message' => "{$failed} failed jobs in queue"];
        }
        
        return ['status' => 'healthy', 'message' => 'Queue system OK'];
    }

    private function checkStorage(): array
    {
        $diskFreePercentage = (disk_free_space('/') / disk_total_space('/')) * 100;
        
        if ($diskFreePercentage < 10) {
            return ['status' => 'critical', 'message' => 'Low disk space: ' . round($diskFreePercentage, 2) . '% free'];
        }
        
        if ($diskFreePercentage < 20) {
            return ['status' => 'warning', 'message' => 'Disk space warning: ' . round($diskFreePercentage, 2) . '% free'];
        }
        
        return ['status' => 'healthy', 'message' => 'Storage OK: ' . round($diskFreePercentage, 2) . '% free'];
    }

    private function checkExternalServices(): array
    {
        $services = [];
        
        // Check SII service
        try {
            $ch = curl_init('https://maullin.sii.cl/DTEWS/QueryEstUp.jws?wsdl');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $services['sii'] = $httpCode === 200 
                ? ['status' => 'healthy', 'message' => 'SII service available']
                : ['status' => 'unhealthy', 'message' => 'SII service unavailable'];
        } catch (\Exception $e) {
            $services['sii'] = ['status' => 'unhealthy', 'message' => $e->getMessage()];
        }
        
        return $services;
    }
}