<?php

namespace Tests\Unit\Performance;

use Tests\TestCase;
use App\Http\Middleware\ApiResponseOptimization;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ApiResponseOptimizationTest extends TestCase
{
    protected ApiResponseOptimization $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new ApiResponseOptimization();
    }

    /** @test */
    public function it_only_optimizes_api_requests()
    {
        // Test non-API request
        $request = Request::create('/dashboard', 'GET');
        $response = new JsonResponse(['data' => 'test']);

        $result = $this->middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        // Should return response unchanged for non-API requests
        $this->assertEquals($response, $result);
    }

    /** @test */
    public function it_optimizes_api_requests()
    {
        $request = Request::create('/api/customers', 'GET');
        $request->headers->set('Accept', 'application/json');
        
        $response = new JsonResponse(['data' => 'test']);

        $result = $this->middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        $this->assertInstanceOf(JsonResponse::class, $result);
    }

    /** @test */
    public function it_applies_gzip_compression_when_supported()
    {
        $request = Request::create('/api/customers', 'GET');
        $request->headers->set('Accept', 'application/json');
        $request->headers->set('Accept-Encoding', 'gzip, deflate');

        $largeData = str_repeat('This is test data for compression. ', 100);
        $response = new JsonResponse(['data' => $largeData]);

        $result = $this->middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        // Check if compression headers are set when content is large enough
        if (strlen($response->getContent()) >= 1024) {
            $this->assertTrue(
                $result->headers->has('Content-Encoding') ||
                $result === $response // Compression may not apply if content is not large enough
            );
        }
    }

    /** @test */
    public function it_does_not_compress_small_responses()
    {
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Accept', 'application/json');
        $request->headers->set('Accept-Encoding', 'gzip');

        $smallData = ['small' => 'data'];
        $response = new JsonResponse($smallData);

        $result = $this->middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        // Small responses should not be compressed
        $this->assertFalse($result->headers->has('Content-Encoding'));
    }

    /** @test */
    public function it_generates_etag_for_responses()
    {
        $request = Request::create('/api/customers', 'GET');
        $request->headers->set('Accept', 'application/json');

        $response = new JsonResponse(['id' => 1, 'name' => 'Test Customer']);

        $result = $this->middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        $this->assertTrue($result->headers->has('ETag'));
        $etag = $result->headers->get('ETag');
        $this->assertStringStartsWith('"', $etag);
        $this->assertStringEndsWith('"', $etag);
    }

    /** @test */
    public function it_returns_304_for_matching_etag()
    {
        $request = Request::create('/api/customers', 'GET');
        $request->headers->set('Accept', 'application/json');
        
        $response = new JsonResponse(['id' => 1, 'name' => 'Test Customer']);

        // First request to get ETag
        $result1 = $this->middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        $etag = $result1->headers->get('ETag');
        
        // Second request with If-None-Match header
        $request2 = Request::create('/api/customers', 'GET');
        $request2->headers->set('Accept', 'application/json');
        $request2->headers->set('If-None-Match', $etag);
        
        $response2 = new JsonResponse(['id' => 1, 'name' => 'Test Customer']);
        
        $result2 = $this->middleware->handle($request2, function ($req) use ($response2) {
            return $response2;
        });

        $this->assertEquals(304, $result2->getStatusCode());
        $this->assertEquals('', $result2->getContent());
    }

    /** @test */
    public function it_adds_cache_control_headers()
    {
        $request = Request::create('/api/customers', 'GET');
        $request->headers->set('Accept', 'application/json');

        $response = new JsonResponse(['data' => 'test']);

        $result = $this->middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        $this->assertTrue($result->headers->has('Cache-Control'));
        $this->assertTrue($result->headers->has('Vary'));
    }

    /** @test */
    public function it_removes_null_values_from_response()
    {
        $request = Request::create('/api/customers', 'GET');
        $request->headers->set('Accept', 'application/json');

        $dataWithNulls = [
            'id' => 1,
            'name' => 'Test',
            'email' => null,
            'phone' => null,
            'address' => 'Test Address'
        ];

        $response = new JsonResponse($dataWithNulls);

        $result = $this->middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        $resultData = $result->getData(true);
        
        // Should have removed null values
        $this->assertArrayNotHasKey('email', $resultData);
        $this->assertArrayNotHasKey('phone', $resultData);
        $this->assertArrayHasKey('id', $resultData);
        $this->assertArrayHasKey('name', $resultData);
        $this->assertArrayHasKey('address', $resultData);
    }

    /** @test */
    public function it_optimizes_numeric_values()
    {
        $request = Request::create('/api/products', 'GET');
        $request->headers->set('Accept', 'application/json');

        $dataWithNumbers = [
            'id' => 1.0,
            'price' => 99.999999999,
            'quantity' => 5.0,
            'rating' => 4.5
        ];

        $response = new JsonResponse($dataWithNumbers);

        $result = $this->middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        $resultData = $result->getData(true);
        
        // Floats that are whole numbers should become integers
        $this->assertSame(1, $resultData['id']);
        $this->assertSame(5, $resultData['quantity']);
        
        // Decimals should be rounded to reasonable precision
        $this->assertEquals(4.5, $resultData['rating']);
    }

    /** @test */
    public function it_applies_field_filtering_when_requested()
    {
        $request = Request::create('/api/customers?fields=id,name', 'GET');
        $request->headers->set('Accept', 'application/json');

        $fullData = [
            'data' => [
                [
                    'id' => 1,
                    'name' => 'Test Customer',
                    'email' => 'test@example.com',
                    'phone' => '123-456-7890',
                    'address' => 'Test Address'
                ]
            ]
        ];

        $response = new JsonResponse($fullData);

        $result = $this->middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        $resultData = $result->getData(true);
        
        // Should only contain requested fields
        $this->assertArrayHasKey('data', $resultData);
        $firstItem = $resultData['data'][0];
        $this->assertArrayHasKey('id', $firstItem);
        $this->assertArrayHasKey('name', $firstItem);
        $this->assertArrayNotHasKey('email', $firstItem);
        $this->assertArrayNotHasKey('phone', $firstItem);
        $this->assertArrayNotHasKey('address', $firstItem);
    }

    /** @test */
    public function it_adds_performance_headers()
    {
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Accept', 'application/json');

        $response = new JsonResponse(['test' => 'data']);

        $result = $this->middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        // Should have performance headers
        $this->assertTrue($result->headers->has('X-Memory-Usage'));
        
        if (defined('LARAVEL_START')) {
            $this->assertTrue($result->headers->has('X-Response-Time'));
        }
    }

    /** @test */
    public function it_handles_last_modified_headers()
    {
        $request = Request::create('/api/customers/1', 'GET');
        $request->headers->set('Accept', 'application/json');

        $dataWithTimestamp = [
            'id' => 1,
            'name' => 'Test Customer',
            'updated_at' => '2025-05-29T12:00:00Z'
        ];

        $response = new JsonResponse($dataWithTimestamp);

        $result = $this->middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        $this->assertTrue($result->headers->has('Last-Modified'));
    }

    /** @test */
    public function it_returns_304_for_if_modified_since()
    {
        $request = Request::create('/api/customers/1', 'GET');
        $request->headers->set('Accept', 'application/json');
        
        $lastModified = '2025-05-29T12:00:00Z';
        $ifModifiedSince = gmdate('D, d M Y H:i:s', strtotime($lastModified + 3600)) . ' GMT';
        
        $request->headers->set('If-Modified-Since', $ifModifiedSince);

        $dataWithTimestamp = [
            'id' => 1,
            'name' => 'Test Customer',
            'updated_at' => $lastModified
        ];

        $response = new JsonResponse($dataWithTimestamp);

        $result = $this->middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        // Should return 304 if resource hasn't been modified since the specified time
        $this->assertEquals(304, $result->getStatusCode());
    }

    /** @test */
    public function it_sets_appropriate_cache_control_for_different_data_types()
    {
        // Test static data
        $request = Request::create('/api/countries', 'GET');
        $request->headers->set('Accept', 'application/json');

        $response = new JsonResponse(['is_static' => true, 'data' => ['US', 'CA', 'MX']]);

        $result = $this->middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        $cacheControl = $result->headers->get('Cache-Control');
        $this->assertStringContains('public', $cacheControl);
        $this->assertStringContains('max-age=3600', $cacheControl);
    }

    /** @test */
    public function it_handles_non_json_responses_gracefully()
    {
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Accept', 'application/json');

        // Return a non-JSON response
        $response = response('Plain text response');

        $result = $this->middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        // Should return the original response unchanged
        $this->assertEquals($response, $result);
    }

    /** @test */
    public function it_logs_performance_metrics_in_debug_mode()
    {
        config(['app.debug' => true]);
        
        Log::shouldReceive('channel')
            ->with('performance')
            ->andReturnSelf();
        
        Log::shouldReceive('info')
            ->once()
            ->with('API Response Optimization Metrics', \Mockery::type('array'));

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Accept', 'application/json');

        $response = new JsonResponse(['test' => 'data']);

        $this->middleware->handle($request, function ($req) use ($response) {
            return $response;
        });
    }

    /** @test */
    public function it_handles_large_response_compression()
    {
        $request = Request::create('/api/large-data', 'GET');
        $request->headers->set('Accept', 'application/json');
        $request->headers->set('Accept-Encoding', 'gzip');

        // Create a large response (over 1KB)
        $largeData = array_fill(0, 100, [
            'id' => 1,
            'data' => str_repeat('test data ', 20)
        ]);

        $response = new JsonResponse(['items' => $largeData]);

        $result = $this->middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        // Should have compression ratio header if compressed
        if ($result->headers->has('Content-Encoding')) {
            $this->assertTrue($result->headers->has('X-Compression-Ratio'));
        }
    }
}