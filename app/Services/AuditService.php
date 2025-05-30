<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\AuditSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AuditService
{
    public function getActivityFeed(?int $tenantId = null, array $filters = []): Collection
    {
        $query = AuditLog::query();
        
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        
        if (!empty($filters['model_type'])) {
            $query->where('auditable_type', $filters['model_type']);
        }
        
        if (!empty($filters['event'])) {
            $query->where('event', $filters['event']);
        }
        
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
        
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('auditable_name', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%")
                    ->orWhere('user_email', 'like', "%{$search}%");
            });
        }
        
        return $query->with(['user', 'auditable'])
            ->orderBy('created_at', 'desc')
            ->limit($filters['limit'] ?? 100)
            ->get();
    }

    public function getModelHistory(Model $model, int $limit = 50): Collection
    {
        return AuditLog::forModel($model)
            ->with('user')
            ->limit($limit)
            ->get();
    }

    public function getUserActivity(int $userId, int $days = 30): array
    {
        $since = now()->subDays($days);
        
        $activity = AuditLog::where('user_id', $userId)
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COUNT(DISTINCT auditable_type) as models_affected')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        $byEvent = AuditLog::where('user_id', $userId)
            ->where('created_at', '>=', $since)
            ->selectRaw('event, COUNT(*) as count')
            ->groupBy('event')
            ->orderByDesc('count')
            ->get();
        
        $byModel = AuditLog::where('user_id', $userId)
            ->where('created_at', '>=', $since)
            ->selectRaw('auditable_type, COUNT(*) as count')
            ->groupBy('auditable_type')
            ->orderByDesc('count')
            ->get();
        
        return [
            'daily_activity' => $activity,
            'by_event' => $byEvent,
            'by_model' => $byModel,
            'total_actions' => $activity->sum('total'),
            'active_days' => $activity->count()
        ];
    }

    public function getSystemStatistics(?int $tenantId = null, int $days = 30): array
    {
        $query = AuditLog::query();
        
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        
        $since = now()->subDays($days);
        $query->where('created_at', '>=', $since);
        
        // Most active users
        $activeUsers = (clone $query)
            ->selectRaw('user_id, user_name, user_email, COUNT(*) as action_count')
            ->groupBy('user_id', 'user_name', 'user_email')
            ->orderByDesc('action_count')
            ->limit(10)
            ->get();
        
        // Most modified models
        $modifiedModels = (clone $query)
            ->selectRaw('auditable_type, COUNT(*) as modification_count')
            ->groupBy('auditable_type')
            ->orderByDesc('modification_count')
            ->get();
        
        // Activity by hour
        $hourlyActivity = (clone $query)
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();
        
        // Activity by day of week
        $weeklyActivity = (clone $query)
            ->selectRaw('DAYOFWEEK(created_at) as day_of_week, COUNT(*) as count')
            ->groupBy('day_of_week')
            ->orderBy('day_of_week')
            ->get();
        
        return [
            'total_actions' => (clone $query)->count(),
            'unique_users' => (clone $query)->distinct('user_id')->count('user_id'),
            'active_users' => $activeUsers,
            'modified_models' => $modifiedModels,
            'hourly_activity' => $hourlyActivity,
            'weekly_activity' => $weeklyActivity
        ];
    }

    public function getChangeSummary(Model $model, Carbon $since = null): array
    {
        $query = AuditLog::forModel($model);
        
        if ($since) {
            $query->where('created_at', '>=', $since);
        }
        
        $changes = $query->whereIn('event', ['created', 'updated'])->get();
        
        $fieldChanges = [];
        
        foreach ($changes as $change) {
            if ($change->changed_fields) {
                foreach ($change->changed_fields as $field) {
                    if (!isset($fieldChanges[$field])) {
                        $fieldChanges[$field] = 0;
                    }
                    $fieldChanges[$field]++;
                }
            }
        }
        
        arsort($fieldChanges);
        
        return [
            'total_changes' => $changes->count(),
            'field_changes' => $fieldChanges,
            'last_change' => $changes->first(),
            'unique_users' => $changes->pluck('user_id')->unique()->count()
        ];
    }

    public function searchAuditLogs(string $query, ?int $tenantId = null, int $limit = 50): Collection
    {
        $searchQuery = AuditLog::query();
        
        if ($tenantId) {
            $searchQuery->where('tenant_id', $tenantId);
        }
        
        // Search in multiple fields
        $searchQuery->where(function ($q) use ($query) {
            $q->where('auditable_name', 'like', "%{$query}%")
                ->orWhere('user_name', 'like', "%{$query}%")
                ->orWhere('user_email', 'like', "%{$query}%")
                ->orWhereJsonContains('old_values', $query)
                ->orWhereJsonContains('new_values', $query)
                ->orWhereJsonContains('metadata', $query);
        });
        
        return $searchQuery->with(['user', 'auditable'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function exportAuditLogs(array $filters = []): array
    {
        $query = $this->buildExportQuery($filters);
        
        $logs = $query->get();
        
        $export = [];
        
        foreach ($logs as $log) {
            $export[] = [
                'date' => $log->created_at->format('Y-m-d H:i:s'),
                'user' => $log->user_name ?? 'Sistema',
                'action' => $log->getEventLabel(),
                'model' => $log->getModelLabel(),
                'record' => $log->auditable_name ?? 'N/A',
                'changes' => $this->formatChangesForExport($log),
                'ip_address' => $log->ip_address,
                'url' => $log->url
            ];
        }
        
        return $export;
    }

    protected function buildExportQuery(array $filters)
    {
        $query = AuditLog::query();
        
        if (!empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }
        
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
        
        if (!empty($filters['user_ids'])) {
            $query->whereIn('user_id', $filters['user_ids']);
        }
        
        if (!empty($filters['events'])) {
            $query->whereIn('event', $filters['events']);
        }
        
        if (!empty($filters['models'])) {
            $query->whereIn('auditable_type', $filters['models']);
        }
        
        return $query->orderBy('created_at', 'desc');
    }

    protected function formatChangesForExport(AuditLog $log): string
    {
        if (!$log->changed_fields || empty($log->changed_fields)) {
            return '';
        }
        
        $changes = [];
        foreach ($log->getFormattedDiff() as $diff) {
            $changes[] = sprintf(
                '%s: %s → %s',
                $diff['field'],
                $diff['old'],
                $diff['new']
            );
        }
        
        return implode('; ', $changes);
    }

    public function getComplianceReport(?int $tenantId, Carbon $startDate, Carbon $endDate): array
    {
        $query = AuditLog::whereBetween('created_at', [$startDate, $endDate]);
        
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        
        // Data access report
        $dataAccess = (clone $query)
            ->whereIn('event', ['created', 'updated', 'deleted'])
            ->selectRaw('auditable_type, event, COUNT(*) as count')
            ->groupBy('auditable_type', 'event')
            ->get();
        
        // User actions report
        $userActions = (clone $query)
            ->selectRaw('user_id, user_name, COUNT(*) as total_actions')
            ->selectRaw('COUNT(DISTINCT DATE(created_at)) as active_days')
            ->groupBy('user_id', 'user_name')
            ->orderByDesc('total_actions')
            ->get();
        
        // Sensitive data access
        $sensitiveModels = ['App\Models\User', 'App\Models\Payment', 'App\Models\BankAccount'];
        $sensitiveAccess = (clone $query)
            ->whereIn('auditable_type', $sensitiveModels)
            ->selectRaw('user_id, user_name, auditable_type, COUNT(*) as access_count')
            ->groupBy('user_id', 'user_name', 'auditable_type')
            ->get();
        
        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ],
            'summary' => [
                'total_actions' => (clone $query)->count(),
                'unique_users' => (clone $query)->distinct('user_id')->count('user_id'),
                'models_accessed' => (clone $query)->distinct('auditable_type')->count('auditable_type')
            ],
            'data_access' => $dataAccess,
            'user_actions' => $userActions,
            'sensitive_access' => $sensitiveAccess
        ];
    }

    public function configureAuditSettings(string $modelClass, ?int $tenantId, array $settings): AuditSetting
    {
        $auditSetting = AuditSetting::firstOrNew([
            'tenant_id' => $tenantId,
            'model_class' => $modelClass
        ]);
        
        $auditSetting->fill($settings);
        $auditSetting->save();
        
        return $auditSetting;
    }
}