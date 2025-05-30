<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiResponseOptimization
{
    protected array $config;

    public function __construct()
    {
        $this->config = config('performance.api', [
            'enable_compression' => true,
            'compression_threshold' => 1024,
            'enable_etag' => true,
            'enable_last_modified' => true,
        ]);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only optimize API responses
        if (!$this->isApiRequest($request) || !$response instanceof JsonResponse) {
            return $response;
        }

        $startTime = microtime(true);

        // Apply optimizations
        $this->applyCompressionOptimization($request, $response);
        $this->applyCachingHeaders($request, $response);
        $this->applyContentOptimization($response);
        $this->addPerformanceHeaders($response);

        $optimizationTime = (microtime(true) - $startTime) * 1000;
        
        // Add optimization metrics to response headers (for debugging)
        if (config('app.debug')) {
            $response->headers->set('X-Optimization-Time', round($optimizationTime, 2) . 'ms');
        }

        return $response;
    }

    /**
     * Check if this is an API request
     */
    protected function isApiRequest(Request $request): bool
    {
        return $request->is('api/*') || 
               $request->expectsJson() || 
               $request->header('Accept') === 'application/json';
    }

    /**
     * Apply compression optimization
     */
    protected function applyCompressionOptimization(Request $request, JsonResponse $response): void
    {
        if (!$this->config['enable_compression']) {
            return;
        }

        $content = $response->getContent();
        $contentLength = strlen($content);

        // Only compress if content is larger than threshold
        if ($contentLength < $this->config['compression_threshold']) {
            return;
        }

        $acceptEncoding = $request->header('Accept-Encoding', '');
        
        // Check if client supports compression
        if (str_contains($acceptEncoding, 'gzip')) {
            $compressedContent = gzencode($content, 6); // Compression level 6 (balanced)
            
            if ($compressedContent && strlen($compressedContent) < $contentLength) {
                $response->setContent($compressedContent);
                $response->headers->set('Content-Encoding', 'gzip');
                $response->headers->set('Content-Length', strlen($compressedContent));
                
                // Add compression ratio header for monitoring
                $compressionRatio = round((1 - strlen($compressedContent) / $contentLength) * 100, 1);
                $response->headers->set('X-Compression-Ratio', $compressionRatio . '%');
            }
        } elseif (str_contains($acceptEncoding, 'deflate')) {
            $compressedContent = gzdeflate($content, 6);
            
            if ($compressedContent && strlen($compressedContent) < $contentLength) {
                $response->setContent($compressedContent);
                $response->headers->set('Content-Encoding', 'deflate');
                $response->headers->set('Content-Length', strlen($compressedContent));
            }
        }
    }

    /**
     * Apply caching headers for better performance
     */
    protected function applyCachingHeaders(Request $request, JsonResponse $response): void
    {
        $data = $response->getData(true);
        
        // Generate ETag if enabled
        if ($this->config['enable_etag']) {
            $etag = $this->generateETag($data);
            $response->headers->set('ETag', $etag);
            
            // Check if client has cached version
            $clientETag = $request->header('If-None-Match');
            if ($clientETag === $etag) {
                $response->setStatusCode(304);
                $response->setContent('');
                return;
            }
        }

        // Add Last-Modified header if applicable
        if ($this->config['enable_last_modified'] && isset($data['updated_at'])) {
            $lastModified = strtotime($data['updated_at']);
            if ($lastModified) {
                $response->headers->set('Last-Modified', gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
                
                // Check If-Modified-Since header
                $ifModifiedSince = $request->header('If-Modified-Since');
                if ($ifModifiedSince && strtotime($ifModifiedSince) >= $lastModified) {
                    $response->setStatusCode(304);
                    $response->setContent('');
                    return;
                }
            }
        }

        // Add cache control headers based on content type
        $this->addCacheControlHeaders($request, $response, $data);
    }

    /**
     * Add appropriate cache control headers
     */
    protected function addCacheControlHeaders(Request $request, JsonResponse $response, array $data): void
    {
        $route = $request->route();
        $routeName = $route ? $route->getName() : '';
        
        // Determine cache strategy based on route and data characteristics
        if ($this->isStaticData($routeName, $data)) {
            // Static data can be cached longer
            $response->headers->set('Cache-Control', 'public, max-age=3600, s-maxage=7200');
        } elseif ($this->isUserSpecificData($routeName, $data)) {
            // User-specific data should be private
            $response->headers->set('Cache-Control', 'private, max-age=300');
        } elseif ($this->isFrequentlyChangingData($routeName, $data)) {
            // Frequently changing data should have short cache
            $response->headers->set('Cache-Control', 'public, max-age=60');
        } else {
            // Default caching
            $response->headers->set('Cache-Control', 'public, max-age=600');
        }

        // Add Vary header for content negotiation
        $response->headers->set('Vary', 'Accept, Accept-Encoding, Authorization');
    }

    /**
     * Optimize response content for better performance
     */
    protected function applyContentOptimization(JsonResponse $response): void
    {
        $data = $response->getData(true);
        
        if (!is_array($data)) {
            return;
        }

        // Remove null values to reduce payload size
        $optimizedData = $this->removeNullValues($data);
        
        // Optimize numeric values
        $optimizedData = $this->optimizeNumericValues($optimizedData);
        
        // Compress repeated data structures
        $optimizedData = $this->compressRepeatedStructures($optimizedData);
        
        // Apply field filtering if requested
        $optimizedData = $this->applyFieldFiltering($optimizedData);
        
        $response->setData($optimizedData);
    }

    /**
     * Add performance-related headers
     */
    protected function addPerformanceHeaders(JsonResponse $response): void
    {
        // Add response time
        if (defined('LARAVEL_START')) {
            $responseTime = round((microtime(true) - LARAVEL_START) * 1000, 2);
            $response->headers->set('X-Response-Time', $responseTime . 'ms');
        }

        // Add memory usage
        $memoryUsage = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $response->headers->set('X-Memory-Usage', $memoryUsage . 'MB');

        // Add database query count if available
        if (config('app.debug')) {
            $queryCount = count(\DB::getQueryLog());
            $response->headers->set('X-Query-Count', $queryCount);
        }
    }

    /**
     * Generate ETag for response data
     */
    protected function generateETag(array $data): string
    {
        // Remove volatile fields that don't affect content
        $filteredData = $this->filterVolatileFields($data);
        
        return '"' . md5(json_encode($filteredData)) . '"';
    }

    /**
     * Filter out volatile fields that shouldn't affect ETag
     */
    protected function filterVolatileFields(array $data): array
    {
        $volatileFields = [
            'last_login_at',
            'online_status',
            'view_count',
            'temporary_fields'
        ];

        return $this->recursiveUnset($data, $volatileFields);
    }

    /**
     * Recursively unset fields from array
     */
    protected function recursiveUnset(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            unset($data[$field]);
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->recursiveUnset($value, $fields);
            }
        }

        return $data;
    }

    /**
     * Check if data is static and can be cached longer
     */
    protected function isStaticData(string $routeName, array $data): bool
    {
        $staticRoutes = [
            'api.countries',
            'api.currencies',
            'api.timezones',
            'api.settings.system'
        ];

        return in_array($routeName, $staticRoutes) ||
               (isset($data['is_static']) && $data['is_static'] === true);
    }

    /**
     * Check if data is user-specific
     */
    protected function isUserSpecificData(string $routeName, array $data): bool
    {
        $userSpecificIndicators = [
            'user_id',
            'profile',
            'preferences',
            'permissions'
        ];

        foreach ($userSpecificIndicators as $indicator) {
            if (isset($data[$indicator])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if data changes frequently
     */
    protected function isFrequentlyChangingData(string $routeName, array $data): bool
    {
        $frequentChangeRoutes = [
            'api.dashboard.metrics',
            'api.analytics.realtime',
            'api.inventory.stock'
        ];

        return in_array($routeName, $frequentChangeRoutes);
    }

    /**
     * Remove null values to reduce payload size
     */
    protected function removeNullValues(array $data): array
    {
        return array_filter($data, function ($value) {
            if (is_array($value)) {
                return !empty($this->removeNullValues($value));
            }
            return $value !== null;
        });
    }

    /**
     * Optimize numeric values for better compression
     */
    protected function optimizeNumericValues(array $data): array
    {
        array_walk_recursive($data, function (&$value) {
            if (is_numeric($value)) {
                // Convert to appropriate type
                if (is_float($value) && floor($value) == $value) {
                    $value = (int) $value;
                } elseif (is_float($value)) {
                    // Round to reasonable precision
                    $value = round($value, 6);
                }
            }
        });

        return $data;
    }

    /**
     * Compress repeated data structures
     */
    protected function compressRepeatedStructures(array $data): array
    {
        // Look for repeated objects/arrays that can be referenced
        if (isset($data['data']) && is_array($data['data'])) {
            $data['data'] = $this->deduplicateArrayElements($data['data']);
        }

        return $data;
    }

    /**
     * Deduplicate array elements with common structures
     */
    protected function deduplicateArrayElements(array $items): array
    {
        if (count($items) < 2) {
            return $items;
        }

        // Find common keys across all items
        $allKeys = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $allKeys[] = array_keys($item);
            }
        }

        if (empty($allKeys)) {
            return $items;
        }

        $commonKeys = call_user_func_array('array_intersect', $allKeys);
        
        // If we have many common keys, we could optimize by factoring them out
        // This is a simplified implementation
        return $items;
    }

    /**
     * Apply field filtering based on request parameters
     */
    protected function applyFieldFiltering(array $data): array
    {
        $request = request();
        
        // Check for fields parameter
        $fields = $request->get('fields');
        if (!$fields) {
            return $data;
        }

        $allowedFields = explode(',', $fields);
        $allowedFields = array_map('trim', $allowedFields);

        return $this->filterFields($data, $allowedFields);
    }

    /**
     * Filter data to include only specified fields
     */
    protected function filterFields(array $data, array $allowedFields): array
    {
        if (isset($data['data']) && is_array($data['data'])) {
            // Handle paginated or collection responses
            $data['data'] = array_map(function ($item) use ($allowedFields) {
                if (is_array($item)) {
                    return array_intersect_key($item, array_flip($allowedFields));
                }
                return $item;
            }, $data['data']);
        } else {
            // Handle single resource responses
            $data = array_intersect_key($data, array_flip($allowedFields));
        }

        return $data;
    }

    /**
     * Log performance metrics for monitoring
     */
    protected function logPerformanceMetrics(Request $request, JsonResponse $response): void
    {
        if (!config('app.debug')) {
            return;
        }

        $metrics = [
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'response_size' => strlen($response->getContent()),
            'memory_usage' => memory_get_peak_usage(true),
            'response_time' => defined('LARAVEL_START') ? 
                round((microtime(true) - LARAVEL_START) * 1000, 2) : null,
        ];

        Log::channel('performance')->info('API Response Optimization Metrics', $metrics);
    }
}