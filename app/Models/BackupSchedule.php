<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class BackupSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'frequency',
        'time',
        'day_of_week',
        'day_of_month',
        'timezone',
        'included_tables',
        'excluded_tables',
        'include_files',
        'disk',
        'retention_days',
        'is_active',
        'last_run_at',
        'next_run_at',
        'run_count',
        'success_count',
        'failure_count',
        'notification_settings'
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'day_of_month' => 'integer',
        'included_tables' => 'array',
        'excluded_tables' => 'array',
        'include_files' => 'boolean',
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
        'notification_settings' => 'array'
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function calculateNextRunTime(): Carbon
    {
        $now = now($this->timezone);
        $time = Carbon::parse($this->time, $this->timezone);
        
        switch ($this->frequency) {
            case 'daily':
                $next = $now->copy()->setTimeFrom($time);
                if ($next->isPast()) {
                    $next->addDay();
                }
                break;
                
            case 'weekly':
                $next = $now->copy()->next($this->day_of_week)->setTimeFrom($time);
                if ($next->isPast() || ($next->dayOfWeek === $now->dayOfWeek && $now->gt($time))) {
                    $next->addWeek();
                }
                break;
                
            case 'monthly':
                $next = $now->copy()->day($this->day_of_month)->setTimeFrom($time);
                if ($next->isPast()) {
                    $next->addMonth();
                }
                // Handle months with fewer days
                while ($next->day !== $this->day_of_month && $this->day_of_month <= 28) {
                    $next->addMonth()->day($this->day_of_month);
                }
                break;
                
            default:
                throw new \Exception("Frecuencia no válida: {$this->frequency}");
        }
        
        return $next;
    }

    public function updateNextRunTime(): void
    {
        $this->update([
            'next_run_at' => $this->calculateNextRunTime()
        ]);
    }

    public function shouldRun(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if (!$this->next_run_at) {
            $this->updateNextRunTime();
            return false;
        }

        return $this->next_run_at->isPast();
    }

    public function recordRun(bool $success = true): void
    {
        $this->increment('run_count');
        
        if ($success) {
            $this->increment('success_count');
        } else {
            $this->increment('failure_count');
        }
        
        $this->update([
            'last_run_at' => now(),
            'next_run_at' => $this->calculateNextRunTime()
        ]);
    }

    public function createBackup(): Backup
    {
        $name = sprintf(
            '%s - %s',
            $this->name,
            now()->format('Y-m-d H:i:s')
        );

        return Backup::create([
            'tenant_id' => $this->tenant_id,
            'name' => $name,
            'type' => 'scheduled',
            'disk' => $this->disk,
            'included_tables' => $this->included_tables,
            'excluded_tables' => $this->excluded_tables,
            'include_files' => $this->include_files,
            'retention_days' => $this->retention_days,
            'created_by' => null // System created
        ]);
    }

    public function getFrequencyLabel(): string
    {
        return match($this->frequency) {
            'daily' => 'Diario',
            'weekly' => 'Semanal',
            'monthly' => 'Mensual',
            default => ucfirst($this->frequency)
        };
    }

    public function getScheduleDescription(): string
    {
        $time = Carbon::parse($this->time)->format('H:i');
        
        switch ($this->frequency) {
            case 'daily':
                return "Diariamente a las {$time}";
                
            case 'weekly':
                $days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                $day = $days[$this->day_of_week] ?? '';
                return "Cada {$day} a las {$time}";
                
            case 'monthly':
                $day = $this->day_of_month;
                return "El día {$day} de cada mes a las {$time}";
                
            default:
                return "{$this->frequency} a las {$time}";
        }
    }

    public function getSuccessRate(): float
    {
        if ($this->run_count === 0) {
            return 0;
        }

        return round(($this->success_count / $this->run_count) * 100, 1);
    }

    public function shouldNotify(string $event): bool
    {
        if (!$this->notification_settings) {
            return false;
        }

        return $this->notification_settings[$event] ?? false;
    }

    public function getNotificationEmails(): array
    {
        if (!$this->notification_settings || !isset($this->notification_settings['emails'])) {
            return [];
        }

        return array_filter($this->notification_settings['emails']);
    }

    public static function getDueSchedules(): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', now());
            })
            ->get();
    }
}