<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Traits\ChecksPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

abstract class BaseApiController extends Controller
{
    use ChecksPermissions;
    protected function success($data = null, string $message = null, int $code = 200): JsonResponse
    {
        $response = [
            'success' => true
        ];

        if ($message) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    protected function error(string $message, int $code = 400, array $errors = []): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    protected function paginated(LengthAwarePaginator $paginator): JsonResponse
    {
        return $this->success([
            'items' => $paginator->items(),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem()
            ]
        ]);
    }

    protected function created(Model $model, string $message = 'Recurso creado exitosamente'): JsonResponse
    {
        return $this->success($model, $message, 201);
    }

    protected function updated(Model $model, string $message = 'Recurso actualizado exitosamente'): JsonResponse
    {
        return $this->success($model, $message);
    }

    protected function deleted(string $message = 'Recurso eliminado exitosamente'): JsonResponse
    {
        return $this->success(null, $message);
    }

    protected function notFound(string $message = 'Recurso no encontrado'): JsonResponse
    {
        return $this->error($message, 404);
    }

    protected function unauthorized(string $message = 'No autorizado'): JsonResponse
    {
        return $this->error($message, 401);
    }

    protected function forbidden(string $message = 'Sin permisos para esta acción'): JsonResponse
    {
        return $this->error($message, 403);
    }

    protected function validationError(array $errors): JsonResponse
    {
        return $this->error('Error de validación', 422, $errors);
    }

    protected function serverError(string $message = 'Error interno del servidor'): JsonResponse
    {
        return $this->error($message, 500);
    }

    protected function applyFilters(Request $request, $query)
    {
        // Date range filter
        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        // Search filter
        if ($request->has('search') && method_exists($query->getModel(), 'getSearchableColumns')) {
            $search = $request->search;
            $searchableColumns = $query->getModel()->getSearchableColumns();
            
            $query->where(function ($q) use ($search, $searchableColumns) {
                foreach ($searchableColumns as $column) {
                    $q->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        // Sort
        if ($request->has('sort_by')) {
            $direction = $request->get('sort_direction', 'asc');
            $query->orderBy($request->sort_by, $direction);
        }

        return $query;
    }

    protected function getTenantId(Request $request): int
    {
        // Get tenant from authenticated user
        if (Auth::check()) {
            return Auth::user()->tenant_id;
        }
        
        // Fallback to request parameter (for system-level API calls)
        return $request->get('tenant_id');
    }
    
    /**
     * Check API permission and return error response if unauthorized
     */
    protected function checkApiPermission(string $permission): ?JsonResponse
    {
        try {
            $this->checkPermission($permission);
            return null;
        } catch (\Exception $e) {
            return $this->forbidden('Sin permisos para esta acción: ' . $permission);
        }
    }
    
    /**
     * Verify resource belongs to authenticated user's tenant
     */
    protected function verifyTenantAccess(Model $resource): ?JsonResponse
    {
        $tenantId = $this->getTenantId(request());
        
        if (isset($resource->tenant_id) && $resource->tenant_id !== $tenantId) {
            return $this->forbidden('Acceso denegado al recurso');
        }
        
        return null;
    }
    
    /**
     * Log API activity
     */
    protected function logApiActivity(string $action, array $data = []): void
    {
        Log::info('API Activity', [
            'action' => $action,
            'user_id' => Auth::id(),
            'tenant_id' => $this->getTenantId(request()),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'data' => $data
        ]);
    }
    
    /**
     * Handle API exceptions consistently
     */
    protected function handleApiException(\Exception $e): JsonResponse
    {
        Log::error('API Error', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'user_id' => Auth::id(),
            'tenant_id' => $this->getTenantId(request()),
            'trace' => $e->getTraceAsString()
        ]);
        
        // Don't expose sensitive information in production
        if (app()->environment('production')) {
            return $this->serverError('Ha ocurrido un error interno');
        }
        
        return $this->serverError($e->getMessage());
    }
    
    /**
     * Validate rate limit
     */
    protected function checkRateLimit(Request $request, int $maxAttempts = 60, int $decayMinutes = 1): ?JsonResponse
    {
        $key = 'api_rate_limit:' . $request->ip() . ':' . ($request->user()->id ?? 'guest');
        
        // This would integrate with a rate limiting service
        // For now, we'll just return null (no limit)
        return null;
    }
    
    /**
     * Transform model for API response
     */
    protected function transformModel(Model $model, array $includes = []): array
    {
        $data = $model->toArray();
        
        // Load relationships if specified
        if (!empty($includes)) {
            $model->load($includes);
            foreach ($includes as $include) {
                $data[$include] = $model->$include;
            }
        }
        
        // Add computed attributes
        if (method_exists($model, 'getApiAttributes')) {
            $data = array_merge($data, $model->getApiAttributes());
        }
        
        return $data;
    }
}