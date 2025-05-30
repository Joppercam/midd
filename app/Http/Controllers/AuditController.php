<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\AuditSetting;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class AuditController extends Controller
{
    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    public function index(Request $request): Response
    {
        $tenantId = auth()->user()->tenant_id;
        
        $filters = $request->only([
            'user_id', 'model_type', 'event', 
            'date_from', 'date_to', 'search'
        ]);
        $filters['limit'] = 100;
        
        $logs = $this->auditService->getActivityFeed($tenantId, $filters);
        
        // Get filter options
        $users = User::where('tenant_id', $tenantId)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();
            
        $modelTypes = AuditLog::where('tenant_id', $tenantId)
            ->distinct('auditable_type')
            ->pluck('auditable_type')
            ->map(fn($type) => [
                'value' => $type,
                'label' => class_basename($type)
            ]);
            
        $events = AuditLog::where('tenant_id', $tenantId)
            ->distinct('event')
            ->pluck('event');

        return Inertia::render('Audit/Index', [
            'logs' => $logs,
            'filters' => $filters,
            'users' => $users,
            'modelTypes' => $modelTypes,
            'events' => $events
        ]);
    }

    public function show(AuditLog $log): Response
    {
        $this->authorize('view', $log);
        
        $log->load(['user', 'auditable']);
        
        // Get related logs
        if ($log->auditable) {
            $relatedLogs = $log->auditable->auditLogs()
                ->where('id', '!=', $log->id)
                ->limit(10)
                ->get();
        } else {
            $relatedLogs = collect();
        }
        
        return Inertia::render('Audit/Show', [
            'log' => $log,
            'relatedLogs' => $relatedLogs,
            'diff' => $log->getFormattedDiff()
        ]);
    }

    public function userActivity(Request $request, User $user): Response
    {
        $this->authorize('viewAny', AuditLog::class);
        
        $days = $request->get('days', 30);
        $activity = $this->auditService->getUserActivity($user->id, $days);
        
        return Inertia::render('Audit/UserActivity', [
            'user' => $user,
            'activity' => $activity,
            'days' => $days
        ]);
    }

    public function modelHistory(Request $request): Response
    {
        $this->authorize('viewAny', AuditLog::class);
        
        $modelType = $request->get('model_type');
        $modelId = $request->get('model_id');
        
        if (!$modelType || !$modelId) {
            return back()->with('error', 'Modelo no especificado');
        }
        
        $model = $modelType::find($modelId);
        if (!$model) {
            return back()->with('error', 'Modelo no encontrado');
        }
        
        $history = $this->auditService->getModelHistory($model);
        
        return Inertia::render('Audit/ModelHistory', [
            'model' => $model,
            'history' => $history,
            'modelName' => class_basename($modelType) . ' #' . $modelId
        ]);
    }

    public function statistics(Request $request): Response
    {
        $this->authorize('viewAny', AuditLog::class);
        
        $tenantId = auth()->user()->tenant_id;
        $days = $request->get('days', 30);
        
        $statistics = $this->auditService->getSystemStatistics($tenantId, $days);
        
        return Inertia::render('Audit/Statistics', [
            'statistics' => $statistics,
            'days' => $days
        ]);
    }

    public function settings(): Response
    {
        $this->authorize('manage', AuditSetting::class);
        
        $tenantId = auth()->user()->tenant_id;
        
        $settings = AuditSetting::where('tenant_id', $tenantId)
            ->orWhereNull('tenant_id')
            ->get()
            ->groupBy('tenant_id');
        
        $auditableModels = AuditSetting::getAuditableModels();
        
        return Inertia::render('Audit/Settings', [
            'settings' => $settings,
            'auditableModels' => $auditableModels
        ]);
    }

    public function updateSettings(Request $request): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('manage', AuditSetting::class);
        
        $validated = $request->validate([
            'model_class' => 'required|string',
            'is_enabled' => 'boolean',
            'events' => 'nullable|array',
            'events.*' => 'string|in:created,updated,deleted,restored',
            'excluded_fields' => 'nullable|array',
            'excluded_fields.*' => 'string',
            'masked_fields' => 'nullable|array',
            'masked_fields.*' => 'string',
            'retention_days' => 'nullable|integer|min:0|max:3650'
        ]);
        
        $tenantId = auth()->user()->tenant_id;
        
        $this->auditService->configureAuditSettings(
            $validated['model_class'],
            $tenantId,
            $validated
        );
        
        return back()->with('success', 'Configuración de auditoría actualizada');
    }

    public function export(Request $request)
    {
        $this->authorize('export', AuditLog::class);
        
        $filters = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'exists:users,id',
            'events' => 'nullable|array',
            'events.*' => 'string',
            'models' => 'nullable|array',
            'models.*' => 'string'
        ]);
        
        $filters['tenant_id'] = auth()->user()->tenant_id;
        
        $logs = $this->auditService->exportAuditLogs($filters);
        
        $filename = sprintf(
            'audit_logs_%s_%s.csv',
            $filters['date_from'] ?? 'all',
            $filters['date_to'] ?? now()->format('Y-m-d')
        );
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];
        
        $callback = function () use ($logs) {
            $file = fopen('php://output', 'w');
            
            // Headers
            fputcsv($file, [
                'Fecha',
                'Usuario',
                'Acción',
                'Modelo',
                'Registro',
                'Cambios',
                'IP',
                'URL'
            ]);
            
            // Data
            foreach ($logs as $log) {
                fputcsv($file, $log);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }

    public function compliance(Request $request): Response
    {
        $this->authorize('viewCompliance', AuditLog::class);
        
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);
        
        $tenantId = auth()->user()->tenant_id;
        
        $report = $this->auditService->getComplianceReport(
            $tenantId,
            Carbon::parse($validated['start_date']),
            Carbon::parse($validated['end_date'])
        );
        
        return Inertia::render('Audit/Compliance', [
            'report' => $report,
            'startDate' => $validated['start_date'],
            'endDate' => $validated['end_date']
        ]);
    }

    public function search(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('viewAny', AuditLog::class);
        
        $query = $request->get('q', '');
        $tenantId = auth()->user()->tenant_id;
        
        if (strlen($query) < 3) {
            return response()->json([]);
        }
        
        $results = $this->auditService->searchAuditLogs($query, $tenantId, 20);
        
        return response()->json($results);
    }

    public function cleanup(Request $request): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('manage', AuditSetting::class);
        
        $count = AuditLog::cleanup();
        
        return back()->with('success', "Se eliminaron {$count} registros de auditoría antiguos");
    }
}