<?php

namespace App\Services\SII;

use App\Models\Tenant;
use App\Models\TaxDocument;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
class DTEService
{
    private XMLGeneratorService $xmlGenerator;
    private XMLSignerService $xmlSigner;
    private XSDValidatorService $xsdValidator;
    private ResponseProcessorService $responseProcessor;
    private FolioManagerService $folioManager;
    private array $endpoints;
    private string $environment;
    
    public function __construct(
        XMLGeneratorService $xmlGenerator, 
        XMLSignerService $xmlSigner,
        XSDValidatorService $xsdValidator,
        ResponseProcessorService $responseProcessor,
        FolioManagerService $folioManager
    ) {
        $this->xmlGenerator = $xmlGenerator;
        $this->xmlSigner = $xmlSigner;
        $this->xsdValidator = $xsdValidator;
        $this->responseProcessor = $responseProcessor;
        $this->folioManager = $folioManager;
        $this->endpoints = config('sii.endpoints');
        $this->environment = config('sii.environment', 'certification');
    }

    /**
     * Create DTE from tax document
     *
     * @param TaxDocument $document
     * @param Tenant $tenant
     * @return array
     * @throws Exception
     */
    public function createDTE(TaxDocument $document, Tenant $tenant): array
    {
        try {
            DB::beginTransaction();

            // Assign folio if not already assigned
            if (!$document->folio) {
                $folio = $this->folioManager->assignFolioToDocument($document, $tenant);
            } else {
                $folio = $document->folio;
            }

            // Prepare DTE data
            $dteData = $this->prepareDTEData($document, $tenant);
            
            // Generate XML
            $xml = $this->xmlGenerator->generateDTE($dteData);
            
            // Sign XML
            $signedXml = $this->signXML($xml, $tenant);
            
            // Validate XML
            $this->validateDTE($signedXml);
            
            // Store DTE information
            $document->update([
                'xml_content' => $signedXml,
                'sii_status' => 'generated',
                'dte_generated_at' => Carbon::now(),
            ]);

            DB::commit();

            return [
                'success' => true,
                'xml' => $signedXml,
                'folio' => $dteData['folio'],
                'tipo_dte' => $dteData['tipo_dte'],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error creating DTE', [
                'document_id' => $document->id,
                'error' => $e->getMessage()
            ]);
            throw new Exception('Failed to create DTE: ' . $e->getMessage());
        }
    }

    /**
     * Send DTE to SII
     *
     * @param TaxDocument $document
     * @param string $token
     * @return array
     * @throws Exception
     */
    public function sendDTE(TaxDocument $document, string $token): array
    {
        try {
            if (!$document->xml_content) {
                throw new Exception('DTE XML not found for document');
            }

            // Create EnvioDTE
            $envioDTE = $this->createEnvioDTE([$document]);
            
            // Send to SII
            $startTime = microtime(true);
            $response = $this->sendToSII($envioDTE, $token, $document->tenant);
            $responseTime = (microtime(true) - $startTime) * 1000;
            
            // Process the response
            $processedResponse = $this->responseProcessor->processUploadResponse($response, $document);
            
            // Log the event
            $this->logSiiEvent($document, 'upload', [
                'response' => $response,
                'response_time' => $responseTime,
                'track_id' => $processedResponse['track_id'] ?? null,
            ]);

            return $processedResponse;
        } catch (Exception $e) {
            Log::error('Error sending DTE', [
                'document_id' => $document->id,
                'error' => $e->getMessage()
            ]);
            throw new Exception('Failed to send DTE: ' . $e->getMessage());
        }
    }

    /**
     * Check DTE status
     *
     * @param string $trackingId
     * @param string $token
     * @param Tenant $tenant
     * @return array
     * @throws Exception
     */
    public function checkDTEStatus(string $trackingId, string $token, Tenant $tenant): array
    {
        try {
            $startTime = microtime(true);
            
            $response = Http::withHeaders([
                'Cookie' => 'TOKEN=' . $token,
            ])->asForm()->post($this->getEndpointUrl('dte_status', $tenant), [
                'TRACKID' => $trackingId,
                'RUT_EMISOR' => $tenant->rut,
            ]);

            $responseTime = (microtime(true) - $startTime) * 1000;

            if (!$response->successful()) {
                throw new Exception('Failed to check DTE status: ' . $response->status());
            }

            $xmlResponse = $response->body();
            
            // Process the status response
            $processedResponse = $this->responseProcessor->processStatusResponse($xmlResponse, $trackingId);
            
            // Log the event
            $this->logSiiEvent(null, 'status_check', [
                'track_id' => $trackingId,
                'response' => $processedResponse,
                'response_time' => $responseTime,
            ], $tenant);

            return $processedResponse;
        } catch (Exception $e) {
            Log::error('Error checking DTE status', [
                'tracking_id' => $trackingId,
                'error' => $e->getMessage()
            ]);
            
            // Log error event
            $this->logSiiEvent(null, 'error', [
                'track_id' => $trackingId,
                'error' => $e->getMessage(),
            ], $tenant);
            
            throw new Exception('Failed to check DTE status: ' . $e->getMessage());
        }
    }

    /**
     * Prepare DTE data from tax document
     *
     * @param TaxDocument $document
     * @param Tenant $tenant
     * @return array
     */
    private function prepareDTEData(TaxDocument $document, Tenant $tenant): array
    {
        $tipoDTE = $this->getTipoDTE($document->type);
        
        $data = [
            'tipo_dte' => $tipoDTE,
            'folio' => $document->number,
            'fecha_emision' => $document->issue_date->format('Y-m-d'),
            'fecha_vencimiento' => $document->due_date->format('Y-m-d'),
            'forma_pago' => $document->payment_method === 'cash' ? 1 : 2,
            'emisor' => [
                'rut' => $tenant->rut,
                'razon_social' => $tenant->name,
                'giro' => $tenant->business_activity,
                'actividad_economica' => $tenant->economic_activity_code,
                'direccion' => $tenant->address,
                'comuna' => $tenant->commune,
                'codigo_sucursal' => $tenant->branch_code ?? '',
            ],
            'receptor' => [
                'rut' => $document->customer->rut,
                'razon_social' => $document->customer->name,
                'giro' => $document->customer->business_activity ?? '',
                'direccion' => $document->customer->address,
                'comuna' => $document->customer->commune,
            ],
            'detalle' => [],
            'monto_neto' => 0,
            'monto_exento' => 0,
            'iva' => 0,
            'monto_total' => 0,
        ];

        // Add document items
        foreach ($document->items as $index => $item) {
            $montoItem = $item->quantity * $item->unit_price;
            
            $data['detalle'][] = [
                'numero_linea' => $index + 1,
                'nombre_item' => $item->name,
                'descripcion' => $item->description,
                'cantidad' => $item->quantity,
                'unidad_medida' => $item->unit ?? 'UN',
                'precio_unitario' => $item->unit_price,
                'monto_item' => $montoItem,
            ];

            if ($item->tax_exempt) {
                $data['monto_exento'] += $montoItem;
            } else {
                $data['monto_neto'] += $montoItem;
            }
        }

        // Calculate totals
        $data['iva'] = round($data['monto_neto'] * 0.19);
        $data['monto_total'] = $data['monto_neto'] + $data['monto_exento'] + $data['iva'];

        return $data;
    }

    /**
     * Get tipo DTE code
     *
     * @param string $documentType
     * @return int
     */
    private function getTipoDTE(string $documentType): int
    {
        $types = [
            'invoice' => 33,        // Factura Electrónica
            'invoice_exempt' => 34, // Factura No Afecta o Exenta Electrónica
            'purchase' => 46,       // Factura de Compra Electrónica
            'debit_note' => 56,     // Nota de Débito Electrónica
            'credit_note' => 61,    // Nota de Crédito Electrónica
            'receipt' => 39,        // Boleta Electrónica
            'receipt_exempt' => 41, // Boleta Exenta Electrónica
        ];

        return $types[$documentType] ?? 33;
    }

    /**
     * Sign XML document
     *
     * @param string $xml
     * @param Tenant $tenant
     * @return string
     * @throws Exception
     */
    private function signXML(string $xml, Tenant $tenant): string
    {
        // Set tenant-specific certificate paths
        $certificatePath = storage_path('app/sii/certificates/' . $tenant->id . '/certificate.pem');
        $privateKeyPath = storage_path('app/sii/certificates/' . $tenant->id . '/private_key.pem');
        
        // Temporarily update the signer with tenant-specific paths
        $reflectionClass = new \ReflectionClass($this->xmlSigner);
        $certProperty = $reflectionClass->getProperty('certificatePath');
        $certProperty->setAccessible(true);
        $certProperty->setValue($this->xmlSigner, $certificatePath);
        
        $keyProperty = $reflectionClass->getProperty('privateKeyPath');
        $keyProperty->setAccessible(true);
        $keyProperty->setValue($this->xmlSigner, $privateKeyPath);
        
        // Use the XMLSignerService to sign the document
        return $this->xmlSigner->signXML($xml);
    }

    /**
     * Validate DTE
     *
     * @param string $xml
     * @throws Exception
     */
    private function validateDTE(string $xml): void
    {
        // Basic XML validation
        $doc = new \DOMDocument();
        if (!$doc->loadXML($xml)) {
            throw new Exception('Invalid XML document');
        }

        // XSD validation if schemas are available
        if ($this->xsdValidator->schemasExist()) {
            $validation = $this->xsdValidator->validateDTE($xml);
            if (!$validation['valid']) {
                $errorMessages = [];
                foreach ($validation['errors'] as $error) {
                    $errorMessages[] = "Line {$error['line']}: {$error['message']}";
                }
                throw new Exception('DTE validation failed: ' . implode('; ', $errorMessages));
            }
        } else {
            Log::warning('XSD schemas not available for validation');
        }
    }

    /**
     * Create EnvioDTE wrapper
     *
     * @param array $documents
     * @return string
     * @throws Exception
     */
    private function createEnvioDTE(array $documents): string
    {
        $tenant = $documents[0]->tenant;
        $dtes = [];
        $subtotales = [];

        foreach ($documents as $document) {
            if ($document->xml_content) {
                $dtes[] = $document->xml_content;
                
                $tipoDTE = $this->getTipoDTE($document->type);
                if (!isset($subtotales[$tipoDTE])) {
                    $subtotales[$tipoDTE] = 0;
                }
                $subtotales[$tipoDTE]++;
            }
        }

        $envioData = [
            'rut_emisor' => $tenant->rut,
            'rut_envia' => $tenant->authorized_sender_rut ?? $tenant->rut,
            'rut_receptor' => config('sii.rut_receptor', '60803000-K'),
            'fecha_resolucion' => $tenant->sii_resolution_date,
            'numero_resolucion' => $tenant->sii_resolution_number,
            'subtotales' => array_map(function ($tipo, $cantidad) {
                return [
                    'tipo_dte' => $tipo,
                    'cantidad' => $cantidad,
                ];
            }, array_keys($subtotales), $subtotales),
        ];

        return $this->xmlGenerator->generateEnvioDTE($envioData, $dtes);
    }

    /**
     * Send to SII
     *
     * @param string $xml
     * @param string $token
     * @param Tenant $tenant
     * @return array
     * @throws Exception
     */
    private function sendToSII(string $xml, string $token, Tenant $tenant): array
    {
        $response = Http::withHeaders([
            'Cookie' => 'TOKEN=' . $token,
        ])->attach(
            'archivo',
            $xml,
            'envio_' . uniqid() . '.xml'
        )->post($this->getEndpointUrl('dte_upload', $tenant));

        if (!$response->successful()) {
            throw new Exception('Failed to send to SII: ' . $response->status());
        }

        $result = $response->json();
        
        if (!isset($result['STATUS']) || $result['STATUS'] !== '0') {
            throw new Exception('SII rejected the submission: ' . ($result['DETAIL'] ?? 'Unknown error'));
        }

        return $result;
    }

    /**
     * Log SII event
     *
     * @param TaxDocument|null $document
     * @param string $eventType
     * @param array $data
     * @param Tenant|null $tenant
     */
    private function logSiiEvent(?TaxDocument $document, string $eventType, array $data, ?Tenant $tenant = null): void
    {
        try {
            $logData = [
                'tenant_id' => $tenant ? $tenant->id : ($document ? $document->tenant_id : null),
                'tax_document_id' => $document?->id,
                'event_type' => $eventType,
                'track_id' => $data['track_id'] ?? null,
                'status' => $data['status'] ?? null,
                'request_data' => $data['request'] ?? null,
                'response_data' => $data['response'] ?? null,
                'error_message' => $data['error'] ?? null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'response_time' => $data['response_time'] ?? null,
            ];

            \App\Models\SiiEventLog::create($logData);
        } catch (Exception $e) {
            Log::error('Failed to log SII event', [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate PDF from DTE
     *
     * @param TaxDocument $document
     * @return string
     * @throws Exception
     */
    public function generatePDF(TaxDocument $document): string
    {
        // This is a placeholder - actual PDF generation would use a library like TCPDF or similar
        // The PDF must include the required SII elements like TED (Timbre Electrónico)
        // For now, we'll use the standard invoice PDF generation
        
        $document->load(['customer', 'items.product', 'tenant']);
        return route('invoices.download', $document);
    }
    
    /**
     * Get endpoint URL based on tenant environment
     *
     * @param string $endpoint
     * @param Tenant $tenant
     * @return string
     */
    private function getEndpointUrl(string $endpoint, Tenant $tenant): string
    {
        $environment = $tenant->sii_environment ?? $this->environment;
        return $this->endpoints[$environment][$endpoint];
    }
}