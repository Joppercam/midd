<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AuditLog;
use App\Models\AuditSetting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class AuditApiController extends BaseApiController
{
    public function logs(Request $request): JsonResponse
    {
        if (!$this->checkApiPermission('audit.view')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|exists:users,id',
            'event' => 'nullable|string|max:100',
            'auditable_type' => 'nullable|string|max:100',
            'auditable_id' => 'nullable|integer',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'ip_address' => 'nullable|ip',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = AuditLog::with(['user', 'auditable']);

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->filled('event')) {
                $query->where('event', 'like', "%{$request->event}%");
            }

            if ($request->filled('auditable_type')) {
                $query->where('auditable_type', $request->auditable_type);
            }

            if ($request->filled('auditable_id')) {
                $query->where('auditable_id', $request->auditable_id);
            }

            if ($request->filled('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
            }

            if ($request->filled('ip_address')) {
                $query->where('ip_address', $request->ip_address);
            }

            $logs = $query->orderBy('created_at', 'desc')
                         ->paginate($request->get('per_page', 15));

            $this->logApiActivity('audit.logs', $request);

            return response()->json([
                'data' => $logs->items(),
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                ]
            ]);
        } catch (\Exception $e) {
            return $this->handleApiException($e, 'Error retrieving audit logs');
        }
    }

    public function show(AuditLog $auditLog): JsonResponse
    {
        if (!$this->checkApiPermission('audit.view')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$this->verifyTenantAccess($auditLog)) {
            return response()->json(['error' => 'Resource not found'], 404);
        }

        try {
            $auditLog->load(['user', 'auditable']);

            $this->logApiActivity('audit.show', request(), $auditLog->id);

            return response()->json(['data' => $auditLog]);
        } catch (\Exception $e) {
            return $this->handleApiException($e, 'Error retrieving audit log');
        }
    }

    public function settings(): JsonResponse
    {
        if (!$this->checkApiPermission('audit.configure')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $settings = AuditSetting::first();

            $this->logApiActivity('audit.settings', request());

            return response()->json(['data' => $settings]);
        } catch (\Exception $e) {
            return $this->handleApiException($e, 'Error retrieving audit settings');
        }
    }

    public function updateSettings(Request $request): JsonResponse
    {
        if (!$this->checkApiPermission('audit.configure')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'enabled' => 'required|boolean',
            'retention_days' => 'required|integer|min:1|max:3650',
            'events' => 'required|array',
            'events.*' => 'string|max:100',
            'log_ip_address' => 'boolean',
            'log_user_agent' => 'boolean',
            'log_request_data' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $settings = AuditSetting::firstOrCreate([]);
            $settings->update($validator->validated());

            $this->logApiActivity('audit.update-settings', $request, $settings->id);

            return response()->json([
                'message' => 'Audit settings updated successfully',
                'data' => $settings
            ]);
        } catch (\Exception $e) {
            return $this->handleApiException($e, 'Error updating audit settings');
        }
    }

    public function stats(Request $request): JsonResponse
    {
        if (!$this->checkApiPermission('audit.view')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
            $dateTo = $request->get('date_to', now()->format('Y-m-d'));

            $stats = [
                'total_logs' => AuditLog::whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])->count(),
                'unique_users' => AuditLog::whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                    ->distinct('user_id')->count('user_id'),
                'top_events' => AuditLog::whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                    ->selectRaw('event, COUNT(*) as count')
                    ->groupBy('event')
                    ->orderBy('count', 'desc')
                    ->take(10)
                    ->get(),
                'top_users' => AuditLog::whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                    ->with('user:id,name,email')
                    ->selectRaw('user_id, COUNT(*) as count')
                    ->groupBy('user_id')
                    ->orderBy('count', 'desc')
                    ->take(10)
                    ->get(),
                'daily_activity' => AuditLog::whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                    ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get(),
                'storage_size' => $this->calculateStorageSize(),
            ];

            $this->logApiActivity('audit.stats', $request);

            return response()->json(['data' => $stats]);
        } catch (\Exception $e) {
            return $this->handleApiException($e, 'Error retrieving audit statistics');
        }
    }

    public function cleanup(Request $request): JsonResponse
    {
        if (!$this->checkApiPermission('audit.manage')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'days' => 'required|integer|min:1|max:3650',
            'confirm' => 'required|boolean|accepted',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $cutoffDate = now()->subDays($request->days);
            $deletedCount = AuditLog::where('created_at', '<', $cutoffDate)->delete();

            $this->logApiActivity('audit.cleanup', $request);

            return response()->json([
                'message' => 'Audit cleanup completed successfully',
                'data' => [
                    'deleted_records' => $deletedCount,
                    'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s')
                ]
            ]);
        } catch (\Exception $e) {
            return $this->handleApiException($e, 'Error performing audit cleanup');
        }
    }

    public function export(Request $request): JsonResponse
    {
        if (!$this->checkApiPermission('audit.export')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'format' => 'required|in:csv,json,excel',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'user_id' => 'nullable|exists:users,id',
            'event' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = AuditLog::with(['user'])
                ->whereBetween('created_at', [
                    $request->date_from,
                    $request->date_to . ' 23:59:59'
                ]);

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->filled('event')) {
                $query->where('event', 'like', "%{$request->event}%");
            }

            $logs = $query->orderBy('created_at', 'desc')->get();

            $filename = 'audit_logs_' . $request->date_from . '_to_' . $request->date_to . '.' . $request->format;

            $this->logApiActivity('audit.export', $request);

            return response()->json([
                'message' => 'Export prepared successfully',
                'data' => [
                    'filename' => $filename,
                    'records_count' => $logs->count(),
                    'download_url' => route('api.v1.audit.download', [
                        'filename' => $filename,
                        'token' => encrypt([
                            'user_id' => auth()->id(),
                            'expires_at' => now()->addMinutes(30),
                            'logs' => $logs->toArray()
                        ])
                    ])
                ]
            ]);
        } catch (\Exception $e) {
            return $this->handleApiException($e, 'Error preparing audit export');
        }
    }

    private function calculateStorageSize(): array
    {
        try {
            $totalRecords = AuditLog::count();
            $avgRecordSize = 2048; // Estimated average size in bytes
            $estimatedSize = $totalRecords * $avgRecordSize;

            return [
                'total_records' => $totalRecords,
                'estimated_size_bytes' => $estimatedSize,
                'estimated_size_mb' => round($estimatedSize / 1024 / 1024, 2),
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Unable to calculate storage size'
            ];
        }
    }
}