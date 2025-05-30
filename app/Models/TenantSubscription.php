<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'status',
        'started_at',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'cancelled_at',
        'custom_modules',
        'custom_limits',
        'monthly_amount',
        'billing_cycle',
        'payment_method',
        'billing_info',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'cancelled_at' => 'datetime',
        'custom_modules' => 'array',
        'custom_limits' => 'array',
        'monthly_amount' => 'decimal:2',
        'billing_info' => 'array',
    ];

    /**
     * Estados posibles
     */
    const STATUS_TRIAL = 'trial';
    const STATUS_ACTIVE = 'active';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Ciclos de facturación
     */
    const BILLING_MONTHLY = 'monthly';
    const BILLING_ANNUAL = 'annual';

    /**
     * Tenant de la suscripción
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Plan de la suscripción
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    /**
     * Verificar si está en período de prueba
     */
    public function isOnTrial(): bool
    {
        return $this->status === self::STATUS_TRIAL && 
               $this->trial_ends_at && 
               $this->trial_ends_at->isFuture();
    }

    /**
     * Verificar si está activa
     */
    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_TRIAL, self::STATUS_ACTIVE]) &&
               $this->current_period_end->isFuture();
    }

    /**
     * Verificar si está cancelada
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Verificar si está suspendida
     */
    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    /**
     * Obtener días restantes de prueba
     */
    public function getTrialDaysRemaining(): ?int
    {
        if (!$this->isOnTrial()) {
            return null;
        }

        return now()->diffInDays($this->trial_ends_at);
    }

    /**
     * Obtener días hasta el próximo pago
     */
    public function getDaysUntilRenewal(): int
    {
        return now()->diffInDays($this->current_period_end);
    }

    /**
     * Obtener límite específico (personalizado o del plan)
     */
    public function getLimit(string $key, $default = null)
    {
        // Primero verificar límites personalizados
        $customLimit = data_get($this->custom_limits, $key);
        if ($customLimit !== null) {
            return $customLimit;
        }

        // Luego verificar límites del plan
        return $this->plan->getLimit($key, $default);
    }

    /**
     * Verificar si incluye un módulo (plan + personalizados)
     */
    public function includesModule(string $moduleCode): bool
    {
        // Verificar en módulos del plan
        if ($this->plan->includesModule($moduleCode)) {
            return true;
        }

        // Verificar en módulos personalizados
        $customModules = $this->custom_modules ?? [];
        return in_array($moduleCode, $customModules);
    }

    /**
     * Obtener todos los módulos incluidos
     */
    public function getAllIncludedModules(): array
    {
        $planModules = $this->plan->included_modules ?? [];
        $customModules = $this->custom_modules ?? [];

        return array_unique(array_merge($planModules, $customModules));
    }

    /**
     * Activar suscripción después del trial
     */
    public function activate(): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'trial_ends_at' => null,
        ]);
    }

    /**
     * Suspender suscripción
     */
    public function suspend(): void
    {
        $this->update(['status' => self::STATUS_SUSPENDED]);
    }

    /**
     * Cancelar suscripción
     */
    public function cancel(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Renovar período
     */
    public function renew(): void
    {
        $nextPeriodStart = $this->current_period_end;
        
        $nextPeriodEnd = $this->billing_cycle === self::BILLING_ANNUAL
            ? $nextPeriodStart->addYear()
            : $nextPeriodStart->addMonth();

        $this->update([
            'current_period_start' => $nextPeriodStart,
            'current_period_end' => $nextPeriodEnd,
        ]);
    }

    /**
     * Cambiar plan
     */
    public function changePlan(SubscriptionPlan $newPlan): void
    {
        $this->update([
            'plan_id' => $newPlan->id,
            'monthly_amount' => $this->billing_cycle === self::BILLING_ANNUAL
                ? $newPlan->annual_price / 12
                : $newPlan->monthly_price,
        ]);
    }

    /**
     * Obtener el monto a facturar
     */
    public function getBillingAmount(): float
    {
        return $this->billing_cycle === self::BILLING_ANNUAL
            ? $this->plan->annual_price
            : $this->monthly_amount;
    }

    /**
     * Scope para suscripciones activas
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_TRIAL, self::STATUS_ACTIVE])
            ->where('current_period_end', '>', now());
    }

    /**
     * Scope para suscripciones en trial
     */
    public function scopeOnTrial($query)
    {
        return $query->where('status', self::STATUS_TRIAL)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', now());
    }

    /**
     * Scope para suscripciones que expiran pronto
     */
    public function scopeExpiringSoon($query, int $days = 7)
    {
        return $query->active()
            ->where('current_period_end', '<=', now()->addDays($days));
    }

    /**
     * Obtener el estado legible
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            self::STATUS_TRIAL => 'Período de Prueba',
            self::STATUS_ACTIVE => 'Activa',
            self::STATUS_SUSPENDED => 'Suspendida',
            self::STATUS_CANCELLED => 'Cancelada',
            default => 'Desconocido'
        };
    }

    /**
     * Obtener el color del estado
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_TRIAL => 'yellow',
            self::STATUS_ACTIVE => 'green',
            self::STATUS_SUSPENDED => 'orange',
            self::STATUS_CANCELLED => 'red',
            default => 'gray'
        };
    }
}