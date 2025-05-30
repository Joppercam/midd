<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantModule extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'module_id',
        'is_enabled',
        'enabled_at',
        'disabled_at',
        'expires_at',
        'settings',
        'usage_stats',
        'custom_price',
        'billing_cycle',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'enabled_at' => 'datetime',
        'disabled_at' => 'datetime',
        'expires_at' => 'datetime',
        'settings' => 'array',
        'usage_stats' => 'array',
        'custom_price' => 'decimal:2',
    ];

    /**
     * Tenant al que pertenece el módulo
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Módulo del sistema
     */
    public function systemModule(): BelongsTo
    {
        return $this->belongsTo(SystemModule::class, 'module_id');
    }

    /**
     * Verificar si el módulo está activo
     */
    public function isActive(): bool
    {
        if (!$this->is_enabled) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Verificar si el módulo está en período de prueba
     */
    public function isOnTrial(): bool
    {
        return $this->expires_at && $this->expires_at->isFuture();
    }

    /**
     * Obtener días restantes de prueba
     */
    public function getTrialDaysRemaining(): ?int
    {
        if (!$this->isOnTrial()) {
            return null;
        }

        return now()->diffInDays($this->expires_at);
    }

    /**
     * Obtener configuración específica
     */
    public function getSetting(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Establecer configuración específica
     */
    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->update(['settings' => $settings]);
    }

    /**
     * Obtener estadística de uso
     */
    public function getUsageStat(string $key, $default = 0)
    {
        return data_get($this->usage_stats, $key, $default);
    }

    /**
     * Incrementar estadística de uso
     */
    public function incrementUsage(string $key, int $amount = 1): void
    {
        $stats = $this->usage_stats ?? [];
        $currentValue = data_get($stats, $key, 0);
        data_set($stats, $key, $currentValue + $amount);
        $this->update(['usage_stats' => $stats]);
    }

    /**
     * Obtener el precio efectivo (personalizado o base)
     */
    public function getEffectivePrice(): float
    {
        return $this->custom_price ?? $this->systemModule->base_price;
    }

    /**
     * Scope para módulos activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_enabled', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope para módulos expirados
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Scope para módulos en prueba
     */
    public function scopeOnTrial($query)
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '>', now());
    }

    /**
     * Obtener el estado del módulo como string
     */
    public function getStatusAttribute(): string
    {
        if (!$this->is_enabled) {
            return 'disabled';
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return 'expired';
        }

        if ($this->expires_at && $this->expires_at->isFuture()) {
            return 'trial';
        }

        return 'active';
    }

    /**
     * Obtener el color del estado
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'active' => 'green',
            'trial' => 'yellow',
            'expired' => 'red',
            'disabled' => 'gray',
            default => 'gray'
        };
    }

    /**
     * Obtener el label del estado
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'active' => 'Activo',
            'trial' => 'Prueba',
            'expired' => 'Expirado',
            'disabled' => 'Deshabilitado',
            default => 'Desconocido'
        };
    }
}