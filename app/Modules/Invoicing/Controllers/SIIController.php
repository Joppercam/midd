<?php

namespace App\Modules\Invoicing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TaxDocument;
use App\Models\SiiEventLog;
use App\Services\SII\DTEService;
use App\Services\SII\SIIAuthService;
use App\Services\SII\SIIService;
use App\Services\SII\AdvancedSIIService;
use App\Traits\ChecksPermissions;
use App\Modules\Invoicing\Services\SIIIntegrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Carbon\Carbon;

class SIIController extends Controller
{
    use ChecksPermissions;

    protected $siiService;
    protected $siiAuthService;
    protected $dteService;
    protected $advancedSIIService;
    protected $siiIntegrationService;

    public function __construct(
        SIIService $siiService,
        SIIAuthService $siiAuthService,
        DTEService $dteService,
        AdvancedSIIService $advancedSIIService,
        SIIIntegrationService $siiIntegrationService
    ) {
        $this->middleware(['auth', 'verified', 'check.subscription', 'check.module:invoicing']);
        $this->siiService = $siiService;
        $this->siiAuthService = $siiAuthService;
        $this->dteService = $dteService;
        $this->advancedSIIService = $advancedSIIService;
        $this->siiIntegrationService = $siiIntegrationService;
    }

    public function configuration()
    {
        $this->checkPermission('sii.configuration');
        
        $tenant = auth()->user()->tenant;
        $config = $this->siiIntegrationService->getConfiguration($tenant);
        
        return Inertia::render('Invoicing/SII/Configuration', [
            'tenant' => $tenant,
            'configuration' => $config,
            'environments' => config('invoicing.sii.environments'),
        ]);
    }

    public function updateConfiguration(Request $request)
    {
        $this->checkPermission('sii.configuration');
        
        $validated = $request->validate([
            'resolution_number' => 'required|numeric',
            'resolution_date' => 'required|date',
            'environment' => 'required|in:certification,production',
            'auto_send' => 'boolean',
            'auto_retry' => 'boolean',
            'retry_attempts' => 'integer|min:1|max:10',
        ]);

        try {
            $this->siiIntegrationService->updateConfiguration(
                auth()->user()->tenant,
                $validated
            );

            return redirect()->back()->with('success', 'Configuración actualizada correctamente.');

        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function testConnection(Request $request)
    {
        $this->checkPermission('sii.configuration');
        
        try {
            $result = $this->siiIntegrationService->testConnection(auth()->user()->tenant);
            
            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result['data'] ?? null,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al probar la conexión: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function environments()
    {
        $this->checkPermission('sii.environment_management');
        
        $tenant = auth()->user()->tenant;
        $currentEnv = $tenant->sii_environment ?? 'certification';
        
        return Inertia::render('Invoicing/SII/Environments', [
            'current_environment' => $currentEnv,
            'environments' => config('invoicing.sii.environments'),
            'can_switch_to_production' => $this->siiIntegrationService->canSwitchToProduction($tenant),
        ]);
    }

    public function switchEnvironment(Request $request)
    {
        $this->checkPermission('sii.environment_management');
        
        $validated = $request->validate([
            'environment' => 'required|in:certification,production',
        ]);

        try {
            $result = $this->siiIntegrationService->switchEnvironment(
                auth()->user()->tenant,
                $validated['environment']
            );

            if ($result['success']) {
                return redirect()->back()->with('success', $result['message']);
            } else {
                return redirect()->back()->withErrors(['error' => $result['message']]);
            }

        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function documents(Request $request)
    {
        $this->checkPermission('sii.send_documents');
        
        $filters = $request->only([
            'search', 'type', 'sii_status', 'date_from', 'date_to'
        ]);

        $documents = $this->siiIntegrationService->getDocuments($filters);
        
        return Inertia::render('Invoicing/SII/Documents', [
            'documents' => $documents,
            'filters' => $filters,
            'document_types' => config('invoicing.document_types'),
        ]);
    }

    public function getDocumentStatus(TaxDocument $document)
    {
        $this->checkPermission('sii.query_status');
        $this->authorize('view', $document);
        
        try {
            $status = $this->siiIntegrationService->queryDocumentStatus($document);
            
            return response()->json([
                'success' => true,
                'status' => $status,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function bulkSend(Request $request)
    {
        $this->checkPermission('sii.send_documents');
        
        $validated = $request->validate([
            'document_ids' => 'required|array|min:1|max:50',
            'document_ids.*' => 'exists:tax_documents,id',
        ]);

        try {
            $result = $this->siiIntegrationService->bulkSendDocuments(
                $validated['document_ids'],
                auth()->user()->tenant_id
            );

            return response()->json([
                'success' => true,
                'message' => "Enviados {$result['sent']} documentos. {$result['errors']} errores.",
                'details' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function bulkStatus(Request $request)
    {
        $this->checkPermission('sii.query_status');
        
        $validated = $request->validate([
            'document_ids' => 'required|array|min:1|max:100',
            'document_ids.*' => 'exists:tax_documents,id',
        ]);

        try {
            $result = $this->siiIntegrationService->bulkQueryStatus(
                $validated['document_ids'],
                auth()->user()->tenant_id
            );

            return response()->json([
                'success' => true,
                'results' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function folios()
    {
        $this->checkPermission('sii.folio_management');
        
        $tenant = auth()->user()->tenant;
        $folios = $this->siiIntegrationService->getFolioStatus($tenant);
        
        return Inertia::render('Invoicing/SII/Folios', [
            'folios' => $folios,
            'document_types' => config('invoicing.document_types'),
        ]);
    }

    public function requestFolios(Request $request)
    {
        $this->checkPermission('sii.folio_management');
        
        $validated = $request->validate([
            'document_type' => 'required|in:' . implode(',', array_keys(config('invoicing.document_types'))),
            'quantity' => 'required|integer|min:1|max:10000',
        ]);

        try {
            $result = $this->siiIntegrationService->requestFolios(
                auth()->user()->tenant,
                $validated['document_type'],
                $validated['quantity']
            );

            if ($result['success']) {
                return redirect()->back()->with('success', $result['message']);
            } else {
                return redirect()->back()->withErrors(['error' => $result['message']]);
            }

        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function downloadFolios(string $type)
    {
        $this->checkPermission('sii.folio_management');
        
        try {
            $download = $this->siiIntegrationService->downloadFolios(
                auth()->user()->tenant,
                $type
            );

            return $download;

        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function logs(Request $request)
    {
        $this->checkPermission('sii.configuration');
        
        $filters = $request->only([
            'level', 'event_type', 'date_from', 'date_to', 'search'
        ]);

        $logs = SiiEventLog::where('tenant_id', auth()->user()->tenant_id)
            ->when($filters['level'] ?? null, function ($query, $level) {
                $query->where('level', $level);
            })
            ->when($filters['event_type'] ?? null, function ($query, $type) {
                $query->where('event_type', $type);
            })
            ->when($filters['date_from'] ?? null, function ($query, $date) {
                $query->whereDate('created_at', '>=', $date);
            })
            ->when($filters['date_to'] ?? null, function ($query, $date) {
                $query->whereDate('created_at', '<=', $date);
            })
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('message', 'like', "%{$search}%")
                      ->orWhere('context', 'like', "%{$search}%");
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Invoicing/SII/Logs', [
            'logs' => $logs,
            'filters' => $filters,
        ]);
    }

    public function stats()
    {
        $this->checkPermission('sii.configuration');
        
        $stats = $this->siiIntegrationService->getStatistics(auth()->user()->tenant_id);
        
        return response()->json($stats);
    }

    public function summaryReport(Request $request)
    {
        $this->checkPermission('sii.configuration');
        
        $validated = $request->validate([
            'period' => 'required|in:week,month,quarter,year',
            'document_type' => 'nullable|in:' . implode(',', array_keys(config('invoicing.document_types'))),
        ]);

        try {
            $report = $this->siiIntegrationService->generateSummaryReport(
                auth()->user()->tenant_id,
                $validated['period'],
                $validated['document_type'] ?? null
            );

            return response()->json([
                'success' => true,
                'report' => $report,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}