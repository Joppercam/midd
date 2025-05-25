<?php

namespace App\Http\Controllers\SII;

use App\Http\Controllers\Controller;
use App\Models\TaxDocument;
use App\Services\SII\SIIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class SIIController extends Controller
{
    protected $siiService;

    public function __construct(SIIService $siiService)
    {
        $this->siiService = $siiService;
    }

    public function configuration()
    {
        $tenant = auth()->user()->tenant;
        
        return Inertia::render('SII/Configuration', [
            'tenant' => $tenant->only(['id', 'name', 'rut']),
            'hasConfiguration' => $tenant->sii_configuration()->exists(),
            'configuration' => $tenant->sii_configuration,
        ]);
    }

    public function updateConfiguration(Request $request)
    {
        $validated = $request->validate([
            'resolution_number' => 'required|integer',
            'resolution_date' => 'required|date',
            'environment' => 'required|in:certification,production',
        ]);

        $tenant = auth()->user()->tenant;
        
        $tenant->sii_configuration()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            $validated
        );

        return redirect()->back()->with('success', 'Configuración SII actualizada exitosamente');
    }

    public function uploadCertificate(Request $request)
    {
        $request->validate([
            'certificate' => 'required|file|mimes:p12,pfx|max:2048',
            'password' => 'required|string',
        ]);

        $tenant = auth()->user()->tenant;

        try {
            $result = $this->siiService->uploadCertificate(
                $request->file('certificate'),
                $request->password
            );

            if (!$result['success']) {
                return redirect()->back()->withErrors(['certificate' => $result['message']]);
            }

            return redirect()->back()->with('success', 'Certificado digital cargado exitosamente');
        } catch (\Exception $e) {
            Log::error('Error uploading certificate', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors(['certificate' => 'Error al cargar el certificado']);
        }
    }

    public function send(TaxDocument $taxDocument)
    {
        $this->authorize('update', $taxDocument);

        if ($taxDocument->sii_status !== 'pending') {
            return redirect()->back()->withErrors(['error' => 'El documento ya fue enviado al SII']);
        }

        try {
            $result = $this->siiService->processInvoice($taxDocument);

            if ($result['success']) {
                return redirect()->back()->with('success', 'Documento enviado exitosamente al SII');
            } else {
                return redirect()->back()->withErrors(['error' => $result['message']]);
            }
        } catch (\Exception $e) {
            Log::error('Error sending document to SII', [
                'document_id' => $taxDocument->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors(['error' => 'Error al enviar el documento al SII']);
        }
    }

    public function checkStatus(TaxDocument $taxDocument)
    {
        $this->authorize('view', $taxDocument);

        if (!$taxDocument->sii_track_id) {
            return response()->json([
                'success' => false,
                'message' => 'El documento no tiene un ID de seguimiento SII',
            ], 400);
        }

        try {
            $result = $this->siiService->checkStatus($taxDocument->sii_track_id);

            if ($result['success']) {
                $taxDocument->update([
                    'sii_status' => $result['status'],
                    'sii_response' => $result['response'],
                ]);

                return response()->json([
                    'success' => true,
                    'status' => $result['status'],
                    'response' => $result['response'],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Error checking SII status', [
                'document_id' => $taxDocument->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al consultar el estado en SII',
            ], 500);
        }
    }

    public function batchSend(Request $request)
    {
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

    public function testConnection()
    {
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
}