<?php

namespace App\Http\Controllers;

use App\Services\BackupService;
use App\Models\Backup;
use App\Models\BackupSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Carbon\Carbon;

class BackupController extends Controller
{
    protected BackupService $backupService;

    public function __construct(BackupService $backupService)
    {
        $this->middleware(['auth', 'tenant']);
        $this->backupService = $backupService;
    }

    /**
     * Display backup management interface
     */
    public function index()
    {
        $tenant = app('currentTenant');
        
        $backups = Backup::where('tenant_id', $tenant->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        
        $schedules = BackupSchedule::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get();
        
        $statistics = $this->getBackupStatistics();
        
        return Inertia::render('Backups/Index', [
            'backups' => $backups,
            'schedules' => $schedules,
            'statistics' => $statistics,
            'storageInfo' => $this->getStorageInfo()
        ]);
    }

    /**
     * Create manual backup
     */
    public function create(Request $request)
    {
        $request->validate([
            'type' => 'required|in:full,database,files',
            'description' => 'nullable|string|max:255'
        ]);

        try {
            $backup = $this->backupService->createBackup(
                $request->type,
                $request->description
            );

            return back()->with('success', 'Backup creado exitosamente');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al crear backup: ' . $e->getMessage()]);
        }
    }

    /**
     * Download backup
     */
    public function download(Backup $backup)
    {
        $this->authorize('download', $backup);
        
        if (!Storage::disk('backups')->exists($backup->file_path)) {
            return back()->withErrors(['error' => 'Archivo de backup no encontrado']);
        }

        return Storage::disk('backups')->download($backup->file_path, $backup->filename);
    }

    /**
     * Delete backup
     */
    public function destroy(Backup $backup)
    {
        $this->authorize('delete', $backup);
        
        try {
            $this->backupService->deleteBackup($backup->id);
            return back()->with('success', 'Backup eliminado exitosamente');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al eliminar backup: ' . $e->getMessage()]);
        }
    }

    /**
     * Restore backup
     */
    public function restore(Request $request, Backup $backup)
    {
        $this->authorize('restore', $backup);
        
        $request->validate([
            'confirmation' => 'required|string|in:CONFIRMAR_RESTAURACION'
        ]);

        try {
            $result = $this->backupService->restoreBackup($backup->id);
            
            if ($result['success']) {
                return back()->with('success', 'Backup restaurado exitosamente');
            } else {
                return back()->withErrors(['error' => 'Error en restauración: ' . $result['message']]);
            }
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al restaurar backup: ' . $e->getMessage()]);
        }
    }

    /**
     * Backup schedules management
     */
    public function schedules()
    {
        $tenant = app('currentTenant');
        
        $schedules = BackupSchedule::where('tenant_id', $tenant->id)
            ->orderBy('created_at', 'desc')
            ->get();
        
        return Inertia::render('Backups/Schedules', [
            'schedules' => $schedules,
            'timezones' => $this->getTimezones()
        ]);
    }

    /**
     * Create backup schedule
     */
    public function createSchedule(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:full,database,files',
            'frequency' => 'required|in:daily,weekly,monthly',
            'time' => 'required|date_format:H:i',
            'day_of_week' => 'nullable|integer|min:0|max:6',
            'day_of_month' => 'nullable|integer|min:1|max:31',
            'retention_days' => 'required|integer|min:1|max:365',
            'is_active' => 'boolean',
            'notifications' => 'array',
            'notifications.*' => 'email'
        ]);

        $tenant = app('currentTenant');

        try {
            $schedule = BackupSchedule::create([
                'tenant_id' => $tenant->id,
                'name' => $request->name,
                'type' => $request->type,
                'frequency' => $request->frequency,
                'time' => $request->time,
                'day_of_week' => $request->day_of_week,
                'day_of_month' => $request->day_of_month,
                'retention_days' => $request->retention_days,
                'is_active' => $request->boolean('is_active', true),
                'notifications' => $request->notifications ?? [],
                'next_run' => $this->calculateNextRun($request),
                'created_by' => auth()->id()
            ]);

            return back()->with('success', 'Programación de backup creada exitosamente');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al crear programación: ' . $e->getMessage()]);
        }
    }

    /**
     * Update backup schedule
     */
    public function updateSchedule(Request $request, BackupSchedule $schedule)
    {
        $this->authorize('update', $schedule);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:full,database,files',
            'frequency' => 'required|in:daily,weekly,monthly',
            'time' => 'required|date_format:H:i',
            'day_of_week' => 'nullable|integer|min:0|max:6',
            'day_of_month' => 'nullable|integer|min:1|max:31',
            'retention_days' => 'required|integer|min:1|max:365',
            'is_active' => 'boolean',
            'notifications' => 'array',
            'notifications.*' => 'email'
        ]);

        try {
            $schedule->update([
                'name' => $request->name,
                'type' => $request->type,
                'frequency' => $request->frequency,
                'time' => $request->time,
                'day_of_week' => $request->day_of_week,
                'day_of_month' => $request->day_of_month,
                'retention_days' => $request->retention_days,
                'is_active' => $request->boolean('is_active'),
                'notifications' => $request->notifications ?? [],
                'next_run' => $this->calculateNextRun($request)
            ]);

            return back()->with('success', 'Programación actualizada exitosamente');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al actualizar programación: ' . $e->getMessage()]);
        }
    }

    /**
     * Delete backup schedule
     */
    public function deleteSchedule(BackupSchedule $schedule)
    {
        $this->authorize('delete', $schedule);
        
        try {
            $schedule->delete();
            return back()->with('success', 'Programación eliminada exitosamente');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al eliminar programación: ' . $e->getMessage()]);
        }
    }

    /**
     * Toggle schedule status
     */
    public function toggleSchedule(BackupSchedule $schedule)
    {
        $this->authorize('update', $schedule);
        
        try {
            $schedule->update([
                'is_active' => !$schedule->is_active,
                'next_run' => $schedule->is_active ? null : $this->calculateNextRun($schedule)
            ]);

            $status = $schedule->is_active ? 'activada' : 'desactivada';
            return back()->with('success', "Programación {$status} exitosamente");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al cambiar estado: ' . $e->getMessage()]);
        }
    }

    /**
     * Test backup schedule
     */
    public function testSchedule(BackupSchedule $schedule)
    {
        $this->authorize('update', $schedule);
        
        try {
            $backup = $this->backupService->createBackup(
                $schedule->type,
                "Test de programación: {$schedule->name}"
            );

            return back()->with('success', 'Backup de prueba creado exitosamente');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error en backup de prueba: ' . $e->getMessage()]);
        }
    }

    /**
     * Get backup logs
     */
    public function logs(Request $request)
    {
        $tenant = app('currentTenant');
        
        $query = Backup::where('tenant_id', $tenant->id);
        
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        
        $logs = $query->orderBy('created_at', 'desc')->paginate(50);
        
        return Inertia::render('Backups/Logs', [
            'logs' => $logs,
            'filters' => $request->all()
        ]);
    }

    /**
     * Cleanup old backups
     */
    public function cleanup(Request $request)
    {
        $request->validate([
            'days' => 'required|integer|min:1|max:365'
        ]);

        try {
            $deleted = $this->backupService->cleanupOldBackups($request->days);
            return back()->with('success', "Se eliminaron {$deleted} backups antiguos");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error en limpieza: ' . $e->getMessage()]);
        }
    }

    /**
     * Get backup statistics
     */
    protected function getBackupStatistics(): array
    {
        $tenant = app('currentTenant');
        
        $stats = [
            'total' => Backup::where('tenant_id', $tenant->id)->count(),
            'successful' => Backup::where('tenant_id', $tenant->id)->where('status', 'completed')->count(),
            'failed' => Backup::where('tenant_id', $tenant->id)->where('status', 'failed')->count(),
            'total_size' => Backup::where('tenant_id', $tenant->id)->where('status', 'completed')->sum('file_size'),
            'last_backup' => Backup::where('tenant_id', $tenant->id)
                ->where('status', 'completed')
                ->latest()
                ->first()?->created_at,
            'scheduled_count' => BackupSchedule::where('tenant_id', $tenant->id)->where('is_active', true)->count()
        ];
        
        // Success rate
        $stats['success_rate'] = $stats['total'] > 0 
            ? round(($stats['successful'] / $stats['total']) * 100, 1) 
            : 0;
        
        // Next scheduled backup
        $stats['next_scheduled'] = BackupSchedule::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->whereNotNull('next_run')
            ->orderBy('next_run')
            ->first()?->next_run;
        
        // Recent activity (last 30 days)
        $stats['recent_activity'] = Backup::where('tenant_id', $tenant->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, status')
            ->groupBy('date', 'status')
            ->orderBy('date')
            ->get()
            ->groupBy('date');
        
        return $stats;
    }

    /**
     * Get storage information
     */
    protected function getStorageInfo(): array
    {
        $disk = Storage::disk('backups');
        
        try {
            $totalSpace = disk_total_space($disk->path(''));
            $freeSpace = disk_free_space($disk->path(''));
            $usedSpace = $totalSpace - $freeSpace;
            
            return [
                'total' => $totalSpace,
                'used' => $usedSpace,
                'free' => $freeSpace,
                'usage_percentage' => round(($usedSpace / $totalSpace) * 100, 1)
            ];
        } catch (\Exception $e) {
            return [
                'total' => 0,
                'used' => 0,
                'free' => 0,
                'usage_percentage' => 0,
                'error' => 'No se pudo obtener información del almacenamiento'
            ];
        }
    }

    /**
     * Calculate next run time for schedule
     */
    protected function calculateNextRun($scheduleData): Carbon
    {
        $now = now();
        $time = Carbon::createFromFormat('H:i', $scheduleData['time'] ?? $scheduleData->time);
        
        switch ($scheduleData['frequency'] ?? $scheduleData->frequency) {
            case 'daily':
                $next = $now->copy()->setTime($time->hour, $time->minute);
                if ($next->lte($now)) {
                    $next->addDay();
                }
                break;
                
            case 'weekly':
                $dayOfWeek = $scheduleData['day_of_week'] ?? $scheduleData->day_of_week ?? 1;
                $next = $now->copy()->setTime($time->hour, $time->minute);
                $next = $next->next(Carbon::MONDAY + $dayOfWeek - 1);
                break;
                
            case 'monthly':
                $dayOfMonth = $scheduleData['day_of_month'] ?? $scheduleData->day_of_month ?? 1;
                $next = $now->copy()->setTime($time->hour, $time->minute);
                $next = $next->startOfMonth()->addDays($dayOfMonth - 1);
                if ($next->lte($now)) {
                    $next->addMonth();
                }
                break;
                
            default:
                $next = $now->addDay();
        }
        
        return $next;
    }

    /**
     * Get available timezones
     */
    protected function getTimezones(): array
    {
        return [
            'America/Santiago' => 'Chile (Santiago)',
            'America/New_York' => 'Eastern Time (US)',
            'America/Los_Angeles' => 'Pacific Time (US)',
            'Europe/London' => 'London (GMT)',
            'Europe/Madrid' => 'Madrid (CET)',
            'UTC' => 'UTC'
        ];
    }
}