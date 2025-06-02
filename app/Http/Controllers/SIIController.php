<?php

namespace App\Http\Controllers;

use App\Models\TaxDocument;
use App\Models\FolioRange;
use App\Models\CafFile;
use App\Services\SII\DTEService;
use App\Services\SII\FolioManagerService;
use App\Services\SII\ResponseProcessorService;
use App\Services\SII\AdvancedSIIService;
use App\Traits\ChecksPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SIIController extends Controller
{
    use ChecksPermissions;
    
    protected DTEService $dteService;
    protected FolioManagerService $folioService;
    protected ResponseProcessorService $responseProcessor;
    protected AdvancedSIIService $advancedSIIService;

    public function __construct(
        DTEService $dteService,
        FolioManagerService $folioService,
        ResponseProcessorService $responseProcessor,
        AdvancedSIIService $advancedSIIService
    ) {
        $this->middleware('auth');
        
        $this->dteService = $dteService;
        $this->folioService = $folioService;
        $this->responseProcessor = $responseProcessor;
        $this->advancedSIIService = $advancedSIIService;
    }

    /**
     * Show SII configuration page
     */
    public function configuration()
    {
        $this->checkPermission('sii.view');
        
        $tenant = auth()->user()->tenant;
        
        return Inertia::render('SII/Configuration', [
            'tenant' => [
                'name' => $tenant->name,
                'rut' => $tenant->rut,
                'sii_environment' => $tenant->sii_environment,
                'sii_certification_completed' => $tenant->sii_certification_completed,
                'sii_certification_date' => $tenant->sii_certification_date,
                'certificate_uploaded_at' => $tenant->certificate_uploaded_at,
                'authorized_sender_rut' => $tenant->authorized_sender_rut,
                'sii_resolution_date' => $tenant->sii_resolution_date,
                'sii_resolution_number' => $tenant->sii_resolution_number,
            ],
            'hasCertificate' => $tenant->certificate_uploaded_at !== null,
            'hasConfiguration' => $tenant->sii_resolution_number !== null && $tenant->sii_resolution_date !== null,
            'configuration' => [
                'resolution_number' => $tenant->sii_resolution_number,
                'resolution_date' => $tenant->sii_resolution_date,
                'environment' => $tenant->sii_environment ?? 'certification',
            ],
        ]);
    }

    /**
     * Upload SII certificate
     */
    public function uploadCertificate(Request $request)
    {
        $request->validate([
            'certificate' => 'required|file|mimes:p12,pfx|max:2048',
            'password' => 'required|string',
            'authorized_sender_rut' => 'required|string',
            'resolution_date' => 'required|date',
            'resolution_number' => 'required|integer',
        ]);

        $tenant = auth()->user()->tenant;
        
        try {
            // Store certificate securely
            $path = $request->file('certificate')->store(
                'certificates/' . $tenant->id,
                'private'
            );

            // Update tenant with certificate info
            $tenant->update([
                'certificate_password' => encrypt($request->password),
                'certificate_uploaded_at' => now(),
                'authorized_sender_rut' => $request->authorized_sender_rut,
                'sii_resolution_date' => $request->resolution_date,
                'sii_resolution_number' => $request->resolution_number,
            ]);

            Log::info('SII certificate uploaded', [
                'tenant_id' => $tenant->id,
                'path' => $path,
            ]);

            return redirect()->route('sii.configuration')
                ->with('success', 'Certificado cargado exitosamente.');
                
        } catch (\Exception $e) {
            Log::error('Error uploading SII certificate', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
            
            return back()->withErrors(['certificate' => 'Error al cargar el certificado.']);
        }
    }

    /**
     * Send document to SII
     */
    public function sendDocument(TaxDocument $document)
    {
        $this->authorize('update', $document);

        if (!in_array($document->status, ['draft', 'rejected', 'error'])) {
            return back()->withErrors(['document' => 'El documento no puede ser enviado en su estado actual.']);
        }

        try {
            // Send to SII
            $result = $this->dteService->sendToSII($document);

            if ($result['success']) {
                return redirect()->route('tax-documents.show', $document)
                    ->with('success', 'Documento enviado al SII exitosamente.');
            } else {
                return back()->withErrors(['sii' => $result['message']]);
            }
            
        } catch (\Exception $e) {
            Log::error('Error sending document to SII', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);
            
            return back()->withErrors(['sii' => 'Error al enviar el documento al SII.']);
        }
    }

    /**
     * Check document status in SII
     */
    public function checkStatus(TaxDocument $document)
    {
        $this->authorize('view', $document);

        if (!$document->sii_track_id) {
            return back()->withErrors(['document' => 'El documento no tiene track ID del SII.']);
        }

        try {
            $status = $this->advancedSIIService->checkDTEStatus(
                $document->sii_track_id,
                $document->rut,
                $document->document_type,
                $document->folio
            );

            // Process the response
            $this->responseProcessor->processStatusResponse($document, $status);

            return redirect()->route('tax-documents.show', $document)
                ->with('success', 'Estado actualizado desde el SII.');
                
        } catch (\Exception $e) {
            Log::error('Error checking SII status', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);
            
            return back()->withErrors(['sii' => 'Error al consultar el estado en el SII.']);
        }
    }

    /**
     * Folio management page
     */
    public function folios()
    {
        $folioRanges = FolioRange::with('cafFile')
            ->where('tenant_id', auth()->user()->tenant_id)
            ->orderBy('document_type')
            ->orderBy('start_number')
            ->get();

        return Inertia::render('SII/Folios', [
            'folioRanges' => $folioRanges,
            'documentTypes' => TaxDocument::SII_DOCUMENT_TYPES,
        ]);
    }

    /**
     * Upload CAF file
     */
    public function uploadCAF(Request $request)
    {
        $request->validate([
            'caf_file' => 'required|file|mimes:xml|max:1024',
            'document_type' => 'required|integer|in:33,34,39,56,61',
        ]);

        try {
            $tenant = auth()->user()->tenant;
            $xml = file_get_contents($request->file('caf_file')->getRealPath());
            
            // Process CAF file
            $cafData = $this->folioService->processCAF($xml, $request->document_type);
            
            if (!$cafData['success']) {
                return back()->withErrors(['caf_file' => $cafData['message']]);
            }

            // Store CAF file
            $path = $request->file('caf_file')->store(
                'caf/' . $tenant->id,
                'private'
            );

            // Create CAF record
            $caf = CafFile::create([
                'tenant_id' => $tenant->id,
                'document_type' => $request->document_type,
                'start_number' => $cafData['start'],
                'end_number' => $cafData['end'],
                'private_key' => $cafData['private_key'],
                'public_key' => $cafData['public_key'],
                'xml_content' => $xml,
                'file_path' => $path,
                'uploaded_at' => now(),
            ]);

            // Create or update folio range
            $folioRange = FolioRange::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'document_type' => $request->document_type,
                    'start_number' => $cafData['start'],
                    'end_number' => $cafData['end'],
                ],
                [
                    'caf_file_id' => $caf->id,
                    'current_number' => $cafData['start'] - 1,
                    'is_active' => true,
                    'alert_threshold' => 10,
                ]
            );

            Log::info('CAF file uploaded', [
                'tenant_id' => $tenant->id,
                'document_type' => $request->document_type,
                'range' => $cafData['start'] . '-' . $cafData['end'],
            ]);

            return redirect()->route('sii.folios')
                ->with('success', 'Archivo CAF cargado exitosamente.');
                
        } catch (\Exception $e) {
            Log::error('Error uploading CAF file', [
                'error' => $e->getMessage(),
            ]);
            
            return back()->withErrors(['caf_file' => 'Error al procesar el archivo CAF.']);
        }
    }

    /**
     * Toggle SII environment (certification/production)
     */
    public function toggleEnvironment(Request $request)
    {
        $request->validate([
            'environment' => 'required|in:certification,production',
        ]);

        $tenant = auth()->user()->tenant;
        
        // Check if can switch to production
        if ($request->environment === 'production' && !$tenant->sii_certification_completed) {
            return back()->withErrors(['environment' => 'Debe completar la certificación antes de pasar a producción.']);
        }

        $tenant->update(['sii_environment' => $request->environment]);

        Log::info('SII environment changed', [
            'tenant_id' => $tenant->id,
            'environment' => $request->environment,
        ]);

        return redirect()->route('sii.configuration')
            ->with('success', 'Ambiente SII actualizado a ' . $request->environment . '.');
    }

    /**
     * Mark certification as complete
     */
    public function completeCertification()
    {
        $tenant = auth()->user()->tenant;
        
        if ($tenant->sii_certification_completed) {
            return back()->with('info', 'La certificación ya está completada.');
        }

        $tenant->update([
            'sii_certification_completed' => true,
            'sii_certification_date' => now(),
        ]);

        Log::info('SII certification completed', ['tenant_id' => $tenant->id]);

        return redirect()->route('sii.configuration')
            ->with('success', 'Certificación SII completada exitosamente.');
    }

    /**
     * Download DTE XML
     */
    public function downloadXML(TaxDocument $document)
    {
        $this->authorize('view', $document);

        if (!$document->xml_content) {
            return back()->withErrors(['document' => 'El documento no tiene XML generado.']);
        }

        $filename = sprintf(
            'DTE_%s_%s_%s.xml',
            $document->document_type,
            $document->folio,
            $document->issue_date->format('Ymd')
        );

        return response($document->xml_content, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * View DTE as PDF with TED
     */
    public function viewPDF(TaxDocument $document)
    {
        $this->authorize('view', $document);

        // TODO: Implement PDF generation with TED (Timbre Electrónico)
        // For now, redirect to standard invoice PDF
        return redirect()->route('tax-documents.pdf', $document);
    }
}