<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsEvent extends TenantAwareModel
{
    use HasFactory;

    protected $fillable = [
        'event_type',
        'event_data',
        'user_id',
    ];

    protected $casts = [
        'event_data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function track(string $eventType, array $data = [], ?string $userId = null): self
    {
        return static::create([
            'event_type' => $eventType,
            'event_data' => $data,
            'user_id' => $userId ?? auth()->id(),
        ]);
    }
}