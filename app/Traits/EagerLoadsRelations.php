<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

trait EagerLoadsRelations
{
    protected static array $globalEagerLoads = [];
    protected static array $conditionalEagerLoads = [];
    protected array $detectedQueries = [];

    /**
     * Configure automatic eager loading for common query patterns
     */
    protected static function bootEagerLoadsRelations(): void
    {
        static::addGlobalScope('auto_eager_load', function (Builder $builder) {
            $model = $builder->getModel();
            $tableName = $model->getTable();
            
            // Apply global eager loads for this model
            if (isset(static::$globalEagerLoads[$tableName])) {
                $builder->with(static::$globalEagerLoads[$tableName]);
            }
            
            // Apply conditional eager loads based on context
            $contextualLoads = static::getContextualEagerLoads($model);
            if (!empty($contextualLoads)) {
                $builder->with($contextualLoads);
            }
        });

        // Set up query detection for N+1 problems
        static::setupQueryDetection();
    }

    /**
     * Register global eager loads for specific models
     */
    public static function registerGlobalEagerLoads(string $model, array $relations): void
    {
        $tableName = (new $model)->getTable();
        static::$globalEagerLoads[$tableName] = array_merge(
            static::$globalEagerLoads[$tableName] ?? [],
            $relations
        );
    }

    /**
     * Register conditional eager loads based on request context
     */
    public static function registerConditionalEagerLoads(string $model, array $conditions): void
    {
        $tableName = (new $model)->getTable();
        static::$conditionalEagerLoads[$tableName] = array_merge(
            static::$conditionalEagerLoads[$tableName] ?? [],
            $conditions
        );
    }

    /**
     * Get contextual eager loads based on current request
     */
    protected static function getContextualEagerLoads(Model $model): array
    {
        $tableName = $model->getTable();
        $eagerLoads = [];
        
        if (!isset(static::$conditionalEagerLoads[$tableName])) {
            return $eagerLoads;
        }
        
        $conditions = static::$conditionalEagerLoads[$tableName];
        $request = request();
        
        foreach ($conditions as $condition) {
            if (static::evaluateCondition($condition, $request, $model)) {
                $relations = $condition['relations'] ?? [];
                // Ensure relations is an array, not a Collection
                if ($relations instanceof \Illuminate\Support\Collection) {
                    $relations = $relations->toArray();
                }
                $eagerLoads = array_merge($eagerLoads, $relations);
            }
        }
        
        return array_unique($eagerLoads);
    }

    /**
     * Evaluate if a condition should trigger eager loading
     */
    protected static function evaluateCondition(array $condition, $request, Model $model): bool
    {
        // Check route-based conditions
        if (isset($condition['routes']) && $request) {
            $currentRoute = $request->route()?->getName();
            if (in_array($currentRoute, $condition['routes'])) {
                return true;
            }
        }
        
        // Check parameter-based conditions
        if (isset($condition['parameters']) && $request) {
            foreach ($condition['parameters'] as $param => $expectedValue) {
                if ($request->has($param)) {
                    $value = $request->get($param);
                    if ($expectedValue === true || $value === $expectedValue) {
                        return true;
                    }
                }
            }
        }
        
        // Check header-based conditions
        if (isset($condition['headers']) && $request) {
            foreach ($condition['headers'] as $header => $expectedValue) {
                if ($request->hasHeader($header)) {
                    $value = $request->header($header);
                    if ($expectedValue === true || $value === $expectedValue) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }

    /**
     * Set up N+1 query detection and automatic resolution
     */
    protected static function setupQueryDetection(): void
    {
        if (config('app.debug') && config('performance.detect_n_plus_one', true)) {
            static::enableQueryLogging();
        }
    }

    /**
     * Enable query logging for N+1 detection
     */
    protected static function enableQueryLogging(): void
    {
        \DB::listen(function ($query) {
            static::analyzeQuery($query);
        });
    }

    /**
     * Analyze queries for potential N+1 problems
     */
    protected static function analyzeQuery($query): void
    {
        $sql = $query->sql;
        $bindings = $query->bindings;
        $time = $query->time;
        
        // Detect potential N+1 patterns
        if (static::isSelectQuery($sql) && static::hasWhereInClause($sql)) {
            static::recordSuspiciousQuery($sql, $bindings, $time);
        }
        
        // Detect repeated similar queries
        $queryPattern = static::normalizeQuery($sql);
        static::trackQueryPattern($queryPattern, $time);
    }

    /**
     * Check if query is a SELECT statement
     */
    protected static function isSelectQuery(string $sql): bool
    {
        return str_starts_with(strtolower(trim($sql)), 'select');
    }

    /**
     * Check if query has WHERE IN clause (common in N+1 scenarios)
     */
    protected static function hasWhereInClause(string $sql): bool
    {
        return str_contains(strtolower($sql), 'where') && 
               (str_contains(strtolower($sql), ' in (') || str_contains(strtolower($sql), ' in('));
    }

    /**
     * Record potentially problematic queries
     */
    protected static function recordSuspiciousQuery(string $sql, array $bindings, float $time): void
    {
        if ($time > config('performance.slow_query_threshold', 100)) {
            Log::warning('Potential N+1 query detected', [
                'sql' => $sql,
                'time' => $time,
                'bindings_count' => count($bindings),
                'suggestion' => 'Consider using eager loading with ->with() method'
            ]);
        }
    }

    /**
     * Normalize query for pattern matching
     */
    protected static function normalizeQuery(string $sql): string
    {
        // Replace specific values with placeholders
        $normalized = preg_replace('/\b\d+\b/', '?', $sql);
        $normalized = preg_replace('/\'[^\']*\'/', '?', $normalized);
        $normalized = preg_replace('/\"[^\"]*\"/', '?', $normalized);
        
        return trim($normalized);
    }

    /**
     * Track query patterns to identify repeated queries
     */
    protected static function trackQueryPattern(string $pattern, float $time): void
    {
        static $queryPatterns = [];
        
        if (!isset($queryPatterns[$pattern])) {
            $queryPatterns[$pattern] = [
                'count' => 0,
                'total_time' => 0,
                'first_seen' => microtime(true)
            ];
        }
        
        $queryPatterns[$pattern]['count']++;
        $queryPatterns[$pattern]['total_time'] += $time;
        
        // Alert if pattern appears too frequently
        if ($queryPatterns[$pattern]['count'] > config('performance.repeated_query_threshold', 10)) {
            Log::warning('Repeated query pattern detected', [
                'pattern' => $pattern,
                'count' => $queryPatterns[$pattern]['count'],
                'total_time' => $queryPatterns[$pattern]['total_time'],
                'suggestion' => 'Consider caching or optimizing this query'
            ]);
            
            // Reset counter to avoid spam
            $queryPatterns[$pattern]['count'] = 0;
        }
    }

    /**
     * Intelligent eager loading based on access patterns
     */
    public function smartWith(array $relations = null): Builder
    {
        $builder = $this->newQuery();
        
        if ($relations === null) {
            // Auto-detect relations that should be eager loaded
            $relations = $this->detectRequiredRelations();
        }
        
        // Apply intelligent batching for large datasets
        if ($this->shouldUseBatching($relations)) {
            return $this->withBatching($builder, $relations);
        }
        
        return $builder->with($relations);
    }

    /**
     * Detect relations that should be eager loaded based on usage patterns
     */
    protected function detectRequiredRelations(): array
    {
        $modelClass = get_class($this);
        $relations = [];
        
        // Check for commonly accessed relations
        $accessLog = cache()->get("relation_access_log:{$modelClass}", []);
        
        foreach ($accessLog as $relation => $stats) {
            if ($stats['access_frequency'] > 0.7) { // 70% access rate
                $relations[] = $relation;
            }
        }
        
        return $relations;
    }

    /**
     * Determine if batching should be used for large datasets
     */
    protected function shouldUseBatching(array $relations): bool
    {
        // Use batching for relations that typically return many records
        $batchingRelations = config('performance.batching_relations', [
            'items', 'details', 'logs', 'activities', 'movements'
        ]);
        
        return !empty(array_intersect($relations, $batchingRelations));
    }

    /**
     * Apply batched eager loading for better memory management
     */
    protected function withBatching(Builder $builder, array $relations): Builder
    {
        $batchSize = config('performance.eager_loading_batch_size', 1000);
        
        foreach ($relations as $relation) {
            if ($this->isLargeRelation($relation)) {
                $builder->with([$relation => function ($query) use ($batchSize) {
                    $query->limit($batchSize);
                }]);
            } else {
                $builder->with($relation);
            }
        }
        
        return $builder;
    }

    /**
     * Check if a relation typically returns many records
     */
    protected function isLargeRelation(string $relation): bool
    {
        $largeRelations = config('performance.large_relations', [
            'items', 'details', 'activities', 'logs', 'movements', 'entries'
        ]);
        
        return in_array($relation, $largeRelations);
    }

    /**
     * Track relation access for optimization
     */
    public function trackRelationAccess(string $relation): void
    {
        $modelClass = get_class($this);
        $cacheKey = "relation_access_log:{$modelClass}";
        
        $accessLog = cache()->get($cacheKey, []);
        
        if (!isset($accessLog[$relation])) {
            $accessLog[$relation] = [
                'access_count' => 0,
                'total_requests' => 0,
                'access_frequency' => 0,
                'last_accessed' => null
            ];
        }
        
        $accessLog[$relation]['access_count']++;
        $accessLog[$relation]['total_requests']++;
        $accessLog[$relation]['access_frequency'] = $accessLog[$relation]['access_count'] / $accessLog[$relation]['total_requests'];
        $accessLog[$relation]['last_accessed'] = now();
        
        cache()->put($cacheKey, $accessLog, 3600); // Store for 1 hour
    }

    /**
     * Get performance recommendations for this model
     */
    public function getPerformanceRecommendations(): array
    {
        $modelClass = get_class($this);
        $recommendations = [];
        
        // Check for frequently accessed relations
        $accessLog = cache()->get("relation_access_log:{$modelClass}", []);
        
        foreach ($accessLog as $relation => $stats) {
            if ($stats['access_frequency'] > 0.5 && !in_array($relation, static::$globalEagerLoads[$this->getTable()] ?? [])) {
                $recommendations[] = [
                    'type' => 'eager_loading',
                    'message' => "Consider adding '{$relation}' to global eager loads",
                    'relation' => $relation,
                    'frequency' => $stats['access_frequency']
                ];
            }
        }
        
        return $recommendations;
    }

    /**
     * Apply automatic optimizations based on collected data
     */
    public static function applyAutomaticOptimizations(): array
    {
        $optimizations = [];
        
        foreach (static::$globalEagerLoads as $table => $relations) {
            $modelClass = static::getModelClassFromTable($table);
            if (!$modelClass) continue;
            
            $accessLog = cache()->get("relation_access_log:{$modelClass}", []);
            
            foreach ($accessLog as $relation => $stats) {
                if ($stats['access_frequency'] > 0.8 && !in_array($relation, $relations)) {
                    // Auto-add highly accessed relations
                    static::$globalEagerLoads[$table][] = $relation;
                    $optimizations[] = "Added '{$relation}' to global eager loads for {$modelClass}";
                }
                
                if ($stats['access_frequency'] < 0.1 && in_array($relation, $relations)) {
                    // Remove rarely accessed relations
                    static::$globalEagerLoads[$table] = array_diff($relations, [$relation]);
                    $optimizations[] = "Removed '{$relation}' from global eager loads for {$modelClass}";
                }
            }
        }
        
        return $optimizations;
    }

    /**
     * Get model class from table name
     */
    protected static function getModelClassFromTable(string $table): ?string
    {
        // This is a simplified mapping - in real implementation,
        // you might want a more sophisticated way to map tables to models
        $mapping = [
            'users' => \App\Models\User::class,
            'customers' => \App\Models\Customer::class,
            'products' => \App\Models\Product::class,
            'tax_documents' => \App\Models\TaxDocument::class,
            'payments' => \App\Models\Payment::class,
            'expenses' => \App\Models\Expense::class,
            'suppliers' => \App\Models\Supplier::class,
        ];
        
        return $mapping[$table] ?? null;
    }

    /**
     * Clear performance tracking data
     */
    public static function clearPerformanceData(): void
    {
        $pattern = 'relation_access_log:*';
        $keys = cache()->getRedis()->keys($pattern);
        
        if (!empty($keys)) {
            cache()->getRedis()->del($keys);
        }
    }
}