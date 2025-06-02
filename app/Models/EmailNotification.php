<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EmailNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'notifiable_type',
        'notifiable_id',
        'email_type',
        'recipient_email',
        'recipient_name',
        'subject',
        'body',
        'attachments',
        'status',
        'sent_at',
        'opened_at',
        'failure_reason',
        'metadata'
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'attachments' => 'array',
        'metadata' => 'array'
    ];

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function markAsOpened(): void
    {
        if (!$this->opened_at) {
            $this->update([
                'opened_at' => now(),
                'status' => 'opened'
            ]);
        }
    }

    public function getTypeDisplayAttribute(): string
    {
        return match($this->email_type) {
            'invoice_sent' => 'Factura Enviada',
            'payment_reminder' => 'Recordatorio de Pago',
            'payment_overdue' => 'Pago Vencido',
            'payment_received' => 'Pago Recibido',
            'credit_note_sent' => 'Nota de Crédito',
            default => 'Notificación'
        };
    }

    public function getStatusDisplayAttribute(): string
    {
        return match($this->status) {
            'pending' => 'Pendiente',
            'sent' => 'Enviado',
            'failed' => 'Fallido',
            'bounced' => 'Rebotado',
            'opened' => 'Abierto',
            'clicked' => 'Clickeado',
            default => $this->status
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'yellow',
            'sent' => 'blue',
            'failed' => 'red',
            'bounced' => 'red',
            'opened' => 'green',
            'clicked' => 'green',
            default => 'gray'
        };
    }

    public static function getStatistics(string $tenantId): array
    {
        $query = self::where('tenant_id', $tenantId);
        
        return [
            'total' => $query->count(),
            'sent' => $query->where('status', 'sent')->count(),
            'opened' => $query->where('status', 'opened')->count(),
            'failed' => $query->where('status', 'failed')->count(),
            'today' => $query->whereDate('created_at', today())->count(),
            'this_month' => $query->whereMonth('created_at', now()->month)
                                  ->whereYear('created_at', now()->year)
                                  ->count()
        ];
    }
}