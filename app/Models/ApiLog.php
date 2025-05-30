<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToTenant;

class ApiLog extends Model
{
    use HasFactory, BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'api_token_id',
        'tenant_id',
        'method',
        'endpoint',
        'status_code',
        'ip_address',
        'user_agent',
        'request_headers',
        'request_body',
        'response_body',
        'response_time',
        'error_message',
        'created_at'
    ];

    protected $casts = [
        'request_headers' => 'array',
        'request_body' => 'array',
        'response_body' => 'array',
        'created_at' => 'datetime'
    ];

    public function apiToken(): BelongsTo
    {
        return $this->belongsTo(ApiToken::class);
    }

    public static function record(array $data): self
    {
        $data['created_at'] = now();
        
        // Sanitize sensitive data
        if (isset($data['request_headers'])) {
            $data['request_headers'] = self::sanitizeHeaders($data['request_headers']);
        }
        
        if (isset($data['request_body'])) {
            $data['request_body'] = self::sanitizeBody($data['request_body']);
        }
        
        return self::create($data);
    }

    protected static function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = ['authorization', 'x-api-key', 'cookie'];
        
        foreach ($sensitiveHeaders as $header) {
            if (isset($headers[$header])) {
                $headers[$header] = '***REDACTED***';
            }
        }
        
        return $headers;
    }

    protected static function sanitizeBody($body): array
    {
        if (!is_array($body)) {
            return ['raw' => substr($body, 0, 1000)];
        }
        
        $sensitiveFields = ['password', 'token', 'secret', 'api_key', 'credit_card'];
        
        array_walk_recursive($body, function (&$value, $key) use ($sensitiveFields) {
            foreach ($sensitiveFields as $field) {
                if (stripos($key, $field) !== false) {
                    $value = '***REDACTED***';
                }
            }
        });
        
        return $body;
    }

    public function isSuccessful(): bool
    {
        return $this->status_code >= 200 && $this->status_code < 300;
    }

    public function isError(): bool
    {
        return $this->status_code >= 400;
    }

    public function getEndpointWithoutParams(): string
    {
        return preg_replace('/\/\d+/', '/{id}', $this->endpoint);
    }

    public static function getStatisticsByEndpoint($tenantId, $days = 30): array
    {
        return self::where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('endpoint, method')
            ->selectRaw('COUNT(*) as total_requests')
            ->selectRaw('AVG(response_time) as avg_response_time')
            ->selectRaw('COUNT(CASE WHEN status_code >= 200 AND status_code < 300 THEN 1 END) as successful_requests')
            ->selectRaw('COUNT(CASE WHEN status_code >= 400 THEN 1 END) as failed_requests')
            ->groupBy('endpoint', 'method')
            ->orderByDesc('total_requests')
            ->get()
            ->toArray();
    }

    public static function getErrorSummary($tenantId, $days = 7): array
    {
        return self::where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subDays($days))
            ->where('status_code', '>=', 400)
            ->selectRaw('status_code, COUNT(*) as count')
            ->selectRaw('MAX(error_message) as sample_error')
            ->groupBy('status_code')
            ->orderByDesc('count')
            ->get()
            ->toArray();
    }
}