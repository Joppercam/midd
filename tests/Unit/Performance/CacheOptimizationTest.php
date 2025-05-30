<?php

namespace Tests\Unit\Performance;

use Tests\TestCase;
use App\Services\CacheOptimizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CacheOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected CacheOptimizationService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheService = new CacheOptimizationService();
    }

    /** @test */
    public function it_can_cache_dashboard_data_with_intelligent_invalidation()
    {
        $userRole = 'admin';
        $tenantId = 1;
        $expectedData = ['revenue' => 10000, 'customers' => 50];

        $cachedData = $this->cacheService->cacheDashboardData($userRole, $tenantId, function () use ($expectedData) {
            return $expectedData;
        });

        $this->assertEquals($expectedData, $cachedData);

        // Verify data is cached
        $cachedAgain = $this->cacheService->cacheDashboardData($userRole, $tenantId, function () {
            return ['different' => 'data'];
        });

        $this->assertEquals($expectedData, $cachedAgain);
    }

    /** @test */
    public function it_can_cache_report_data_with_compression()
    {
        $reportType = 'sales';
        $params = ['start_date' => '2025-01-01', 'end_date' => '2025-01-31'];
        $largeData = array_fill(0, 1500, ['sale_id' => 1, 'amount' => 100]);

        $result = $this->cacheService->cacheReportData($reportType, $params, function () use ($largeData) {
            return $largeData;
        });

        $this->assertIsArray($result);
        $this->assertCount(1000, $result); // Should be compressed due to large size
    }

    /** @test */
    public function it_can_cache_heavy_queries_with_performance_monitoring()
    {
        $queryIdentifier = 'complex_financial_report';
        $expectedResult = ['total' => 50000, 'items' => 100];

        DB::shouldReceive('enableQueryLog')->once();
        DB::shouldReceive('getQueryLog')->once()->andReturn([]);
        DB::shouldReceive('disableQueryLog')->once();

        $result = $this->cacheService->cacheHeavyQuery($queryIdentifier, function () use ($expectedResult) {
            return $expectedResult;
        });

        $this->assertEquals($expectedResult, $result);
    }

    /** @test */
    public function it_can_warm_caches_for_multiple_roles()
    {
        $tenantId = 1;

        $result = $this->cacheService->warmCaches($tenantId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('warmed_caches', $result);
        $this->assertArrayHasKey('execution_time', $result);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertIsArray($result['warmed_caches']);
    }

    /** @test */
    public function it_can_invalidate_caches_by_tags()
    {
        $tags = ['dashboard', 'reports'];
        $tenantId = 1;

        // Cache some data first
        Cache::tags(['dashboard'])->put('test_key_1', 'test_value_1', 300);
        Cache::tags(['reports'])->put('test_key_2', 'test_value_2', 300);
        Cache::tags(['other'])->put('test_key_3', 'test_value_3', 300);

        $this->cacheService->invalidateCachesByTags($tags, $tenantId);

        // Tagged caches should be invalidated, but others should remain
        $this->assertNull(Cache::tags(['dashboard'])->get('test_key_1'));
        $this->assertNull(Cache::tags(['reports'])->get('test_key_2'));
        $this->assertEquals('test_value_3', Cache::tags(['other'])->get('test_key_3'));
    }

    /** @test */
    public function it_can_invalidate_hierarchical_cache()
    {
        $entity = 'customer';
        $entityId = 123;
        $tenantId = 1;

        // Mock cache invalidation
        Cache::shouldReceive('tags')
            ->with(['dashboard', 'reports', 'analytics', 'customer', 'customer_123', 'tenant_1'])
            ->once()
            ->andReturnSelf();
        
        Cache::shouldReceive('flush')->once();

        $this->cacheService->invalidateHierarchicalCache($entity, $entityId, $tenantId);
    }

    /** @test */
    public function it_can_optimize_queries_with_redis_caching()
    {
        $queryKey = 'product_inventory';
        $expectedData = ['products' => [['id' => 1, 'stock' => 10]]];
        $options = ['use_redis' => true, 'compress' => true];

        $result = $this->cacheService->optimizeQuery($queryKey, function () use ($expectedData) {
            return $expectedData;
        }, $options);

        $this->assertEquals($expectedData, $result);
    }

    /** @test */
    public function it_can_get_performance_metrics()
    {
        // Generate some metrics first
        $this->cacheService->cacheDashboardData('admin', 1, fn() => ['test' => 'data']);
        
        $metrics = $this->cacheService->getPerformanceMetrics();

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('metrics', $metrics);
        $this->assertArrayHasKey('cache_stats', $metrics);
        $this->assertArrayHasKey('memory_usage', $metrics);
        $this->assertArrayHasKey('recommendations', $metrics);
    }

    /** @test */
    public function it_can_optimize_cache_configuration_automatically()
    {
        $optimizations = $this->cacheService->optimizeCacheConfiguration();

        $this->assertIsArray($optimizations);
        $this->assertArrayHasKey('optimizations', $optimizations);
        $this->assertArrayHasKey('new_config', $optimizations);
        $this->assertArrayHasKey('timestamp', $optimizations);
    }

    /** @test */
    public function it_can_preload_critical_data()
    {
        $tenantId = 1;

        // This should not throw any exceptions
        $this->cacheService->preloadCriticalData($tenantId);
        
        // Test passes if no exceptions are thrown
        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_cache_failures_gracefully()
    {
        // Mock Redis failure
        Cache::shouldReceive('tags')->andThrow(new \Exception('Redis connection failed'));

        $result = $this->cacheService->cacheDashboardData('admin', 1, function () {
            return ['fallback' => 'data'];
        });

        // Should fall back to generating data directly
        $this->assertEquals(['fallback' => 'data'], $result);
    }

    /** @test */
    public function it_compresses_large_datasets_automatically()
    {
        $largeDataset = array_fill(0, 2000, ['id' => 1, 'data' => str_repeat('x', 100)]);
        
        $reportType = 'large_report';
        $params = ['type' => 'comprehensive'];

        $result = $this->cacheService->cacheReportData($reportType, $params, function () use ($largeDataset) {
            return $largeDataset;
        });

        // Should be compressed (limited to 1000 items)
        $this->assertLessThanOrEqual(1000, count($result));
    }

    /** @test */
    public function it_records_performance_metrics_correctly()
    {
        $queryIdentifier = 'test_query';
        
        DB::shouldReceive('enableQueryLog')->once();
        DB::shouldReceive('getQueryLog')->once()->andReturn([]);
        DB::shouldReceive('disableQueryLog')->once();

        $this->cacheService->cacheHeavyQuery($queryIdentifier, function () {
            usleep(10000); // 10ms delay
            return ['test' => 'result'];
        });

        $metrics = $this->cacheService->getPerformanceMetrics();
        
        $this->assertArrayHasKey('metrics', $metrics);
        $this->assertIsArray($metrics['metrics']);
    }

    /** @test */
    public function it_can_handle_multi_level_caching()
    {
        $key = 'test_multi_level';
        $expectedData = ['level' => 'test'];
        $options = ['l1_ttl' => 60, 'l2_ttl' => 600];

        $result = $this->cacheService->multiLevelCache($key, function () use ($expectedData) {
            return $expectedData;
        }, $options);

        $this->assertEquals($expectedData, $result);

        // Second call should hit L1 cache
        $result2 = $this->cacheService->multiLevelCache($key, function () {
            return ['different' => 'data'];
        }, $options);

        $this->assertEquals($expectedData, $result2);
    }

    /** @test */
    public function it_can_analyze_cache_hit_rates()
    {
        // This tests the protected method indirectly through optimization
        $optimizations = $this->cacheService->optimizeCacheConfiguration();
        
        $this->assertIsArray($optimizations['optimizations']);
    }

    /** @test */
    public function it_handles_memory_optimization()
    {
        $memoryUsage = $this->cacheService->getPerformanceMetrics()['memory_usage'];
        
        $this->assertArrayHasKey('current', $memoryUsage);
        $this->assertArrayHasKey('peak', $memoryUsage);
        $this->assertArrayHasKey('limit', $memoryUsage);
        $this->assertIsInt($memoryUsage['current']);
        $this->assertIsInt($memoryUsage['peak']);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }
}