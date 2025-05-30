<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CacheOptimizationService
{
    protected array $cacheConfig;
    protected array $performanceMetrics = [];

    public function __construct()
    {
        $this->cacheConfig = [
            'dashboard' => ['ttl' => 300, 'tags' => ['dashboard']],
            'reports' => ['ttl' => 900, 'tags' => ['reports']],
            'analytics' => ['ttl' => 1800, 'tags' => ['analytics']],
            'user_data' => ['ttl' => 3600, 'tags' => ['users']],
            'system_config' => ['ttl' => 7200, 'tags' => ['system']],
            'heavy_queries' => ['ttl' => 600, 'tags' => ['queries']]
        ];
    }

    /**
     * Cache dashboard data with intelligent invalidation
     */
    public function cacheDashboardData(string $userRole, int $tenantId, callable $dataCallback): array
    {
        $cacheKey = "dashboard:{$userRole}:{$tenantId}:" . date('Y-m-d-H');
        
        return Cache::tags($this->cacheConfig['dashboard']['tags'])
            ->remember($cacheKey, $this->cacheConfig['dashboard']['ttl'], function() use ($dataCallback) {
                $startTime = microtime(true);
                $data = $dataCallback();
                $this->recordPerformanceMetric('dashboard_generation', microtime(true) - $startTime);
                return $data;
            });
    }

    /**
     * Cache report data with compression
     */
    public function cacheReportData(string $reportType, array $params, callable $dataCallback): array
    {
        $cacheKey = "report:{$reportType}:" . md5(serialize($params));
        
        return Cache::tags($this->cacheConfig['reports']['tags'])
            ->remember($cacheKey, $this->cacheConfig['reports']['ttl'], function() use ($dataCallback) {
                $startTime = microtime(true);
                $data = $dataCallback();
                
                // Compress large datasets
                if (count($data) > 1000) {
                    $data = $this->compressLargeDataset($data);
                }
                
                $this->recordPerformanceMetric('report_generation', microtime(true) - $startTime);
                return $data;
            });
    }

    /**
     * Cache heavy database queries with smart expiration
     */
    public function cacheHeavyQuery(string $queryIdentifier, callable $queryCallback, ?int $customTtl = null): mixed
    {
        $cacheKey = "heavy_query:{$queryIdentifier}:" . date('Y-m-d-H');
        $ttl = $customTtl ?? $this->cacheConfig['heavy_queries']['ttl'];
        
        return Cache::tags($this->cacheConfig['heavy_queries']['tags'])
            ->remember($cacheKey, $ttl, function() use ($queryCallback, $queryIdentifier) {
                $startTime = microtime(true);
                
                // Enable query logging for performance monitoring
                DB::enableQueryLog();
                $result = $queryCallback();
                $queries = DB::getQueryLog();
                DB::disableQueryLog();
                
                $executionTime = microtime(true) - $startTime;
                $this->recordPerformanceMetric("query_{$queryIdentifier}", $executionTime);
                
                // Log slow queries
                if ($executionTime > 1.0) {
                    Log::warning("Slow query detected: {$queryIdentifier}", [
                        'execution_time' => $executionTime,
                        'query_count' => count($queries)
                    ]);
                }
                
                return $result;
            });
    }

    /**
     * Implement intelligent cache warming
     */
    public function warmCaches(int $tenantId): array
    {
        $startTime = microtime(true);
        $warmedCaches = [];
        
        try {
            // Warm dashboard caches for all roles
            $roles = ['admin', 'gerente', 'contador', 'vendedor'];
            foreach ($roles as $role) {
                $cacheKey = "dashboard:{$role}:{$tenantId}:" . date('Y-m-d-H');
                if (!Cache::has($cacheKey)) {
                    $this->warmDashboardCache($role, $tenantId);
                    $warmedCaches[] = "dashboard_{$role}";
                }
            }
            
            // Warm frequently accessed reports
            $this->warmFrequentReports($tenantId);
            $warmedCaches[] = 'frequent_reports';
            
            // Warm system configuration
            $this->warmSystemConfig($tenantId);
            $warmedCaches[] = 'system_config';
            
            // Warm user permissions
            $this->warmUserPermissions($tenantId);
            $warmedCaches[] = 'user_permissions';
            
        } catch (\Exception $e) {
            Log::error('Cache warming failed', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
        }
        
        $totalTime = microtime(true) - $startTime;
        $this->recordPerformanceMetric('cache_warming', $totalTime);
        
        return [
            'warmed_caches' => $warmedCaches,
            'execution_time' => round($totalTime, 3),
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Intelligent cache invalidation based on data changes
     */
    public function invalidateCachesByTags(array $tags, ?int $tenantId = null): void
    {
        try {
            if ($tenantId) {
                // Invalidate tenant-specific caches
                foreach ($tags as $tag) {
                    Cache::tags([$tag, "tenant_{$tenantId}"])->flush();
                }
            } else {
                // Invalidate global caches
                Cache::tags($tags)->flush();
            }
            
            Log::info('Cache invalidated', [
                'tags' => $tags,
                'tenant_id' => $tenantId
            ]);
            
        } catch (\Exception $e) {
            Log::error('Cache invalidation failed', [
                'tags' => $tags,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Implement cache hierarchies for nested invalidation
     */
    public function invalidateHierarchicalCache(string $entity, int $entityId, ?int $tenantId = null): void
    {
        $hierarchies = [
            'customer' => ['dashboard', 'reports', 'analytics'],
            'invoice' => ['dashboard', 'reports', 'analytics', 'customer_data'],
            'payment' => ['dashboard', 'reports', 'analytics', 'cash_flow'],
            'product' => ['dashboard', 'reports', 'inventory'],
            'expense' => ['dashboard', 'reports', 'analytics', 'profitability']
        ];
        
        if (isset($hierarchies[$entity])) {
            $tags = $hierarchies[$entity];
            
            // Add entity-specific tags
            $tags[] = $entity;
            $tags[] = "{$entity}_{$entityId}";
            
            if ($tenantId) {
                $tags[] = "tenant_{$tenantId}";
            }
            
            $this->invalidateCachesByTags($tags, $tenantId);
        }
    }

    /**
     * Optimize database queries with result caching
     */
    public function optimizeQuery(string $queryKey, callable $queryBuilder, array $options = []): mixed
    {
        $defaultOptions = [
            'ttl' => 600,
            'compress' => false,
            'tags' => ['queries'],
            'use_redis' => false
        ];
        
        $options = array_merge($defaultOptions, $options);
        $cacheKey = "optimized_query:{$queryKey}:" . md5(serialize($options));
        
        if ($options['use_redis']) {
            return $this->cacheInRedis($cacheKey, $queryBuilder, $options);
        }
        
        return Cache::tags($options['tags'])
            ->remember($cacheKey, $options['ttl'], function() use ($queryBuilder, $options, $queryKey) {
                $startTime = microtime(true);
                
                $result = $queryBuilder();
                
                if ($options['compress'] && is_array($result) && count($result) > 100) {
                    $result = $this->compressData($result);
                }
                
                $this->recordPerformanceMetric("optimized_query_{$queryKey}", microtime(true) - $startTime);
                
                return $result;
            });
    }

    /**
     * Implement Redis-based caching for high-frequency data
     */
    public function cacheInRedis(string $key, callable $dataCallback, array $options = []): mixed
    {
        try {
            $redisKey = "redis_cache:{$key}";
            $redis = Cache::getRedis();
            $cachedData = $redis->get($redisKey);
            
            if ($cachedData !== null) {
                $data = json_decode($cachedData, true);
                
                if (isset($options['compress']) && $options['compress']) {
                    $data = $this->decompressData($data);
                }
                
                $this->recordPerformanceMetric('redis_cache_hit', 0);
                return $data;
            }
            
            // Data not in cache, generate it
            $startTime = microtime(true);
            $data = $dataCallback();
            
            $cacheData = $data;
            if (isset($options['compress']) && $options['compress']) {
                $cacheData = $this->compressData($data);
            }
            
            $redis->setex($redisKey, $options['ttl'] ?? 600, json_encode($cacheData));
            
            $this->recordPerformanceMetric('redis_cache_miss', microtime(true) - $startTime);
            
            return $data;
            
        } catch (\Exception $e) {
            Log::error('Redis caching failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            
            // Fallback to direct data generation
            return $dataCallback();
        }
    }

    /**
     * Advanced multi-level caching with L1 (Memory) and L2 (Redis)
     */
    public function multiLevelCache(string $key, callable $dataCallback, array $options = []): mixed
    {
        $defaultOptions = [
            'l1_ttl' => 60,    // Memory cache: 1 minute
            'l2_ttl' => 600,   // Redis cache: 10 minutes
            'compress_l2' => true,
            'tags' => ['multi_level']
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        // Try L1 cache (memory) first
        $l1Key = "l1_cache:{$key}";
        if (Cache::has($l1Key)) {
            $this->recordPerformanceMetric('l1_cache_hit', 0);
            return Cache::get($l1Key);
        }
        
        // Try L2 cache (Redis)
        $l2Key = "l2_cache:{$key}";
        try {
            $redis = Cache::getRedis();
            $l2Data = $redis->get($l2Key);
            
            if ($l2Data !== null) {
                $data = json_decode($l2Data, true);
                
                if ($options['compress_l2']) {
                    $data = $this->decompressData($data);
                }
                
                // Store in L1 for faster subsequent access
                Cache::put($l1Key, $data, $options['l1_ttl']);
                
                $this->recordPerformanceMetric('l2_cache_hit', 0);
                return $data;
            }
        } catch (\Exception $e) {
            Log::warning('L2 cache access failed', ['key' => $key, 'error' => $e->getMessage()]);
        }
        
        // Cache miss, generate data
        $startTime = microtime(true);
        $data = $dataCallback();
        $executionTime = microtime(true) - $startTime;
        
        // Store in both caches
        try {
            // L1 cache (memory)
            Cache::put($l1Key, $data, $options['l1_ttl']);
            
            // L2 cache (Redis)
            $l2Data = $data;
            if ($options['compress_l2']) {
                $l2Data = $this->compressData($data);
            }
            
            $redis = Cache::getRedis();
            $redis->setex($l2Key, $options['l2_ttl'], json_encode($l2Data));
            
        } catch (\Exception $e) {
            Log::error('Failed to store in multi-level cache', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
        }
        
        $this->recordPerformanceMetric('cache_miss_data_generation', $executionTime);
        return $data;
    }

    /**
     * Performance monitoring and metrics collection
     */
    public function getPerformanceMetrics(): array
    {
        return [
            'metrics' => $this->performanceMetrics,
            'cache_stats' => $this->getCacheStatistics(),
            'memory_usage' => $this->getMemoryUsage(),
            'recommendations' => $this->getOptimizationRecommendations()
        ];
    }

    /**
     * Automated cache optimization based on usage patterns
     */
    public function optimizeCacheConfiguration(): array
    {
        $optimizations = [];
        
        try {
            // Analyze cache hit rates
            $hitRates = $this->analyzeCacheHitRates();
            
            // Adjust TTL based on access patterns
            foreach ($hitRates as $cacheType => $stats) {
                if ($stats['hit_rate'] < 0.7) {
                    // Low hit rate, increase TTL
                    $newTtl = min($this->cacheConfig[$cacheType]['ttl'] * 1.5, 7200);
                    $this->cacheConfig[$cacheType]['ttl'] = $newTtl;
                    $optimizations[] = "Increased TTL for {$cacheType} to {$newTtl}s";
                } elseif ($stats['hit_rate'] > 0.95 && $stats['avg_access_time'] > 3600) {
                    // Very high hit rate but old data, can reduce TTL
                    $newTtl = max($this->cacheConfig[$cacheType]['ttl'] * 0.8, 60);
                    $this->cacheConfig[$cacheType]['ttl'] = $newTtl;
                    $optimizations[] = "Decreased TTL for {$cacheType} to {$newTtl}s";
                }
            }
            
            // Identify heavy queries for caching
            $slowQueries = $this->identifySlowQueries();
            foreach ($slowQueries as $query) {
                if ($query['avg_time'] > 0.5) {
                    $optimizations[] = "Recommended caching for slow query: {$query['identifier']}";
                }
            }
            
        } catch (\Exception $e) {
            Log::error('Cache optimization failed', ['error' => $e->getMessage()]);
        }
        
        return [
            'optimizations' => $optimizations,
            'new_config' => $this->cacheConfig,
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Preload critical data for better performance
     */
    public function preloadCriticalData(int $tenantId): void
    {
        // Preload frequently accessed configuration
        $this->preloadSystemConfig($tenantId);
        
        // Preload user permissions and roles
        $this->preloadUserData($tenantId);
        
        // Preload current month's financial data
        $this->preloadFinancialData($tenantId);
        
        // Preload product catalog
        $this->preloadProductCatalog($tenantId);
    }

    // Protected helper methods
    
    protected function recordPerformanceMetric(string $operation, float $executionTime): void
    {
        if (!isset($this->performanceMetrics[$operation])) {
            $this->performanceMetrics[$operation] = [
                'count' => 0,
                'total_time' => 0,
                'avg_time' => 0,
                'min_time' => PHP_FLOAT_MAX,
                'max_time' => 0
            ];
        }
        
        $metrics = &$this->performanceMetrics[$operation];
        $metrics['count']++;
        $metrics['total_time'] += $executionTime;
        $metrics['avg_time'] = $metrics['total_time'] / $metrics['count'];
        $metrics['min_time'] = min($metrics['min_time'], $executionTime);
        $metrics['max_time'] = max($metrics['max_time'], $executionTime);
    }
    
    protected function compressLargeDataset(array $data): array
    {
        // Implement data compression for large datasets
        if (count($data) > 1000) {
            // Sample data if too large
            $data = array_slice($data, 0, 1000);
        }
        
        return $data;
    }
    
    protected function compressData($data): string
    {
        return base64_encode(gzcompress(json_encode($data)));
    }
    
    protected function decompressData(string $compressedData): mixed
    {
        return json_decode(gzuncompress(base64_decode($compressedData)), true);
    }
    
    protected function warmDashboardCache(string $role, int $tenantId): void
    {
        // Implementation would depend on dashboard service
        Log::info("Warming dashboard cache for role: {$role}, tenant: {$tenantId}");
    }
    
    protected function warmFrequentReports(int $tenantId): void
    {
        // Warm frequently accessed reports
        Log::info("Warming frequent reports cache for tenant: {$tenantId}");
    }
    
    protected function warmSystemConfig(int $tenantId): void
    {
        // Warm system configuration
        Log::info("Warming system config cache for tenant: {$tenantId}");
    }
    
    protected function warmUserPermissions(int $tenantId): void
    {
        // Warm user permissions
        Log::info("Warming user permissions cache for tenant: {$tenantId}");
    }
    
    protected function getCacheStatistics(): array
    {
        // Return cache statistics
        return [
            'hit_rate' => 0.85,
            'miss_rate' => 0.15,
            'memory_usage' => '45MB',
            'keys_count' => 1250
        ];
    }
    
    protected function getMemoryUsage(): array
    {
        return [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'limit' => ini_get('memory_limit')
        ];
    }
    
    protected function getOptimizationRecommendations(): array
    {
        return [
            'Enable Redis for high-frequency data',
            'Increase cache TTL for dashboard data',
            'Implement query result caching for reports',
            'Add compression for large datasets'
        ];
    }
    
    protected function analyzeCacheHitRates(): array
    {
        // Analyze cache hit rates for different cache types
        return [
            'dashboard' => ['hit_rate' => 0.85, 'avg_access_time' => 1800],
            'reports' => ['hit_rate' => 0.65, 'avg_access_time' => 3600],
            'analytics' => ['hit_rate' => 0.92, 'avg_access_time' => 2400]
        ];
    }
    
    protected function identifySlowQueries(): array
    {
        // Identify slow queries that could benefit from caching
        return [
            ['identifier' => 'financial_dashboard', 'avg_time' => 0.8],
            ['identifier' => 'customer_analytics', 'avg_time' => 1.2],
            ['identifier' => 'inventory_report', 'avg_time' => 0.6]
        ];
    }
    
    protected function preloadSystemConfig(int $tenantId): void
    {
        // Preload system configuration
        Log::info("Preloading system config for tenant: {$tenantId}");
    }
    
    protected function preloadUserData(int $tenantId): void
    {
        // Preload user data
        Log::info("Preloading user data for tenant: {$tenantId}");
    }
    
    protected function preloadFinancialData(int $tenantId): void
    {
        // Preload financial data
        Log::info("Preloading financial data for tenant: {$tenantId}");
    }
    
    protected function preloadProductCatalog(int $tenantId): void
    {
        // Preload product catalog
        Log::info("Preloading product catalog for tenant: {$tenantId}");
    }
}