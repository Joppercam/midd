<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

trait EfficientPagination
{
    /**
     * Perform efficient cursor-based pagination for large datasets
     */
    public function scopeCursorPaginate(Builder $query, int $perPage = 25, ?string $cursor = null, string $cursorColumn = 'id')
    {
        $perPage = min($perPage, config('performance.max_page_size', 100));
        
        // Apply cursor conditions
        if ($cursor) {
            $cursorValue = $this->decodeCursor($cursor);
            $query->where($cursorColumn, '>', $cursorValue);
        }
        
        // Get one extra record to determine if there are more pages
        $items = $query->orderBy($cursorColumn, 'asc')
                      ->limit($perPage + 1)
                      ->get();
        
        $hasMore = $items->count() > $perPage;
        
        if ($hasMore) {
            $items = $items->take($perPage);
        }
        
        $nextCursor = null;
        if ($hasMore && $items->isNotEmpty()) {
            $lastItem = $items->last();
            $nextCursor = $this->encodeCursor($lastItem->{$cursorColumn});
        }
        
        return [
            'data' => $items,
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
            'per_page' => $perPage
        ];
    }

    /**
     * Smart pagination that chooses the best strategy based on dataset size
     */
    public function scopeSmartPaginate(Builder $query, int $perPage = 25, ?int $page = null)
    {
        $perPage = min($perPage, config('performance.max_page_size', 100));
        $estimatedCount = $this->estimateQueryResultCount($query);
        
        // Use cursor pagination for large datasets
        if ($estimatedCount > config('performance.large_dataset_threshold', 10000)) {
            return $this->scopeCursorPaginate($query, $perPage);
        }
        
        // Use cached count for medium datasets
        if ($estimatedCount > 1000) {
            return $this->cachedPaginate($query, $perPage, $page);
        }
        
        // Use regular pagination for small datasets
        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Paginate with cached total count for better performance
     */
    public function cachedPaginate(Builder $query, int $perPage = 25, ?int $page = null)
    {
        $page = $page ?: Paginator::resolveCurrentPage();
        $cacheKey = $this->generateCountCacheKey($query);
        
        // Get cached count or calculate it
        $total = Cache::remember($cacheKey, 300, function () use ($query) {
            return $query->toBase()->getCountForPagination();
        });
        
        // Calculate offset
        $offset = ($page - 1) * $perPage;
        
        // Get items for current page
        $items = $query->offset($offset)->limit($perPage)->get();
        
        // Create paginator
        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => 'page',
            ]
        );
    }

    /**
     * Optimized pagination with minimal count queries
     */
    public function scopeOptimizedPaginate(Builder $query, int $perPage = 25, ?int $page = null)
    {
        $page = $page ?: Paginator::resolveCurrentPage();
        $perPage = min($perPage, config('performance.max_page_size', 100));
        
        // For first page, we can estimate if there are more pages
        if ($page === 1) {
            return $this->firstPageOptimizedPaginate($query, $perPage);
        }
        
        // For subsequent pages, use cached count if available
        $cacheKey = $this->generateCountCacheKey($query);
        $cachedTotal = Cache::get($cacheKey);
        
        if ($cachedTotal !== null) {
            return $this->cachedPaginate($query, $perPage, $page);
        }
        
        // Fall back to cursor pagination for unknown large datasets
        return $this->scopeCursorPaginate($query, $perPage);
    }

    /**
     * Optimized first page pagination
     */
    protected function firstPageOptimizedPaginate(Builder $query, int $perPage)
    {
        // Get one extra record to check if there are more pages
        $items = $query->limit($perPage + 1)->get();
        $hasMorePages = $items->count() > $perPage;
        
        if ($hasMorePages) {
            $items = $items->take($perPage);
        }
        
        // Estimate total for first page (don't run expensive count query)
        $estimatedTotal = $hasMorePages ? $perPage * 2 : $items->count();
        
        return new LengthAwarePaginator(
            $items,
            $estimatedTotal,
            $perPage,
            1,
            [
                'path' => request()->url(),
                'pageName' => 'page',
                'hasMorePages' => $hasMorePages
            ]
        );
    }

    /**
     * Infinite scroll pagination - load more items
     */
    public function scopeInfiniteScroll(Builder $query, int $perPage = 25, ?string $lastId = null)
    {
        $perPage = min($perPage, config('performance.max_page_size', 100));
        
        if ($lastId) {
            $query->where('id', '<', $lastId);
        }
        
        $items = $query->orderBy('id', 'desc')
                      ->limit($perPage + 1)
                      ->get();
        
        $hasMore = $items->count() > $perPage;
        
        if ($hasMore) {
            $items = $items->take($perPage);
        }
        
        $lastId = $items->isNotEmpty() ? $items->last()->id : null;
        
        return [
            'data' => $items,
            'last_id' => $lastId,
            'has_more' => $hasMore,
            'per_page' => $perPage
        ];
    }

    /**
     * Paginate with efficient filtering and searching
     */
    public function scopeFilteredPaginate(Builder $query, array $filters = [], int $perPage = 25, ?int $page = null)
    {
        // Apply filters efficiently
        $query = $this->applyFiltersOptimally($query, $filters);
        
        // Use appropriate pagination strategy based on filters
        $estimatedComplexity = $this->estimateFilterComplexity($filters);
        
        if ($estimatedComplexity > 0.7) {
            // High complexity filters - use cursor pagination
            return $this->scopeCursorPaginate($query, $perPage);
        }
        
        // Medium/low complexity - use smart pagination
        return $this->scopeSmartPaginate($query, $perPage, $page);
    }

    /**
     * Batch process large datasets efficiently
     */
    public function scopeBatchProcess(Builder $query, callable $callback, int $batchSize = 1000)
    {
        $processed = 0;
        $errors = [];
        
        $query->orderBy('id')->chunk($batchSize, function (Collection $items) use ($callback, &$processed, &$errors) {
            try {
                $callback($items);
                $processed += $items->count();
            } catch (\Exception $e) {
                $errors[] = [
                    'batch_start_id' => $items->first()->id ?? null,
                    'batch_end_id' => $items->last()->id ?? null,
                    'error' => $e->getMessage(),
                    'count' => $items->count()
                ];
            }
        });
        
        return [
            'processed' => $processed,
            'errors' => $errors,
            'success_rate' => $processed / ($processed + count($errors))
        ];
    }

    /**
     * Memory-efficient iteration for very large datasets
     */
    public function scopeLazyIterate(Builder $query, int $chunkSize = 1000)
    {
        return $query->lazy($chunkSize);
    }

    /**
     * Get aggregated data with pagination
     */
    public function scopeAggregatedPaginate(Builder $query, array $aggregations, int $perPage = 25, ?int $page = null)
    {
        $page = $page ?: Paginator::resolveCurrentPage();
        $offset = ($page - 1) * $perPage;
        
        // Build aggregation query
        $selectFields = ['*'];
        foreach ($aggregations as $field => $functions) {
            if (!is_array($functions)) {
                $functions = [$functions];
            }
            
            foreach ($functions as $function) {
                $selectFields[] = DB::raw("{$function}({$field}) as {$field}_{$function}");
            }
        }
        
        // Get total count first
        $total = $query->count();
        
        // Get aggregated data for current page
        $items = $query->select($selectFields)
                      ->offset($offset)
                      ->limit($perPage)
                      ->get();
        
        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => 'page',
            ]
        );
    }

    /**
     * Generate cache key for count queries
     */
    protected function generateCountCacheKey(Builder $query): string
    {
        $sql = $query->toSql();
        $bindings = $query->getBindings();
        $tenant = auth()->user()->tenant_id ?? 'global';
        
        return 'pagination_count:' . $tenant . ':' . md5($sql . serialize($bindings));
    }

    /**
     * Estimate query result count without executing expensive COUNT(*)
     */
    protected function estimateQueryResultCount(Builder $query): int
    {
        // Use table statistics if available
        $table = $query->getModel()->getTable();
        
        try {
            // Get approximate row count from information_schema
            $estimate = DB::select("
                SELECT table_rows 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() 
                AND table_name = ?
            ", [$table]);
            
            if (!empty($estimate)) {
                return (int) $estimate[0]->table_rows;
            }
        } catch (\Exception $e) {
            // Fallback to analyzing query complexity
        }
        
        // Fallback: analyze query for complexity indicators
        return $this->analyzeQueryComplexity($query);
    }

    /**
     * Analyze query complexity to estimate result size
     */
    protected function analyzeQueryComplexity(Builder $query): int
    {
        $sql = strtolower($query->toSql());
        
        // Simple heuristics based on query patterns
        if (str_contains($sql, 'join')) {
            return 5000; // Joins typically return more rows
        }
        
        if (str_contains($sql, 'where') && str_contains($sql, 'like')) {
            return 1000; // Text search might return many results
        }
        
        if (str_contains($sql, 'where')) {
            return 500; // Filtered queries typically return fewer rows
        }
        
        return 2000; // Default estimate
    }

    /**
     * Apply filters in the most optimal order
     */
    protected function applyFiltersOptimally(Builder $query, array $filters): Builder
    {
        // Sort filters by selectivity (more selective filters first)
        $sortedFilters = $this->sortFiltersBySelectivity($filters);
        
        foreach ($sortedFilters as $field => $value) {
            if (is_array($value)) {
                $query->whereIn($field, $value);
            } elseif (str_starts_with($field, 'search_')) {
                $searchField = str_replace('search_', '', $field);
                $query->where($searchField, 'LIKE', "%{$value}%");
            } else {
                $query->where($field, $value);
            }
        }
        
        return $query;
    }

    /**
     * Sort filters by selectivity for optimal query performance
     */
    protected function sortFiltersBySelectivity(array $filters): array
    {
        // Define selectivity weights (higher = more selective)
        $selectivityWeights = [
            'id' => 100,
            'uuid' => 100,
            'email' => 90,
            'phone' => 80,
            'status' => 60,
            'type' => 50,
            'category_id' => 40,
            'tenant_id' => 30,
            'created_at' => 20,
            'updated_at' => 10,
        ];
        
        uksort($filters, function ($a, $b) use ($selectivityWeights) {
            $weightA = $selectivityWeights[$a] ?? 1;
            $weightB = $selectivityWeights[$b] ?? 1;
            
            return $weightB <=> $weightA; // Sort descending (most selective first)
        });
        
        return $filters;
    }

    /**
     * Estimate filter complexity for choosing pagination strategy
     */
    protected function estimateFilterComplexity(array $filters): float
    {
        $complexity = 0;
        
        foreach ($filters as $field => $value) {
            if (str_starts_with($field, 'search_')) {
                $complexity += 0.3; // Text search adds complexity
            } elseif (is_array($value)) {
                $complexity += 0.2 * count($value); // IN clauses add complexity
            } else {
                $complexity += 0.1; // Simple equality adds minimal complexity
            }
        }
        
        return min($complexity, 1.0); // Cap at 1.0
    }

    /**
     * Encode cursor value
     */
    protected function encodeCursor($value): string
    {
        return base64_encode(json_encode($value));
    }

    /**
     * Decode cursor value
     */
    protected function decodeCursor(string $cursor)
    {
        return json_decode(base64_decode($cursor), true);
    }

    /**
     * Clear pagination cache for this model
     */
    public function clearPaginationCache(): void
    {
        $tenant = auth()->user()->tenant_id ?? 'global';
        $pattern = "pagination_count:{$tenant}:*";
        
        $redis = Cache::getRedis();
        $keys = $redis->keys($pattern);
        
        if (!empty($keys)) {
            $redis->del($keys);
        }
    }

    /**
     * Get pagination performance metrics
     */
    public function getPaginationMetrics(): array
    {
        $tenant = auth()->user()->tenant_id ?? 'global';
        $pattern = "pagination_count:{$tenant}:*";
        
        $redis = Cache::getRedis();
        $keys = $redis->keys($pattern);
        
        return [
            'cached_counts' => count($keys),
            'cache_hit_rate' => $this->calculateCacheHitRate(),
            'average_response_time' => $this->getAverageResponseTime(),
            'most_accessed_pages' => $this->getMostAccessedPages()
        ];
    }

    /**
     * Calculate cache hit rate for pagination
     */
    protected function calculateCacheHitRate(): float
    {
        // This would be implemented with actual metrics collection
        return 0.85; // Placeholder
    }

    /**
     * Get average response time for pagination queries
     */
    protected function getAverageResponseTime(): float
    {
        // This would be implemented with actual metrics collection
        return 150.5; // Placeholder in milliseconds
    }

    /**
     * Get most accessed pagination pages
     */
    protected function getMostAccessedPages(): array
    {
        // This would be implemented with actual metrics collection
        return [
            ['page' => 1, 'access_count' => 1250],
            ['page' => 2, 'access_count' => 890],
            ['page' => 3, 'access_count' => 450]
        ];
    }
}