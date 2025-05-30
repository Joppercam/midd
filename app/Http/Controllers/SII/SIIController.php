<?php

namespace App\Http\Controllers\SII;

use App\Http\Controllers\Controller;
use App\Models\TaxDocument;
use App\Services\SII\DTEService;
use App\Services\SII\SIIAuthService;
use App\Services\SII\SIIService;
use App\Traits\ChecksPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class SIIController extends Controller
{
    use ChecksPermissions;
    private SIIService $siiService;
    private SIIAuthService $siiAuthService;
    private DTEService $dteService;

    public function __construct(SIIService $siiService, SIIAuthService $siiAuthService, DTEService $dteService)
    {
        $this->siiService = $siiService;
        $this->siiAuthService = $siiAuthService;
        $this->dteService = $dteService;
    }

    public function configuration()
    {
        $this->checkPermission('sii.manage');
        $tenant = auth()->user()->tenant;
        
        return Inertia::render('SII/Configuration', [
            'tenant' => $tenant,
            'hasConfiguration' => !empty($tenant->sii_resolution_number),
            'configuration' => [
                'resolution_number' => $tenant->sii_resolution_number,
                'resolution_date' => $tenant->sii_resolution_date,
                'environment' => $tenant->sii_environment ?? 'certification',
            ],
        ]);
    }

    public function updateConfiguration(Request $request)
    {
        $this->checkPermission('sii.manage');
        $validated = $request->validate([
            'resolution_number' => 'required|numeric',
            'resolution_date' => 'required|date',
            'environment' => 'required|in:certification,production',
        ]);

        $tenant = auth()->user()->tenant;
        $tenant->update([
            'sii_resolution_number' => $validated['resolution_number'],
            'sii_resolution_date' => $validated['resolution_date'],
            'sii_environment' => $validated['environment'],
        ]);

        return redirect()->back()->with('success', 'Configuración actualizada correctamente');
    }

    public function sendInvoice(TaxDocument $invoice)
    {
        $this->checkPermission('sii.send');
        // Verify ownership
        if ($invoice->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        // Check if already sent
        if ($invoice->sii_status === 'accepted') {
            return redirect()->back()->with('warning', 'Este documento ya fue aceptado por el SII');
        }

        try {
            // Create DTE if not exists
            if (!$invoice->xml_content) {
                $dteResult = $this->dteService->createDTE($invoice, auth()->user()->tenant);
                if (!$dteResult['success']) {
                    return redirect()->back()->withErrors(['error' => 'Error al generar el DTE']);
                }
            }

            // Authenticate with SII
            $authResult = $this->siiAuthService->authenticate(auth()->user()->tenant);
            
            if (!$authResult['success']) {
                return redirect()->back()->withErrors(['error' => 'Error de autenticación con SII']);
            }

            // Send to SII
            $result = $this->dteService->sendDTE($invoice, $authResult['token']);

            if ($result['success']) {
                return redirect()->back()->with('success', 'Documento enviado exitosamente al SII. Track ID: ' . $result['track_id']);
            } else {
                return redirect()->back()->withErrors(['error' => $result['message']]);
            }

        } catch (\Exception $e) {
            Log::error('Error sending document to SII', [
                'document_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors(['error' => 'Error al enviar el documento al SII']);
        }
    }

    public function sendBatch(Request $request)
    {
        $this->checkPermission('sii.send');
        $validated = $request->validate([
            'document_ids' => 'required|array',
            'document_ids.*' => 'exists:tax_documents,id',
        ]);

        $documents = TaxDocument::whereIn('id', $validated['document_ids'])
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('sii_status', 'pending')
            ->get();

        if ($documents->isEmpty()) {
            return redirect()->back()->withErrors(['error' => 'No hay documentos pendientes para enviar']);
        }

        try {
            $result = $this->siiService->processBatch($documents);

            if ($result['success']) {
                return redirect()->back()->with('success', 
                    sprintf('Lote enviado exitosamente. %d documentos procesados.', $result['processed'])
                );
            } else {
                return redirect()->back()->withErrors(['error' => $result['message']]);
            }
        } catch (\Exception $e) {
            Log::error('Error sending batch to SII', [
                'document_count' => $documents->count(),
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors(['error' => 'Error al enviar el lote al SII']);
        }
    }

    /**
     * Check document status
     */
    public function checkStatus(TaxDocument $document)
    {
        $this->checkPermission('sii.manage');
        // Verify ownership
        if ($document->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        if (!$document->sii_track_id) {
            return redirect()->back()->withErrors(['error' => 'El documento no tiene un Track ID asignado']);
        }

        try {
            // Authenticate with SII
            $authResult = $this->siiAuthService->authenticate(auth()->user()->tenant);
            
            if (!$authResult['success']) {
                return redirect()->back()->withErrors(['error' => 'Error de autenticación con SII']);
            }

            // Check status
            $result = $this->dteService->checkDTEStatus(
                $document->sii_track_id,
                $authResult['token'],
                auth()->user()->tenant
            );

            return redirect()->back()->with('success', 'Estado actualizado correctamente');

        } catch (\Exception $e) {
            Log::error('Error checking document status', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors(['error' => 'Error al consultar el estado del documento']);
        }
    }

    /**
     * Show document SII status
     */
    public function showStatus(TaxDocument $document)
    {
        $this->checkPermission('sii.manage');
        // Verify ownership
        if ($document->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $document->load(['customer', 'siiEventLogs' => function ($query) {
            $query->orderBy('created_at', 'desc')->limit(20);
        }]);

        return Inertia::render('SII/DocumentStatus', [
            'document' => $document->append(['type_label', 'sii_status_label', 'sii_status_color', 'is_sii_pending', 'can_resend_to_sii']),
            'eventLogs' => $document->siiEventLogs->map(function ($log) {
                return $log->append(['formatted_event_type', 'is_error', 'response_time_in_seconds']);
            }),
        ]);
    }

    public function testConnection()
    {
        $this->checkPermission('sii.manage');
        try {
            $result = $this->siiService->testConnection();

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error testing SII connection', [
                'tenant_id' => auth()->user()->tenant_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al probar la conexión con SII',
            ], 500);
        }
    }
    
    public function environmentManagement()
    {
        $this->checkPermission('sii.manage');
        $tenant = auth()->user()->tenant;
        
        return Inertia::render('SII/EnvironmentManagement', [
            'tenant' => $tenant,
            'currentEnvironment' => $tenant->sii_environment ?? 'certification',
            'environments' => [
                'certification' => 'Certificación (Maullin)',
                'production' => 'Producción (Palena)',
            ],
            'canSwitchToProduction' => $tenant->sii_environment === 'certification' && $tenant->sii_certification_completed,
        ]);
    }
    
    public function switchEnvironment(Request $request)
    {
        $this->checkPermission('sii.manage');
        $validated = $request->validate([
            'environment' => 'required|in:certification,production',
        ]);
        
        $tenant = auth()->user()->tenant;
        
        // Validate switch to production
        if ($validated['environment'] === 'production') {
            if ($tenant->sii_environment !== 'certification') {
                return redirect()->back()->withErrors(['error' => 'Debe estar en ambiente de certificación para cambiar a producción']);
            }
            
            if (!$tenant->sii_certification_completed) {
                return redirect()->back()->withErrors(['error' => 'Debe completar la certificación antes de cambiar a producción']);
            }
        }
        
        $tenant->update([
            'sii_environment' => $validated['environment'],
        ]);
        
        $environmentLabel = $validated['environment'] === 'certification' ? 'Certificación' : 'Producción';
        
        return redirect()->back()->with('success', "Ambiente cambiado a {$environmentLabel} exitosamente");
    }
    
    public function validateCertification()
    {
        $this->checkPermission('sii.manage');
        $tenant = auth()->user()->tenant;
        
        try {
            $result = $this->siiService->validateCertificationEnvironment($tenant);
            
            // Mark certification as completed if all tests pass
            if ($result['success'] && $result['ready_for_production']) {
                $tenant->update([
                    'sii_certification_completed' => true,
                    'sii_certification_date' => now(),
                ]);
            }
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            Log::error('Error validating certification', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'errors' => ['Error al validar la certificación: ' . $e->getMessage()],
            ], 500);
        }
    }
    
    public function resetCertification()
    {
        $this->checkPermission('sii.manage');
        $tenant = auth()->user()->tenant;
        
        $tenant->update([
            'sii_certification_completed' => false,
            'sii_certification_date' => null,
            'sii_environment' => 'certification',
        ]);
        
        return redirect()->back()->with('success', 'Certificación reiniciada. Ambiente cambiado a certificación.');
    }
}