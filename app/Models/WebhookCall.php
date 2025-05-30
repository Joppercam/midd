<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookCall extends TenantAwareModel
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'webhook_id',
        'event',
        'payload',
        'response',
        'status_code',
        'attempts',
        'completed_at',
        'failed_at',
        'next_retry_at',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
        'response' => 'array',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'next_retry_at' => 'datetime',
    ];

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function isFailed(): bool
    {
        return $this->failed_at !== null;
    }

    public function isPending(): bool
    {
        return !$this->isCompleted() && !$this->isFailed();
    }

    public function shouldRetry(): bool
    {
        return $this->isPending() 
            && $this->attempts < ($this->webhook->max_retries ?? 3)
            && ($this->next_retry_at === null || $this->next_retry_at->isPast());
    }

    public function markAsCompleted(int $statusCode, array $response = []): void
    {
        $this->update([
            'status_code' => $statusCode,
            'response' => $response,
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(string $error, int $statusCode = null): void
    {
        $this->update([
            'status_code' => $statusCode,
            'error_message' => $error,
            'failed_at' => now(),
        ]);
    }

    public function incrementAttempts(): void
    {
        $this->increment('attempts');
        
        $retryDelay = $this->webhook->retry_delay ?? 60;
        $backoff = min($retryDelay * pow(2, $this->attempts - 1), 3600); // Exponential backoff, max 1 hour
        
        $this->update([
            'next_retry_at' => now()->addSeconds($backoff),
        ]);
    }
}