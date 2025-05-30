<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\AuditLog;
use App\Services\SuperAdmin\SystemMonitoringService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class SystemController extends Controller
{
    protected $monitoringService;

    public function __construct(SystemMonitoringService $monitoringService)
    {
        $this->monitoringService = $monitoringService;
    }

    public function settings()
    {
        $settings = SystemSetting::all()->groupBy('category');
        
        return Inertia::render('SuperAdmin/System/Settings', [
            'settings' => $settings,
        ]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'required',
        ]);

        foreach ($validated['settings'] as $setting) {
            SystemSetting::set($setting['key'], $setting['value']);
        }

        auth()->guard('super_admin')->user()->logActivity(
            'system_settings_updated',
            'Updated system settings',
            ['settings' => $validated['settings']]
        );

        Cache::forget('system_settings');

        return back()->with('success', 'System settings updated successfully');
    }

    public function monitoring()
    {
        $metrics = $this->monitoringService->getSystemMetrics();
        $performance = $this->monitoringService->getPerformanceMetrics();
        $health = $this->monitoringService->getHealthChecks();

        return Inertia::render('SuperAdmin/System/Monitoring', [
            'metrics' => $metrics,
            'performance' => $performance,
            'health' => $health,
        ]);
    }

    public function auditLogs(Request $request)
    {
        $query = AuditLog::with(['auditable', 'user']);

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->latest()->paginate(50);

        return Inertia::render('SuperAdmin/System/AuditLogs', [
            'logs' => $logs,
            'filters' => $request->only(['tenant_id', 'user_id', 'event', 'date_from', 'date_to']),
        ]);
    }

    public function maintenance()
    {
        $maintenanceMode = app()->isDownForMaintenance();
        $scheduledJobs = $this->getScheduledJobs();
        $queueStatus = $this->getQueueStatus();

        return Inertia::render('SuperAdmin/System/Maintenance', [
            'maintenanceMode' => $maintenanceMode,
            'scheduledJobs' => $scheduledJobs,
            'queueStatus' => $queueStatus,
        ]);
    }

    public function toggleMaintenance(Request $request)
    {
        $action = app()->isDownForMaintenance() ? 'up' : 'down';
        
        if ($action === 'down') {
            $secret = $request->input('secret', \Str::random(32));
            Artisan::call('down', [
                '--secret' => $secret,
                '--render' => 'maintenance',
            ]);
            
            $message = "Maintenance mode enabled. Secret: {$secret}";
        } else {
            Artisan::call('up');
            $message = 'Maintenance mode disabled';
        }

        auth()->guard('super_admin')->user()->logActivity(
            'maintenance_mode_toggled',
            $message,
            ['action' => $action]
        );

        return back()->with('success', $message);
    }

    public function clearCache()
    {
        Cache::flush();
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        auth()->guard('super_admin')->user()->logActivity(
            'cache_cleared',
            'System cache cleared'
        );

        return back()->with('success', 'Cache cleared successfully');
    }

    public function optimizeSystem()
    {
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('view:cache');
        Artisan::call('optimize');

        auth()->guard('super_admin')->user()->logActivity(
            'system_optimized',
            'System optimization completed'
        );

        return back()->with('success', 'System optimized successfully');
    }

    public function runCommand(Request $request)
    {
        $validated = $request->validate([
            'command' => 'required|string|in:migrate,queue:restart,schedule:run,backup:run',
        ]);

        try {
            Artisan::call($validated['command']);
            $output = Artisan::output();

            auth()->guard('super_admin')->user()->logActivity(
                'artisan_command_run',
                "Ran artisan command: {$validated['command']}",
                ['output' => $output]
            );

            return back()->with('success', "Command executed successfully: {$output}");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Command failed: ' . $e->getMessage()]);
        }
    }

    private function getScheduledJobs()
    {
        // Get scheduled jobs from the kernel
        return [
            ['command' => 'backup:run', 'schedule' => 'Daily at 2:00 AM'],
            ['command' => 'cleanup:logs', 'schedule' => 'Weekly on Sunday'],
            ['command' => 'process:webhooks', 'schedule' => 'Every 5 minutes'],
            ['command' => 'update:statistics', 'schedule' => 'Hourly'],
        ];
    }

    private function getQueueStatus()
    {
        return [
            'default' => DB::table('jobs')->where('queue', 'default')->count(),
            'high' => DB::table('jobs')->where('queue', 'high')->count(),
            'low' => DB::table('jobs')->where('queue', 'low')->count(),
            'failed' => DB::table('failed_jobs')->count(),
        ];
    }
}