<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Webhook extends TenantAwareModel
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'url',
        'events',
        'headers',
        'secret',
        'active',
        'max_retries',
        'retry_delay',
        'timeout',
        'last_called_at',
        'last_status',
        'last_error',
        'failure_count',
    ];

    protected $casts = [
        'events' => 'array',
        'headers' => 'array',
        'active' => 'boolean',
        'last_called_at' => 'datetime',
    ];

    public function calls(): HasMany
    {
        return $this->hasMany(WebhookCall::class);
    }

    public function isActive(): bool
    {
        return $this->active && $this->failure_count < 5;
    }

    public function subscribesToEvent(string $event): bool
    {
        return in_array($event, $this->events ?? []) || in_array('*', $this->events ?? []);
    }

    public function incrementFailure(): void
    {
        $this->increment('failure_count');
        
        if ($this->failure_count >= 5) {
            $this->update(['active' => false]);
        }
    }

    public function resetFailures(): void
    {
        $this->update(['failure_count' => 0]);
    }

    public function generateSignature(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->secret);
    }
}