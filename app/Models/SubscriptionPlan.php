<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'code',
        'description',
        'monthly_price',
        'annual_price',
        'price_monthly',
        'price_yearly',
        'included_modules',
        'limits',
        'features',
        'is_active',
        'is_popular',
        'trial_days',
        'sort_order',
    ];

    protected $casts = [
        'monthly_price' => 'decimal:2',
        'annual_price' => 'decimal:2',
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
        'included_modules' => 'array',
        'limits' => 'array',
        'features' => 'array',
        'is_active' => 'boolean',
        'is_popular' => 'boolean',
    ];

    /**
     * Suscripciones que usan este plan
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class, 'plan_id');
    }

    /**
     * Suscripciones activas
     */
    public function activeSubscriptions()
    {
        return $this->subscriptions()->where('status', 'active');
    }

    /**
     * Verificar si incluye un módulo
     */
    public function includesModule(string $moduleCode): bool
    {
        return in_array($moduleCode, $this->included_modules ?? []);
    }

    /**
     * Obtener módulos incluidos
     */
    public function getIncludedModulesModels()
    {
        if (empty($this->included_modules)) {
            return collect();
        }

        return SystemModule::whereIn('code', $this->included_modules)->get();
    }

    /**
     * Obtener límite específico
     */
    public function getLimit(string $key, $default = null)
    {
        return data_get($this->limits, $key, $default);
    }

    /**
     * Verificar si tiene una característica
     */
    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }

    /**
     * Obtener el descuento anual
     */
    public function getAnnualDiscountAttribute(): float
    {
        if ($this->monthly_price == 0 || $this->annual_price == 0) {
            return 0;
        }

        $monthlyTotal = $this->monthly_price * 12;
        $discount = ($monthlyTotal - $this->annual_price) / $monthlyTotal * 100;

        return round($discount, 1);
    }

    /**
     * Obtener el precio formateado
     */
    public function getFormattedMonthlyPriceAttribute(): string
    {
        return '$' . number_format($this->monthly_price, 0, ',', '.');
    }

    /**
     * Obtener el precio anual formateado
     */
    public function getFormattedAnnualPriceAttribute(): string
    {
        return '$' . number_format($this->annual_price, 0, ',', '.');
    }

    /**
     * Verificar si es un plan gratuito
     */
    public function isFree(): bool
    {
        return $this->monthly_price == 0;
    }

    /**
     * Verificar si permite módulos adicionales ilimitados
     */
    public function hasUnlimitedModules(): bool
    {
        return $this->getLimit('max_additional_modules') === -1;
    }

    /**
     * Obtener el número de tenants usando este plan
     */
    public function getTenantsCountAttribute(): int
    {
        return $this->activeSubscriptions()->count();
    }

    /**
     * Calcular el ingreso mensual de este plan
     */
    public function getMonthlyRevenueAttribute(): float
    {
        $activeCount = $this->activeSubscriptions()
            ->where('billing_cycle', 'monthly')
            ->count();

        $annualCount = $this->activeSubscriptions()
            ->where('billing_cycle', 'annual')
            ->count();

        return ($activeCount * $this->monthly_price) + 
               ($annualCount * ($this->annual_price / 12));
    }

    /**
     * Scope para planes activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para planes populares
     */
    public function scopePopular($query)
    {
        return $query->where('is_popular', true);
    }

    /**
     * Scope ordenado por sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('monthly_price');
    }

    /**
     * Obtener estadísticas del plan
     */
    public function getStats(): array
    {
        $subscriptions = $this->subscriptions();

        return [
            'total_subscriptions' => $subscriptions->count(),
            'active_subscriptions' => $subscriptions->where('status', 'active')->count(),
            'trial_subscriptions' => $subscriptions->where('status', 'trial')->count(),
            'monthly_revenue' => $this->monthly_revenue,
            'conversion_rate' => $this->getConversionRate(),
        ];
    }

    /**
     * Calcular tasa de conversión de trial a activo
     */
    private function getConversionRate(): float
    {
        $totalTrials = $this->subscriptions()
            ->whereIn('status', ['active', 'cancelled'])
            ->whereNotNull('trial_ends_at')
            ->count();

        if ($totalTrials == 0) {
            return 0;
        }

        $convertedTrials = $this->subscriptions()
            ->where('status', 'active')
            ->whereNotNull('trial_ends_at')
            ->where('current_period_start', '>', 'trial_ends_at')
            ->count();

        return round(($convertedTrials / $totalTrials) * 100, 1);
    }
}