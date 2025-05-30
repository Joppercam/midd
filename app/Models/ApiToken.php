<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use App\Traits\BelongsToTenant;

class ApiToken extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'token',
        'abilities',
        'last_used_at',
        'expires_at',
        'is_active',
        'ip_restriction',
        'rate_limit',
        'metadata'
    ];

    protected $casts = [
        'abilities' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'metadata' => 'array'
    ];

    protected $hidden = [
        'token'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function apiLogs(): HasMany
    {
        return $this->hasMany(ApiLog::class);
    }

    public static function generate(array $attributes): self
    {
        $token = self::generateUniqueToken();
        
        return self::create(array_merge($attributes, [
            'token' => hash('sha256', $token)
        ]));
    }

    protected static function generateUniqueToken(): string
    {
        do {
            $token = Str::random(40);
        } while (self::where('token', hash('sha256', $token))->exists());

        return $token;
    }

    public function can(string $ability): bool
    {
        if ($this->abilities === null || in_array('*', $this->abilities)) {
            return true;
        }

        return in_array($ability, $this->abilities);
    }

    public function cant(string $ability): bool
    {
        return !$this->can($ability);
    }

    public function recordUsage(string $ipAddress = null): void
    {
        $this->update([
            'last_used_at' => now()
        ]);
    }

    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function isIpAllowed(string $ip): bool
    {
        if (!$this->ip_restriction) {
            return true;
        }

        $allowedIps = explode(',', $this->ip_restriction);
        
        foreach ($allowedIps as $allowedIp) {
            $allowedIp = trim($allowedIp);
            
            // Check for CIDR notation
            if (strpos($allowedIp, '/') !== false) {
                if ($this->ipInRange($ip, $allowedIp)) {
                    return true;
                }
            } elseif ($ip === $allowedIp) {
                return true;
            }
        }

        return false;
    }

    protected function ipInRange(string $ip, string $range): bool
    {
        list($subnet, $bits) = explode('/', $range);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnet &= $mask;
        
        return ($ip & $mask) == $subnet;
    }

    public function revoke(): void
    {
        $this->update(['is_active' => false]);
    }

    public function getStatistics(int $days = 30): array
    {
        $since = now()->subDays($days);
        
        $logs = $this->apiLogs()
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('COUNT(*) as total_requests')
            ->selectRaw('AVG(response_time) as avg_response_time')
            ->selectRaw('COUNT(CASE WHEN status_code >= 200 AND status_code < 300 THEN 1 END) as successful_requests')
            ->selectRaw('COUNT(CASE WHEN status_code >= 400 THEN 1 END) as failed_requests')
            ->groupBy('date')
            ->get();

        return [
            'total_requests' => $logs->sum('total_requests'),
            'successful_requests' => $logs->sum('successful_requests'),
            'failed_requests' => $logs->sum('failed_requests'),
            'average_response_time' => round($logs->avg('avg_response_time'), 2),
            'daily_usage' => $logs->toArray()
        ];
    }

    public function getMostUsedEndpoints(int $limit = 10): array
    {
        return $this->apiLogs()
            ->selectRaw('endpoint, method, COUNT(*) as count')
            ->groupBy('endpoint', 'method')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}