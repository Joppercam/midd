<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

trait InvalidatesCache
{
    /**
     * Invalidate all dashboard caches for a tenant
     */
    protected function invalidateDashboardCache(?string $tenantId = null): void
    {
        $tenantId = $tenantId ?? Auth::user()->tenant_id;
        $currentMonth = now()->format('Y-m');
        
        // Dashboard caches
        $cacheKeys = [
            "dashboard:admin:{$tenantId}:{$currentMonth}:kpis",
            "dashboard:admin:{$tenantId}:{$currentMonth}:charts",
            "dashboard:admin:{$tenantId}:{$currentMonth}:alerts",
            "dashboard:admin:{$tenantId}:{$currentMonth}:user_metrics",
        ];
        
        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
    }
    
    /**
     * Invalidate product statistics cache
     */
    protected function invalidateProductCache(?string $tenantId = null): void
    {
        $tenantId = $tenantId ?? Auth::user()->tenant_id;
        
        Cache::forget("products:stats:{$tenantId}");
        Cache::forget("categories:list:{$tenantId}");
        
        // Inventory report caches
        Cache::forget("inventory:report:{$tenantId}:low_stock");
        Cache::forget("inventory:report:{$tenantId}:out_of_stock");
        Cache::forget("inventory:report:{$tenantId}:reorder");
        Cache::forget("inventory:report:{$tenantId}:valuation");
    }
    
    /**
     * Invalidate sales report cache (pattern based)
     */
    protected function invalidateSalesReportCache(?string $tenantId = null): void
    {
        $tenantId = $tenantId ?? Auth::user()->tenant_id;
        
        // Since sales reports use MD5 hashes, we need to clear by pattern
        // In production with Redis, we could use pattern deletion
        // For now, we'll track known keys or use tags when available
        
        // This is a limitation of database cache driver
        // With Redis, we could do: Cache::tags(['reports', 'sales'])->flush();
    }
    
    /**
     * Invalidate all caches for a tenant
     */
    protected function invalidateAllTenantCache(?string $tenantId = null): void
    {
        $tenantId = $tenantId ?? Auth::user()->tenant_id;
        
        $this->invalidateDashboardCache($tenantId);
        $this->invalidateProductCache($tenantId);
        $this->invalidateSalesReportCache($tenantId);
    }
    
    /**
     * Get cache tags for the current tenant
     * Note: Tags are only supported by Redis and Memcached drivers
     */
    protected function getCacheTags(): array
    {
        return [
            'tenant:' . Auth::user()->tenant_id,
        ];
    }
}