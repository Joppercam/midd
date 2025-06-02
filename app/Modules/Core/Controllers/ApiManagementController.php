<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\ApiLog;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ApiManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    public function index()
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return redirect()->route('login');
            }
            
            $tenant = $user->tenant;
            
            if (!$tenant) {
                // Para debugging, crear datos dummy
                $tokens = collect([]);
                $stats = [
                    'total_tokens' => 0,
                    'active_tokens' => 0,
                    'total_requests_30d' => 0,
                    'successful_requests_30d' => 0,
                    'failed_requests_30d' => 0,
                    'avg_response_time_30d' => 0,
                ];
                $recentLogs = collect([]);
            } else {
                $tokens = ApiToken::where('tenant_id', $tenant->id)
                    ->with('user')
                    ->orderBy('created_at', 'desc')
                    ->paginate(20);

                $stats = $this->getApiStats($tenant->id);
                $recentLogs = $this->getRecentApiLogs($tenant->id);
            }

            return Inertia::render('Core/ApiManagement/Index', [
                'tokens' => $tokens,
                'stats' => $stats,
                'recentLogs' => $recentLogs,
            ]);
        } catch (\Exception $e) {
            \Log::error('API Management Error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            // Fallback con datos dummy para debugging
            return Inertia::render('Core/ApiManagement/Index', [
                'tokens' => collect([]),
                'stats' => [
                    'total_tokens' => 0,
                    'active_tokens' => 0,
                    'total_requests_30d' => 0,
                    'successful_requests_30d' => 0,
                    'failed_requests_30d' => 0,
                    'avg_response_time_30d' => 0,
                ],
                'recentLogs' => collect([]),
                'error' => $e->getMessage()
            ]);
        }
    }

    public function createToken(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'abilities' => 'required|array',
            'abilities.*' => 'string|in:read,write,delete,admin',
            'expires_at' => 'nullable|date|after:now',
            'rate_limit' => 'nullable|integer|min:1|max:10000',
            'description' => 'nullable|string|max:500',
        ]);

        $tenant = auth()->user()->tenant;
        $user = auth()->user();

        // Verificar límite de tokens
        $existingTokens = ApiToken::where('tenant_id', $tenant->id)->count();
        if ($existingTokens >= 50) {
            return back()->withErrors(['name' => 'Límite de tokens alcanzado (máximo 50).']);
        }

        $token = Str::random(64);
        
        $apiToken = ApiToken::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'name' => $request->name,
            'token' => hash('sha256', $token),
            'abilities' => $request->abilities,
            'rate_limit' => $request->rate_limit ?? 60,
            'expires_at' => $request->expires_at,
            'metadata' => json_encode(['description' => $request->description]),
            'last_used_at' => null,
            'is_active' => true,
        ]);

        return back()->with([
            'message' => 'Token creado exitosamente.',
            'token' => $token, // Solo se muestra una vez
            'token_id' => $apiToken->id,
        ]);
    }

    public function updateToken(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'abilities' => 'required|array',
            'abilities.*' => 'string|in:read,write,delete,admin',
            'expires_at' => 'nullable|date|after:now',
            'rate_limit' => 'nullable|integer|min:1|max:10000',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        $tenant = auth()->user()->tenant;
        
        $apiToken = ApiToken::where('tenant_id', $tenant->id)
            ->where('id', $id)
            ->firstOrFail();

        $updateData = $request->only([
            'name', 'abilities', 'expires_at', 'rate_limit', 'is_active'
        ]);
        
        if ($request->filled('description')) {
            $metadata = json_decode($apiToken->metadata ?? '{}', true);
            $metadata['description'] = $request->description;
            $updateData['metadata'] = json_encode($metadata);
        }
        
        $apiToken->update($updateData);

        return back()->with('message', 'Token actualizado exitosamente.');
    }

    public function deleteToken($id)
    {
        $tenant = auth()->user()->tenant;
        
        $apiToken = ApiToken::where('tenant_id', $tenant->id)
            ->where('id', $id)
            ->firstOrFail();

        $apiToken->delete();

        return back()->with('message', 'Token eliminado exitosamente.');
    }

    public function regenerateToken($id)
    {
        $tenant = auth()->user()->tenant;
        
        $apiToken = ApiToken::where('tenant_id', $tenant->id)
            ->where('id', $id)
            ->firstOrFail();

        $newToken = Str::random(64);
        $apiToken->update([
            'token' => hash('sha256', $newToken),
            'last_used_at' => null,
        ]);

        return back()->with([
            'message' => 'Token regenerado exitosamente.',
            'token' => $newToken,
            'token_id' => $apiToken->id,
        ]);
    }

    public function getLogs(Request $request)
    {
        $tenant = auth()->user()->tenant;
        
        $query = ApiLog::where('tenant_id', $tenant->id)
            ->with(['apiToken.user']);

        // Filtros
        if ($request->filled('token_id')) {
            $query->where('api_token_id', $request->token_id);
        }

        if ($request->filled('status_code')) {
            $query->where('status_code', $request->status_code);
        }

        if ($request->filled('method')) {
            $query->where('method', $request->method);
        }

        if ($request->filled('endpoint')) {
            $query->where('endpoint', 'like', '%' . $request->endpoint . '%');
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json($logs);
    }

    public function getUsageStats(Request $request)
    {
        $tenant = auth()->user()->tenant;
        
        $days = $request->get('days', 30);
        $dateFrom = Carbon::now()->subDays($days);

        $stats = [
            'total_requests' => ApiLog::where('tenant_id', $tenant->id)
                ->where('created_at', '>=', $dateFrom)
                ->count(),
            
            'successful_requests' => ApiLog::where('tenant_id', $tenant->id)
                ->where('created_at', '>=', $dateFrom)
                ->where('status_code', '<', 400)
                ->count(),
                
            'failed_requests' => ApiLog::where('tenant_id', $tenant->id)
                ->where('created_at', '>=', $dateFrom)
                ->where('status_code', '>=', 400)
                ->count(),
                
            'avg_response_time' => ApiLog::where('tenant_id', $tenant->id)
                ->where('created_at', '>=', $dateFrom)
                ->avg('response_time'),
                
            'top_endpoints' => ApiLog::where('tenant_id', $tenant->id)
                ->where('created_at', '>=', $dateFrom)
                ->select('endpoint', DB::raw('COUNT(*) as count'))
                ->groupBy('endpoint')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get(),
                
            'daily_requests' => ApiLog::where('tenant_id', $tenant->id)
                ->where('created_at', '>=', $dateFrom)
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(CASE WHEN status_code < 400 THEN 1 ELSE 0 END) as successful'),
                    DB::raw('SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as failed')
                )
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
        ];

        return response()->json($stats);
    }

    private function getApiStats($tenantId)
    {
        $last30Days = Carbon::now()->subDays(30);
        
        return [
            'total_tokens' => ApiToken::where('tenant_id', $tenantId)->count(),
            'active_tokens' => ApiToken::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->count(),
            'total_requests_30d' => ApiLog::where('tenant_id', $tenantId)
                ->where('created_at', '>=', $last30Days)
                ->count(),
            'successful_requests_30d' => ApiLog::where('tenant_id', $tenantId)
                ->where('created_at', '>=', $last30Days)
                ->where('status_code', '<', 400)
                ->count(),
            'failed_requests_30d' => ApiLog::where('tenant_id', $tenantId)
                ->where('created_at', '>=', $last30Days)
                ->where('status_code', '>=', 400)
                ->count(),
            'avg_response_time_30d' => ApiLog::where('tenant_id', $tenantId)
                ->where('created_at', '>=', $last30Days)
                ->avg('response_time'),
        ];
    }

    private function getRecentApiLogs($tenantId)
    {
        return ApiLog::where('tenant_id', $tenantId)
            ->with(['apiToken.user'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
    }

    public function settings()
    {
        $tenant = auth()->user()->tenant;
        
        return Inertia::render('Core/ApiManagement/Settings', [
            'settings' => [
                'global_rate_limit' => $tenant->api_rate_limit ?? 1000,
                'webhook_url' => $tenant->webhook_url,
                'webhook_secret' => $tenant->webhook_secret ? '••••••••' : null,
                'enable_webhooks' => $tenant->enable_webhooks ?? false,
                'api_documentation_public' => $tenant->api_documentation_public ?? false,
            ]
        ]);
    }

    public function updateSettings(Request $request)
    {
        $request->validate([
            'global_rate_limit' => 'required|integer|min:100|max:10000',
            'webhook_url' => 'nullable|url',
            'webhook_secret' => 'nullable|string|min:16|max:64',
            'enable_webhooks' => 'boolean',
            'api_documentation_public' => 'boolean',
        ]);

        $tenant = auth()->user()->tenant;
        
        $updateData = $request->only([
            'global_rate_limit', 'webhook_url', 'enable_webhooks', 
            'api_documentation_public'
        ]);

        if ($request->filled('webhook_secret')) {
            $updateData['webhook_secret'] = $request->webhook_secret;
        }

        $tenant->update($updateData);

        return back()->with('message', 'Configuración actualizada exitosamente.');
    }
}