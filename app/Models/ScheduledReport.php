<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class ScheduledReport extends TenantAwareModel
{
    protected $fillable = [
        'user_id',
        'report_template_id',
        'name',
        'description',
        'frequency',
        'frequency_details',
        'parameters',
        'format',
        'recipients',
        'auto_send',
        'store_file',
        'storage_path',
        'is_active',
        'next_run_at',
        'last_run_at',
    ];

    protected $casts = [
        'frequency_details' => 'array',
        'parameters' => 'array',
        'recipients' => 'array',
        'auto_send' => 'boolean',
        'store_file' => 'boolean',
        'is_active' => 'boolean',
        'next_run_at' => 'datetime',
        'last_run_at' => 'datetime',
    ];

    const FREQUENCIES = [
        'daily' => 'Diario',
        'weekly' => 'Semanal',
        'monthly' => 'Mensual',
        'quarterly' => 'Trimestral',
        'yearly' => 'Anual',
    ];

    /**
     * User who owns this scheduled report
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Report template for this scheduled report
     */
    public function reportTemplate(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class);
    }

    /**
     * Report executions for this scheduled report
     */
    public function reportExecutions(): HasMany
    {
        return $this->hasMany(ReportExecution::class);
    }

    /**
     * Report shares for this scheduled report
     */
    public function reportShares(): HasMany
    {
        return $this->hasMany(ReportShare::class);
    }

    /**
     * Get last execution
     */
    public function lastExecution()
    {
        return $this->hasOne(ReportExecution::class)->latest();
    }

    /**
     * Get successful executions
     */
    public function successfulExecutions(): HasMany
    {
        return $this->reportExecutions()->where('status', 'completed');
    }

    /**
     * Get failed executions
     */
    public function failedExecutions(): HasMany
    {
        return $this->reportExecutions()->where('status', 'failed');
    }

    /**
     * Calculate next run time based on frequency and details
     */
    public function calculateNextRun(?Carbon $from = null): Carbon
    {
        $from = $from ?? now();
        $details = $this->frequency_details ?? [];

        switch ($this->frequency) {
            case 'daily':
                $hour = $details['hour'] ?? 9;
                $minute = $details['minute'] ?? 0;
                
                $nextRun = $from->copy()->addDay()->hour($hour)->minute($minute)->second(0);
                break;

            case 'weekly':
                $dayOfWeek = $details['day_of_week'] ?? 1; // Monday
                $hour = $details['hour'] ?? 9;
                $minute = $details['minute'] ?? 0;
                
                $nextRun = $from->copy()->next($dayOfWeek)->hour($hour)->minute($minute)->second(0);
                
                // If we're already past this week's scheduled time, go to next week
                if ($nextRun <= $from) {
                    $nextRun->addWeek();
                }
                break;

            case 'monthly':
                $dayOfMonth = $details['day_of_month'] ?? 1;
                $hour = $details['hour'] ?? 9;
                $minute = $details['minute'] ?? 0;
                
                $nextRun = $from->copy()->addMonth()->day($dayOfMonth)->hour($hour)->minute($minute)->second(0);
                
                // Handle cases where day doesn't exist in the month (e.g., Feb 31)
                while ($nextRun->day != $dayOfMonth) {
                    $nextRun = $nextRun->subDay();
                }
                break;

            case 'quarterly':
                $monthOfQuarter = $details['month_of_quarter'] ?? 1; // 1st month of quarter
                $dayOfMonth = $details['day_of_month'] ?? 1;
                $hour = $details['hour'] ?? 9;
                $minute = $details['minute'] ?? 0;
                
                $currentQuarter = ceil($from->month / 3);
                $nextQuarter = $currentQuarter + 1;
                if ($nextQuarter > 4) {
                    $nextQuarter = 1;
                    $year = $from->year + 1;
                } else {
                    $year = $from->year;
                }
                
                $quarterStartMonth = ($nextQuarter - 1) * 3 + 1;
                $targetMonth = $quarterStartMonth + $monthOfQuarter - 1;
                
                $nextRun = Carbon::create($year, $targetMonth, $dayOfMonth, $hour, $minute, 0);
                break;

            case 'yearly':
                $month = $details['month'] ?? 1;
                $dayOfMonth = $details['day_of_month'] ?? 1;
                $hour = $details['hour'] ?? 9;
                $minute = $details['minute'] ?? 0;
                
                $nextRun = $from->copy()->addYear()->month($month)->day($dayOfMonth)->hour($hour)->minute($minute)->second(0);
                break;

            default:
                throw new \InvalidArgumentException("Invalid frequency: {$this->frequency}");
        }

        return $nextRun;
    }

    /**
     * Update next run time
     */
    public function updateNextRun(): void
    {
        $this->update(['next_run_at' => $this->calculateNextRun()]);
    }

    /**
     * Mark as executed
     */
    public function markAsExecuted(): void
    {
        $this->update([
            'last_run_at' => now(),
            'next_run_at' => $this->calculateNextRun(),
        ]);
    }

    /**
     * Check if report is due for execution
     */
    public function isDue(): bool
    {
        return $this->is_active && 
               $this->next_run_at && 
               $this->next_run_at <= now();
    }

    /**
     * Get frequency label
     */
    public function getFrequencyLabelAttribute(): string
    {
        return self::FREQUENCIES[$this->frequency] ?? $this->frequency;
    }

    /**
     * Get frequency details in human readable format
     */
    public function getFrequencyDescriptionAttribute(): string
    {
        $details = $this->frequency_details ?? [];
        
        switch ($this->frequency) {
            case 'daily':
                $hour = $details['hour'] ?? 9;
                $minute = $details['minute'] ?? 0;
                return "Todos los días a las {$hour}:" . str_pad($minute, 2, '0', STR_PAD_LEFT);

            case 'weekly':
                $days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                $dayOfWeek = $details['day_of_week'] ?? 1;
                $hour = $details['hour'] ?? 9;
                $minute = $details['minute'] ?? 0;
                return "Todos los {$days[$dayOfWeek]} a las {$hour}:" . str_pad($minute, 2, '0', STR_PAD_LEFT);

            case 'monthly':
                $dayOfMonth = $details['day_of_month'] ?? 1;
                $hour = $details['hour'] ?? 9;
                $minute = $details['minute'] ?? 0;
                return "El día {$dayOfMonth} de cada mes a las {$hour}:" . str_pad($minute, 2, '0', STR_PAD_LEFT);

            case 'quarterly':
                $monthNames = ['primer', 'segundo', 'tercer'];
                $monthOfQuarter = $details['month_of_quarter'] ?? 1;
                $dayOfMonth = $details['day_of_month'] ?? 1;
                $hour = $details['hour'] ?? 9;
                $minute = $details['minute'] ?? 0;
                $monthName = $monthNames[$monthOfQuarter - 1] ?? $monthOfQuarter;
                return "El día {$dayOfMonth} del {$monthName} mes de cada trimestre a las {$hour}:" . str_pad($minute, 2, '0', STR_PAD_LEFT);

            case 'yearly':
                $months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                          'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
                $month = $details['month'] ?? 1;
                $dayOfMonth = $details['day_of_month'] ?? 1;
                $hour = $details['hour'] ?? 9;
                $minute = $details['minute'] ?? 0;
                return "El {$dayOfMonth} de {$months[$month - 1]} a las {$hour}:" . str_pad($minute, 2, '0', STR_PAD_LEFT);

            default:
                return $this->frequency_label;
        }
    }

    /**
     * Get recipients count
     */
    public function getRecipientsCountAttribute(): int
    {
        return count($this->recipients ?? []);
    }

    /**
     * Get execution success rate
     */
    public function getSuccessRateAttribute(): float
    {
        $total = $this->reportExecutions()->count();
        if ($total === 0) {
            return 0;
        }
        
        $successful = $this->successfulExecutions()->count();
        return round(($successful / $total) * 100, 1);
    }

    /**
     * Scope for active schedules
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for due schedules
     */
    public function scopeDue($query)
    {
        return $query->active()
                    ->whereNotNull('next_run_at')
                    ->where('next_run_at', '<=', now());
    }

    /**
     * Scope by frequency
     */
    public function scopeByFrequency($query, string $frequency)
    {
        return $query->where('frequency', $frequency);
    }

    /**
     * Scope by user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}