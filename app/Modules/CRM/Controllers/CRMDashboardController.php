<?php

namespace App\Modules\CRM\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Models\Activity;
use App\Modules\CRM\Models\Contact;
use App\Modules\CRM\Models\Opportunity;
use App\Modules\CRM\Models\Pipeline;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CRMDashboardController extends Controller
{
    public function index(Request $request)
    {
        $tenant = auth()->user()->tenant;
        $user = auth()->user();
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        // Métricas generales
        $metrics = [
            'total_contacts' => Contact::where('tenant_id', $tenant->id)->count(),
            'new_leads_month' => Contact::where('tenant_id', $tenant->id)
                ->where('contact_type', 'lead')
                ->whereMonth('created_at', $currentMonth)
                ->whereYear('created_at', $currentYear)
                ->count(),
            'active_opportunities' => Opportunity::where('tenant_id', $tenant->id)
                ->where('status', 'open')
                ->count(),
            'total_pipeline_value' => Opportunity::where('tenant_id', $tenant->id)
                ->where('status', 'open')
                ->sum('amount'),
        ];

        // Oportunidades por etapa
        $defaultPipeline = Pipeline::where('tenant_id', $tenant->id)
            ->where('is_default', true)
            ->with('stages')
            ->first();

        $opportunitiesByStage = [];
        if ($defaultPipeline) {
            $opportunitiesByStage = $defaultPipeline->stages->map(function ($stage) use ($tenant) {
                return [
                    'id' => $stage->id,
                    'name' => $stage->name,
                    'color' => $stage->color,
                    'count' => Opportunity::where('tenant_id', $tenant->id)
                        ->where('stage_id', $stage->id)
                        ->where('status', 'open')
                        ->count(),
                    'value' => Opportunity::where('tenant_id', $tenant->id)
                        ->where('stage_id', $stage->id)
                        ->where('status', 'open')
                        ->sum('amount'),
                ];
            });
        }

        // Actividades pendientes del usuario
        $pendingActivities = Activity::where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->with(['related'])
            ->orderBy('due_date')
            ->take(10)
            ->get();

        // Oportunidades próximas a cerrar
        $closingSoon = Opportunity::where('tenant_id', $tenant->id)
            ->where('status', 'open')
            ->whereNotNull('expected_close_date')
            ->whereBetween('expected_close_date', [now(), now()->addDays(30)])
            ->with(['contact', 'company', 'stage'])
            ->orderBy('expected_close_date')
            ->take(5)
            ->get();

        // Rendimiento del equipo (últimos 30 días)
        $teamPerformance = DB::table('crm_opportunities')
            ->join('users', 'crm_opportunities.owner_id', '=', 'users.id')
            ->where('crm_opportunities.tenant_id', $tenant->id)
            ->where('crm_opportunities.created_at', '>=', now()->subDays(30))
            ->groupBy('users.id', 'users.name')
            ->select([
                'users.id',
                'users.name',
                DB::raw('COUNT(CASE WHEN status = "won" THEN 1 END) as won_count'),
                DB::raw('COUNT(CASE WHEN status = "lost" THEN 1 END) as lost_count'),
                DB::raw('COUNT(CASE WHEN status = "open" THEN 1 END) as open_count'),
                DB::raw('SUM(CASE WHEN status = "won" THEN amount ELSE 0 END) as won_value'),
            ])
            ->get();

        // Fuentes de leads
        $leadSources = Contact::where('tenant_id', $tenant->id)
            ->where('contact_type', 'lead')
            ->whereNotNull('source')
            ->groupBy('source')
            ->select('source', DB::raw('count(*) as count'))
            ->orderByDesc('count')
            ->take(5)
            ->get();

        // Tasa de conversión mensual
        $conversionRate = $this->calculateConversionRate($tenant->id, $currentMonth, $currentYear);

        // Pronóstico de ventas (próximos 3 meses)
        $forecast = $this->calculateSalesForecast($tenant->id);

        // Últimas actividades
        $recentActivities = Activity::where('tenant_id', $tenant->id)
            ->where('status', 'completed')
            ->with(['user', 'related'])
            ->orderBy('completed_at', 'desc')
            ->take(5)
            ->get();

        return Inertia::render('CRM/Dashboard', [
            'metrics' => $metrics,
            'opportunitiesByStage' => $opportunitiesByStage,
            'pendingActivities' => $pendingActivities,
            'closingSoon' => $closingSoon,
            'teamPerformance' => $teamPerformance,
            'leadSources' => $leadSources,
            'conversionRate' => $conversionRate,
            'forecast' => $forecast,
            'recentActivities' => $recentActivities,
            'defaultPipeline' => $defaultPipeline,
        ]);
    }

    public function search(Request $request)
    {
        $query = $request->get('q');
        $tenant = auth()->user()->tenant;

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $results = [];

        // Buscar contactos
        $contacts = Contact::where('tenant_id', $tenant->id)
            ->where(function ($q) use ($query) {
                $q->where('first_name', 'like', "%{$query}%")
                    ->orWhere('last_name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%");
            })
            ->take(5)
            ->get()
            ->map(function ($contact) {
                return [
                    'type' => 'contact',
                    'id' => $contact->id,
                    'title' => $contact->full_name,
                    'subtitle' => $contact->email,
                    'url' => route('crm.contacts.show', $contact),
                ];
            });

        $results = array_merge($results, $contacts->toArray());

        // Buscar empresas
        $companies = \App\Modules\CRM\Models\Company::where('tenant_id', $tenant->id)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('rut', 'like', "%{$query}%");
            })
            ->take(5)
            ->get()
            ->map(function ($company) {
                return [
                    'type' => 'company',
                    'id' => $company->id,
                    'title' => $company->name,
                    'subtitle' => $company->industry,
                    'url' => route('crm.companies.show', $company),
                ];
            });

        $results = array_merge($results, $companies->toArray());

        // Buscar oportunidades
        $opportunities = Opportunity::where('tenant_id', $tenant->id)
            ->where('name', 'like', "%{$query}%")
            ->with('contact')
            ->take(5)
            ->get()
            ->map(function ($opp) {
                return [
                    'type' => 'opportunity',
                    'id' => $opp->id,
                    'title' => $opp->name,
                    'subtitle' => $opp->contact->full_name ?? 'Sin contacto',
                    'url' => route('crm.opportunities.show', $opp),
                ];
            });

        $results = array_merge($results, $opportunities->toArray());

        return response()->json($results);
    }

    private function calculateConversionRate($tenantId, $month, $year)
    {
        $totalLeads = Contact::where('tenant_id', $tenantId)
            ->where('contact_type', 'lead')
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->count();

        $convertedLeads = Contact::where('tenant_id', $tenantId)
            ->where('contact_type', 'customer')
            ->whereMonth('updated_at', $month)
            ->whereYear('updated_at', $year)
            ->count();

        if ($totalLeads === 0) return 0;

        return round(($convertedLeads / $totalLeads) * 100, 2);
    }

    private function calculateSalesForecast($tenantId)
    {
        $forecast = [];
        
        for ($i = 0; $i < 3; $i++) {
            $date = Carbon::now()->addMonths($i);
            $month = $date->month;
            $year = $date->year;
            
            // Oportunidades que se esperan cerrar en este mes
            $expectedValue = Opportunity::where('tenant_id', $tenantId)
                ->where('status', 'open')
                ->whereMonth('expected_close_date', $month)
                ->whereYear('expected_close_date', $year)
                ->get()
                ->sum('weighted_value');
            
            $forecast[] = [
                'month' => $date->format('F Y'),
                'expected' => $expectedValue,
                'best_case' => $expectedValue * 1.2, // 20% más optimista
                'worst_case' => $expectedValue * 0.8, // 20% más pesimista
            ];
        }
        
        return $forecast;
    }
}