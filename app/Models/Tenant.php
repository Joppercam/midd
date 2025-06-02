<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Traits\Auditable;

class Tenant extends Model
{
    use HasUuids, Auditable;

    protected $fillable = [
        'name',
        'legal_name',
        'trade_name',
        'industry',
        'tax_id',
        'rut',
        'website',
        'email',
        'phone',
        'mobile',
        'domain',
        'settings',
        'subscription_plan',
        'subscription_status',
        'trial_ends_at',
        'business_activity',
        'tax_regime',
        'economic_activity',
        'economic_activity_code',
        'is_holding',
        'uses_branch_offices',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'commune',
        'branch_code',
        'logo_path',
        'primary_color',
        'secondary_color',
        'invoice_settings',
        'email_settings',
        'currency',
        'timezone',
        'date_format',
        'time_format',
        'fiscal_year_start_month',
        'plan',
        'max_users',
        'max_documents_per_month',
        'max_products',
        'max_customers',
        'api_access',
        'multi_branch',
        'is_active',
        'suspended_at',
        'suspension_reason',
        'last_activity_at',
        'features',
        'sii_environment',
        'sii_certification_completed',
        'sii_certification_date',
        'certificate_password',
        'certificate_uploaded_at',
        'authorized_sender_rut',
        'sii_resolution_date',
        'sii_resolution_number',
        'folio_ranges',
        'storage_used',
        'max_storage',
        'storage_last_calculated_at',
        'api_rate_limit',
        'webhook_url',
        'webhook_secret',
        'enable_webhooks',
        'api_documentation_public',
    ];

    protected $casts = [
        'settings' => 'array',
        'invoice_settings' => 'array',
        'email_settings' => 'array',
        'features' => 'array',
        'folio_ranges' => 'array',
        'is_holding' => 'boolean',
        'uses_branch_offices' => 'boolean',
        'api_access' => 'boolean',
        'multi_branch' => 'boolean',
        'is_active' => 'boolean',
        'sii_certification_completed' => 'boolean',
        'trial_ends_at' => 'datetime',
        'certificate_uploaded_at' => 'datetime',
        'suspended_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'sii_resolution_date' => 'date',
        'sii_certification_date' => 'date',
        'storage_used' => 'integer',
        'max_storage' => 'integer',
        'storage_last_calculated_at' => 'datetime',
        'api_rate_limit' => 'integer',
        'enable_webhooks' => 'boolean',
        'api_documentation_public' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = [
        'certificate_password',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function taxDocuments(): HasMany
    {
        return $this->hasMany(TaxDocument::class);
    }

    /**
     * Relación con módulos activos del tenant
     */
    public function modules(): HasMany
    {
        return $this->hasMany(TenantModule::class);
    }

    /**
     * Relación con módulos activos
     */
    public function activeModules()
    {
        return $this->modules()->active()->with('systemModule');
    }

    /**
     * Relación con la suscripción actual
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(TenantSubscription::class)->latest();
    }

    /**
     * Relación con la suscripción activa
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(TenantSubscription::class)
            ->where('status', 'active')
            ->orWhere(function($query) {
                $query->where('status', 'trial')
                    ->where('trial_ends_at', '>', now());
            });
    }

    public function isOnTrial(): bool
    {
        return $this->subscription_status === 'trial' && 
               $this->trial_ends_at && 
               $this->trial_ends_at->isFuture();
    }

    public function isActive(): bool
    {
        return $this->is_active && in_array($this->subscription_status, ['active', 'trial']);
    }

    public function isSuspended(): bool
    {
        return !$this->is_active || $this->suspended_at !== null;
    }

    public function canCreateUsers(): bool
    {
        return $this->users()->count() < $this->max_users;
    }

    public function canCreateDocuments(): bool
    {
        $currentMonth = now()->format('Y-m');
        $documentsThisMonth = $this->taxDocuments()
            ->whereRaw("strftime('%Y-%m', created_at) = ?", [$currentMonth])
            ->count();
        
        return $documentsThisMonth < $this->max_documents_per_month;
    }

    public function canCreateProducts(): bool
    {
        return $this->products()->count() < $this->max_products;
    }

    public function canCreateCustomers(): bool
    {
        return $this->customers()->count() < $this->max_customers;
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo_path) {
            return null;
        }

        return asset('storage/' . $this->logo_path);
    }

    public function updateLastActivity(): void
    {
        $this->update(['last_activity_at' => now()]);
    }

    public function hasFeature(string $feature): bool
    {
        if (!$this->features) {
            return false;
        }

        return in_array($feature, $this->features);
    }

    public function getPlanLimitsAttribute(): array
    {
        return [
            'users' => $this->max_users,
            'documents_per_month' => $this->max_documents_per_month,
            'products' => $this->max_products,
            'customers' => $this->max_customers,
            'api_access' => $this->api_access,
            'multi_branch' => $this->multi_branch,
        ];
    }

    public function getUsageStatsAttribute(): array
    {
        $currentMonth = now()->format('Y-m');
        
        return [
            'users' => $this->users()->count(),
            'documents_this_month' => $this->taxDocuments()
                ->whereRaw("strftime('%Y-%m', created_at) = ?", [$currentMonth])
                ->count(),
            'products' => $this->products()->count(),
            'customers' => $this->customers()->count(),
        ];
    }

    /**
     * Relationship with usage statistics
     */
    public function usageStatistics(): HasMany
    {
        return $this->hasMany(TenantUsageStatistic::class);
    }

    /**
     * Suspend the tenant
     */
    public function suspend(string $reason = null): void
    {
        $this->update([
            'is_active' => false,
            'suspended_at' => now(),
            'suspension_reason' => $reason,
            'subscription_status' => 'suspended'
        ]);
    }

    /**
     * Reactivate the tenant
     */
    public function reactivate(): void
    {
        $this->update([
            'is_active' => true,
            'suspended_at' => null,
            'suspension_reason' => null,
            'subscription_status' => 'active'
        ]);
    }

    /**
     * Set a specific setting value
     * Supports dot notation for nested settings
     */
    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->update(['settings' => $settings]);
    }

    /**
     * Get a specific setting value
     * Supports dot notation for nested settings
     */
    public function getSetting(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Clear settings matching a pattern
     * Supports dot notation and wildcards
     */
    public function clearSettings(string $pattern): void
    {
        $settings = $this->settings ?? [];
        
        // If pattern contains wildcard or is a parent key
        if (str_contains($pattern, '*') || !str_contains($pattern, '.')) {
            // Get all keys matching the pattern
            $keys = array_keys(data_get($settings, $pattern, []));
            
            // Remove each matching key
            foreach ($keys as $key) {
                data_forget($settings, $pattern . '.' . $key);
            }
            
            // If pattern doesn't contain wildcard, also try to forget the exact key
            if (!str_contains($pattern, '*')) {
                data_forget($settings, $pattern);
            }
        } else {
            // Remove exact key
            data_forget($settings, $pattern);
        }
        
        $this->update(['settings' => $settings]);
    }

    /**
     * Check if a setting exists
     */
    public function hasSetting(string $key): bool
    {
        return data_get($this->settings, $key) !== null;
    }

    /**
     * Merge multiple settings at once
     */
    public function mergeSettings(array $settings): void
    {
        $currentSettings = $this->settings ?? [];
        $mergedSettings = array_merge_recursive($currentSettings, $settings);
        $this->update(['settings' => $mergedSettings]);
    }

    /**
     * Storage usage tracking relation
     */
    public function storageUsage(): HasMany
    {
        return $this->hasMany(TenantStorageUsage::class);
    }

    /**
     * Active storage usage relation
     */
    public function activeStorageUsage(): HasMany
    {
        return $this->storageUsage()->active();
    }

    /**
     * Increment storage used
     */
    public function incrementStorageUsed(int $bytes): void
    {
        $this->increment('storage_used', $bytes);
        $this->update(['storage_last_calculated_at' => now()]);
    }

    /**
     * Decrement storage used
     */
    public function decrementStorageUsed(int $bytes): void
    {
        $this->decrement('storage_used', $bytes);
        $this->update(['storage_last_calculated_at' => now()]);
    }

    /**
     * Check if tenant can upload a file of given size
     */
    public function canUploadFile(int $fileSize): bool
    {
        return ($this->storage_used + $fileSize) <= $this->max_storage;
    }

    /**
     * Get remaining storage space
     */
    public function getRemainingStorageAttribute(): int
    {
        return max(0, $this->max_storage - $this->storage_used);
    }

    /**
     * Get storage usage percentage
     */
    public function getStorageUsagePercentageAttribute(): float
    {
        if ($this->max_storage === 0) {
            return 0;
        }
        
        return round(($this->storage_used / $this->max_storage) * 100, 2);
    }

    /**
     * Get formatted storage used
     */
    public function getFormattedStorageUsedAttribute(): string
    {
        return $this->formatBytes($this->storage_used);
    }

    /**
     * Get formatted max storage
     */
    public function getFormattedMaxStorageAttribute(): string
    {
        return $this->formatBytes($this->max_storage);
    }

    /**
     * Recalculate storage usage from actual files
     */
    public function recalculateStorageUsage(): int
    {
        $totalUsage = $this->activeStorageUsage()->sum('file_size');
        
        $this->update([
            'storage_used' => $totalUsage,
            'storage_last_calculated_at' => now()
        ]);
        
        return $totalUsage;
    }

    /**
     * Get storage usage by category
     */
    public function getStorageUsageByCategory(): array
    {
        $usage = $this->activeStorageUsage()
            ->selectRaw('
                CASE 
                    WHEN mime_type LIKE "image/%" THEN "images"
                    WHEN mime_type LIKE "video/%" THEN "videos"
                    WHEN mime_type LIKE "audio/%" THEN "audio"
                    WHEN mime_type IN ("application/pdf", "application/msword", "application/vnd.openxmlformats-officedocument.wordprocessingml.document") THEN "documents"
                    ELSE "other"
                END as category,
                SUM(file_size) as total_size,
                COUNT(*) as file_count
            ')
            ->groupBy('category')
            ->get();

        return $usage->mapWithKeys(function ($item) {
            return [$item->category => [
                'size' => $item->total_size,
                'count' => $item->file_count,
                'formatted_size' => $this->formatBytes($item->total_size)
            ]];
        })->toArray();
    }

    /**
     * Check if storage cleanup is needed
     */
    public function needsStorageCleanup(): bool
    {
        return $this->storage_usage_percentage > 90;
    }

    /**
     * Format bytes to human readable format
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}