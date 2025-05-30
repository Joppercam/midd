<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupRestoreLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'backup_id',
        'tenant_id',
        'restored_by',
        'status',
        'restore_type',
        'restored_tables',
        'restored_files',
        'notes',
        'error_message',
        'started_at',
        'completed_at'
    ];

    protected $casts = [
        'restored_tables' => 'array',
        'restored_files' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime'
    ];

    public function backup(): BelongsTo
    {
        return $this->belongsTo(Backup::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function restoredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restored_by');
    }

    public function start(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now()
        ]);
    }

    public function complete(array $restoredTables = [], array $restoredFiles = []): void
    {
        $this->update([
            'status' => 'completed',
            'restored_tables' => $restoredTables,
            'restored_files' => $restoredFiles,
            'completed_at' => now()
        ]);
    }

    public function fail(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
            'completed_at' => now()
        ]);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function getDuration(): ?string
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }

        $duration = $this->started_at->diffInSeconds($this->completed_at);
        
        if ($duration < 60) {
            return $duration . ' segundos';
        } elseif ($duration < 3600) {
            return round($duration / 60, 1) . ' minutos';
        } else {
            return round($duration / 3600, 1) . ' horas';
        }
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            'pending' => 'Pendiente',
            'running' => 'En Proceso',
            'completed' => 'Completado',
            'failed' => 'Fallido',
            default => ucfirst($this->status)
        };
    }

    public function getRestoreTypeLabel(): string
    {
        return match($this->restore_type) {
            'full' => 'Completo',
            'tables_only' => 'Solo Tablas',
            'files_only' => 'Solo Archivos',
            default => ucfirst($this->restore_type)
        };
    }

    public function getSummary(): array
    {
        return [
            'tables_restored' => count($this->restored_tables ?? []),
            'files_restored' => count($this->restored_files ?? []),
            'duration' => $this->getDuration(),
            'status' => $this->getStatusLabel(),
            'type' => $this->getRestoreTypeLabel()
        ];
    }
}