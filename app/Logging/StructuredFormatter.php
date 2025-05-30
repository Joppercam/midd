<?php

namespace App\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;

class StructuredFormatter extends JsonFormatter
{
    public function format(LogRecord $record): string
    {
        $data = [
            'timestamp' => $record->datetime->format('Y-m-d\TH:i:s.uP'),
            'level' => $record->level->getName(),
            'level_code' => $record->level->value,
            'channel' => $record->channel,
            'message' => $record->message,
            'context' => $this->normalizeContext($record->context),
            'extra' => $this->normalizeExtra($record->extra),
            'environment' => app()->environment(),
            'hostname' => gethostname(),
            'process_id' => getmypid(),
            'request_id' => request()->header('X-Request-ID') ?? $this->generateRequestId(),
        ];

        // Agregar información del usuario si está autenticado
        if (auth()->check()) {
            $data['user'] = [
                'id' => auth()->id(),
                'email' => auth()->user()->email,
                'tenant_id' => auth()->user()->tenant_id,
            ];
        }

        // Agregar información de la petición si existe
        if (app()->runningInConsole()) {
            $data['cli'] = [
                'command' => implode(' ', $_SERVER['argv'] ?? []),
                'user' => get_current_user(),
            ];
        } else {
            $data['request'] = [
                'method' => request()->method(),
                'url' => request()->fullUrl(),
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'referer' => request()->header('referer'),
            ];
        }

        // Añadir métricas de rendimiento si están disponibles
        if (defined('LARAVEL_START')) {
            $data['performance'] = [
                'duration_ms' => round((microtime(true) - LARAVEL_START) * 1000, 2),
                'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ];
        }

        return $this->toJson($data) . "\n";
    }

    protected function normalizeContext(array $context): array
    {
        // Eliminar información sensible
        $sensitiveKeys = ['password', 'token', 'secret', 'api_key', 'credit_card'];
        
        foreach ($context as $key => $value) {
            foreach ($sensitiveKeys as $sensitive) {
                if (stripos($key, $sensitive) !== false) {
                    $context[$key] = '***REDACTED***';
                }
            }
        }

        return $context;
    }

    protected function normalizeExtra(array $extra): array
    {
        // Procesar información extra del log
        return array_map(function ($value) {
            if (is_object($value) && method_exists($value, '__toString')) {
                return (string) $value;
            }
            return $value;
        }, $extra);
    }

    protected function generateRequestId(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}