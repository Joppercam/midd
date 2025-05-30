<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class Backup extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'status',
        'disk',
        'path',
        'size',
        'included_tables',
        'excluded_tables',
        'include_files',
        'statistics',
        'error_message',
        'started_at',
        'completed_at',
        'retention_days',
        'expires_at',
        'created_by'
    ];

    protected $casts = [
        'size' => 'integer',
        'included_tables' => 'array',
        'excluded_tables' => 'array',
        'include_files' => 'boolean',
        'statistics' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime'
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function restoreLogs(): HasMany
    {
        return $this->hasMany(BackupRestoreLog::class);
    }

    public function start(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now()
        ]);
    }

    public function complete(string $path, int $size, array $statistics = []): void
    {
        $this->update([
            'status' => 'completed',
            'path' => $path,
            'size' => $size,
            'statistics' => $statistics,
            'completed_at' => now(),
            'expires_at' => now()->addDays($this->retention_days)
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

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function canBeRestored(): bool
    {
        return $this->isCompleted() && !$this->isExpired() && $this->fileExists();
    }

    public function fileExists(): bool
    {
        return $this->path && Storage::disk($this->disk)->exists($this->path);
    }

    public function getFile()
    {
        if (!$this->fileExists()) {
            throw new \Exception('Archivo de respaldo no encontrado');
        }

        return Storage::disk($this->disk)->get($this->path);
    }

    public function getDownloadUrl(): ?string
    {
        if (!$this->fileExists()) {
            return null;
        }

        if ($this->disk === 'local') {
            return route('backups.download', $this);
        }

        return Storage::disk($this->disk)->temporaryUrl(
            $this->path,
            now()->addMinutes(30)
        );
    }

    public function delete(): bool
    {
        // Delete physical file
        if ($this->fileExists()) {
            Storage::disk($this->disk)->delete($this->path);
        }

        return parent::delete();
    }

    public function getSizeFormatted(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
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

    public function getTypeLabel(): string
    {
        return match($this->type) {
            'manual' => 'Manual',
            'scheduled' => 'Programado',
            'automatic' => 'Automático',
            default => ucfirst($this->type)
        };
    }

    public static function cleanup(): int
    {
        $expiredBackups = self::where('expires_at', '<=', now())
            ->where('status', 'completed')
            ->get();

        $count = 0;
        foreach ($expiredBackups as $backup) {
            $backup->delete();
            $count++;
        }

        return $count;
    }

    public static function getStorageUsage(?int $tenantId = null): array
    {
        $query = self::where('status', 'completed');
        
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $totalSize = $query->sum('size');
        $backupCount = $query->count();

        return [
            'total_size' => $totalSize,
            'total_size_formatted' => self::formatBytes($totalSize),
            'backup_count' => $backupCount,
            'average_size' => $backupCount > 0 ? $totalSize / $backupCount : 0,
            'average_size_formatted' => $backupCount > 0 ? self::formatBytes($totalSize / $backupCount) : '0 B'
        ];
    }

    protected static function formatBytes($bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}