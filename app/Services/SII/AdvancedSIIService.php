<?php

namespace App\Services\SII;

use App\Models\TaxDocument;
use App\Models\Tenant;
use App\Models\SiiEventLog;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use DOMDocument;
use Exception;

class AdvancedSIIService
{
    protected string $environment;
    protected array $endpoints;
    protected string $certificatePath;
    protected string $privateKeyPath;
    protected string $certificatePassword;

    public function __construct()
    {
        $this->environment = config('sii.environment', 'certificacion');
        $this->endpoints = config('sii.endpoints');
        $this->certificatePath = storage_path('app/sii/certificates/');
        $this->privateKeyPath = storage_path('app/sii/private_keys/');
    }

    /**
     * Configure SII integration for tenant
     */
    public function configureTenant(Tenant $tenant, array $config): array
    {
        try {
            // Validate certificate
            $certificateValidation = $this->validateCertificate($config['certificate_path']);
            if (!$certificateValidation['valid']) {
                throw new Exception('Certificado inválido: ' . $certificateValidation['error']);
            }

            // Store certificate securely
            $certificateId = $this->storeCertificate($tenant, $config['certificate_path'], $config['password']);

            // Test SII connection
            $connectionTest = $this->testSIIConnection($tenant);

            // Update tenant configuration
            $tenant->update([
                'sii_environment' => $this->environment,
                'sii_certificate_id' => $certificateId,
                'sii_rut_emisor' => $config['rut_emisor'],
                'sii_razon_social' => $config['razon_social'],
                'sii_giro' => $config['giro'],
                'sii_direccion' => $config['direccion'],
                'sii_comuna' => $config['comuna'],
                'sii_ciudad' => $config['ciudad'],
                'sii_folio_desde' => $config['folio_desde'] ?? 1,
                'sii_folio_hasta' => $config['folio_hasta'] ?? 999999,
                'sii_configured_at' => now(),
                'sii_last_test' => $connectionTest['success'] ? now() : null
            ]);

            $this->logSIIEvent($tenant, 'configuration', 'success', 'Configuración SII completada exitosamente');

            return [
                'success' => true,
                'certificate_id' => $certificateId,
                'connection_test' => $connectionTest,
                'message' => 'Configuración SII completada exitosamente'
            ];

        } catch (Exception $e) {
            $this->logSIIEvent($tenant, 'configuration', 'error', $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send single DTE to SII
     */
    public function sendDTE(TaxDocument $document): array
    {
        try {
            $tenant = $document->tenant;
            
            if (!$this->isTenantConfigured($tenant)) {
                throw new Exception('Tenant no configurado para SII');
            }

            // Generate DTE XML
            $dteXml = $this->generateDTEXML($document);

            // Sign DTE
            $signedXml = $this->signXML($dteXml, $tenant);

            // Create envelope
            $envelope = $this->createEnvelope($signedXml, $tenant);

            // Send to SII
            $response = $this->sendToSII($envelope, $tenant, 'upload_dte');

            // Process response
            $result = $this->processUploadResponse($response, $document);

            // Update document
            $document->update([
                'sii_track_id' => $result['track_id'] ?? null,
                'sii_status' => $result['status'],
                'sii_response' => $result['response'],
                'sii_sent_at' => now()
            ]);

            $this->logSIIEvent($tenant, 'send_dte', $result['status'], "DTE {$document->number} enviado", [
                'document_id' => $document->id,
                'track_id' => $result['track_id'] ?? null
            ]);

            return $result;

        } catch (Exception $e) {
            $this->logSIIEvent($document->tenant, 'send_dte', 'error', $e->getMessage(), [
                'document_id' => $document->id
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send batch of DTEs to SII
     */
    public function sendBatchDTE(array $documents): array
    {
        $results = [];
        $tenant = null;

        try {
            if (empty($documents)) {
                throw new Exception('No hay documentos para enviar');
            }

            $tenant = $documents[0]->tenant;
            
            if (!$this->isTenantConfigured($tenant)) {
                throw new Exception('Tenant no configurado para SII');
            }

            // Group documents by type
            $groupedDocuments = collect($documents)->groupBy('type');

            foreach ($groupedDocuments as $type => $typeDocuments) {
                // Create batch envelope
                $batchXml = $this->createBatchEnvelope($typeDocuments, $tenant);

                // Send batch to SII
                $response = $this->sendToSII($batchXml, $tenant, 'upload_batch');

                // Process batch response
                $batchResult = $this->processBatchResponse($response, $typeDocuments);

                $results[$type] = $batchResult;

                // Update documents
                foreach ($typeDocuments as $index => $document) {
                    $documentResult = $batchResult['documents'][$index] ?? ['status' => 'error'];
                    
                    $document->update([
                        'sii_track_id' => $batchResult['track_id'] ?? null,
                        'sii_status' => $documentResult['status'],
                        'sii_response' => $documentResult['response'] ?? null,
                        'sii_sent_at' => now()
                    ]);
                }
            }

            $this->logSIIEvent($tenant, 'send_batch', 'success', "Lote de " . count($documents) . " documentos enviado");

            return [
                'success' => true,
                'results' => $results,
                'total_sent' => count($documents)
            ];

        } catch (Exception $e) {
            if ($tenant) {
                $this->logSIIEvent($tenant, 'send_batch', 'error', $e->getMessage());
            }

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check DTE status with SII
     */
    public function checkDTEStatus(TaxDocument $document): array
    {
        try {
            $tenant = $document->tenant;
            
            if (!$document->sii_track_id) {
                throw new Exception('Documento no tiene track ID de SII');
            }

            // Prepare status query
            $queryXml = $this->createStatusQuery($document->sii_track_id, $tenant);

            // Send query to SII
            $response = $this->sendToSII($queryXml, $tenant, 'query_status');

            // Process status response
            $result = $this->processStatusResponse($response, $document);

            // Update document status
            $document->update([
                'sii_status' => $result['status'],
                'sii_response' => $result['response'],
                'sii_last_check' => now()
            ]);

            $this->logSIIEvent($tenant, 'check_status', 'success', "Estado verificado para DTE {$document->number}", [
                'document_id' => $document->id,
                'status' => $result['status']
            ]);

            return $result;

        } catch (Exception $e) {
            $this->logSIIEvent($document->tenant, 'check_status', 'error', $e->getMessage(), [
                'document_id' => $document->id
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get available folios from SII
     */
    public function getFolios(Tenant $tenant, int $documentType, int $quantity = 100): array
    {
        try {
            if (!$this->isTenantConfigured($tenant)) {
                throw new Exception('Tenant no configurado para SII');
            }

            // Create folio request
            $folioXml = $this->createFolioRequest($tenant, $documentType, $quantity);

            // Send request to SII
            $response = $this->sendToSII($folioXml, $tenant, 'request_folios');

            // Process folio response
            $result = $this->processFolioResponse($response, $tenant, $documentType);

            $this->logSIIEvent($tenant, 'request_folios', 'success', "Folios solicitados para tipo {$documentType}", [
                'document_type' => $documentType,
                'quantity' => $quantity
            ]);

            return $result;

        } catch (Exception $e) {
            $this->logSIIEvent($tenant, 'request_folios', 'error', $e->getMessage(), [
                'document_type' => $documentType,
                'quantity' => $quantity
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send libro de ventas/compras to SII
     */
    public function sendTaxBook(array $bookData, string $bookType, Tenant $tenant): array
    {
        try {
            if (!$this->isTenantConfigured($tenant)) {
                throw new Exception('Tenant no configurado para SII');
            }

            // Generate book XML
            $bookXml = $this->generateTaxBookXML($bookData, $bookType, $tenant);

            // Sign book XML
            $signedXml = $this->signXML($bookXml, $tenant);

            // Send to SII
            $response = $this->sendToSII($signedXml, $tenant, 'upload_book');

            // Process response
            $result = $this->processBookResponse($response, $bookType);

            $this->logSIIEvent($tenant, 'send_book', $result['status'], "Libro {$bookType} enviado", [
                'book_type' => $bookType,
                'period' => $bookData['period'] ?? null
            ]);

            return $result;

        } catch (Exception $e) {
            $this->logSIIEvent($tenant, 'send_book', 'error', $e->getMessage(), [
                'book_type' => $bookType
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Validate and store certificate
     */
    protected function validateCertificate(string $certificatePath): array
    {
        try {
            if (!file_exists($certificatePath)) {
                return ['valid' => false, 'error' => 'Archivo de certificado no encontrado'];
            }

            $certificateContent = file_get_contents($certificatePath);
            $certificate = openssl_x509_parse($certificateContent);

            if (!$certificate) {
                return ['valid' => false, 'error' => 'No se pudo leer el certificado'];
            }

            // Check expiration
            $validTo = Carbon::createFromTimestamp($certificate['validTo_time_t']);
            if ($validTo->isPast()) {
                return ['valid' => false, 'error' => 'Certificado expirado'];
            }

            // Check if it's close to expiration (30 days)
            $warning = null;
            if ($validTo->diffInDays(now()) <= 30) {
                $warning = "Certificado expira en {$validTo->diffInDays(now())} días";
            }

            return [
                'valid' => true,
                'subject' => $certificate['subject']['CN'] ?? 'Unknown',
                'issuer' => $certificate['issuer']['CN'] ?? 'Unknown',
                'valid_from' => Carbon::createFromTimestamp($certificate['validFrom_time_t'])->toDateString(),
                'valid_to' => $validTo->toDateString(),
                'warning' => $warning
            ];

        } catch (Exception $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Store certificate securely
     */
    protected function storeCertificate(Tenant $tenant, string $certificatePath, string $password): string
    {
        $certificateId = uniqid('cert_');
        $tenantDir = $this->certificatePath . $tenant->id . '/';
        
        if (!is_dir($tenantDir)) {
            mkdir($tenantDir, 0700, true);
        }

        $storedPath = $tenantDir . $certificateId . '.p12';
        copy($certificatePath, $storedPath);

        // Store password encrypted
        $encryptedPassword = encrypt($password);
        file_put_contents($tenantDir . $certificateId . '.key', $encryptedPassword);

        return $certificateId;
    }

    /**
     * Test SII connection
     */
    protected function testSIIConnection(Tenant $tenant): array
    {
        try {
            $testXml = $this->createTestRequest($tenant);
            $response = $this->sendToSII($testXml, $tenant, 'test_connection');

            return [
                'success' => true,
                'response_time' => $response['response_time'] ?? 0,
                'server_status' => 'active'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if tenant is properly configured for SII
     */
    protected function isTenantConfigured(Tenant $tenant): bool
    {
        return !empty($tenant->sii_certificate_id) && 
               !empty($tenant->sii_rut_emisor) && 
               !empty($tenant->sii_configured_at);
    }

    /**
     * Generate DTE XML for document
     */
    protected function generateDTEXML(TaxDocument $document): string
    {
        $xml = new DOMDocument('1.0', 'ISO-8859-1');
        $xml->formatOutput = true;

        // Create DTE structure
        $dte = $xml->createElement('DTE');
        $dte->setAttribute('version', '1.0');
        $xml->appendChild($dte);

        $documento = $xml->createElement('Documento');
        $documento->setAttribute('ID', 'F' . $document->number . 'T' . $this->getDocumentType($document->type));
        $dte->appendChild($documento);

        // Add document header (Encabezado)
        $encabezado = $this->createDocumentHeader($xml, $document);
        $documento->appendChild($encabezado);

        // Add document details (Detalles)
        $detalles = $this->createDocumentDetails($xml, $document);
        foreach ($detalles as $detalle) {
            $documento->appendChild($detalle);
        }

        // Add totals (Totales)
        $totales = $this->createDocumentTotals($xml, $document);
        $documento->appendChild($totales);

        return $xml->saveXML();
    }

    /**
     * Sign XML with digital certificate
     */
    protected function signXML(string $xml, Tenant $tenant): string
    {
        try {
            $certificateId = $tenant->sii_certificate_id;
            $certificatePath = $this->certificatePath . $tenant->id . '/' . $certificateId . '.p12';
            $passwordFile = $this->certificatePath . $tenant->id . '/' . $certificateId . '.key';
            
            if (!file_exists($certificatePath) || !file_exists($passwordFile)) {
                throw new Exception('Certificado no encontrado');
            }

            $password = decrypt(file_get_contents($passwordFile));

            // Load certificate
            $certificateData = file_get_contents($certificatePath);
            $certificates = [];
            
            if (!openssl_pkcs12_read($certificateData, $certificates, $password)) {
                throw new Exception('No se pudo leer el certificado');
            }

            // Create signed XML (simplified implementation)
            // In a real implementation, you would use proper XML signing libraries
            $signedXml = $this->addXMLSignature($xml, $certificates['pkey'], $certificates['cert']);

            return $signedXml;

        } catch (Exception $e) {
            throw new Exception('Error al firmar XML: ' . $e->getMessage());
        }
    }

    /**
     * Send XML to SII
     */
    protected function sendToSII(string $xml, Tenant $tenant, string $action): array
    {
        try {
            $endpoint = $this->getEndpoint($action);
            $startTime = microtime(true);

            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'text/xml; charset=UTF-8',
                    'SOAPAction' => $action
                ])
                ->withBody($xml)
                ->post($endpoint);

            $responseTime = microtime(true) - $startTime;

            if (!$response->successful()) {
                throw new Exception('Error HTTP: ' . $response->status());
            }

            return [
                'success' => true,
                'response' => $response->body(),
                'response_time' => round($responseTime, 3),
                'status_code' => $response->status()
            ];

        } catch (Exception $e) {
            throw new Exception('Error enviando a SII: ' . $e->getMessage());
        }
    }

    /**
     * Log SII events for audit
     */
    protected function logSIIEvent(Tenant $tenant, string $event, string $status, string $message, array $data = []): void
    {
        try {
            SiiEventLog::create([
                'tenant_id' => $tenant->id,
                'event' => $event,
                'status' => $status,
                'message' => $message,
                'data' => $data,
                'created_at' => now()
            ]);

        } catch (Exception $e) {
            Log::error('Failed to log SII event', [
                'tenant_id' => $tenant->id,
                'event' => $event,
                'error' => $e->getMessage()
            ]);
        }
    }

    // Additional helper methods would be implemented here...
    
    protected function getDocumentType(string $type): int
    {
        return match($type) {
            'invoice' => 33,
            'credit_note' => 61,
            'debit_note' => 56,
            'exempt_invoice' => 34,
            default => 33
        };
    }

    protected function getEndpoint(string $action): string
    {
        $baseUrl = $this->environment === 'production' 
            ? 'https://palena.sii.cl' 
            : 'https://maullin.sii.cl';
            
        return $baseUrl . '/DTEWS/services/' . $action;
    }

    protected function createDocumentHeader(DOMDocument $xml, TaxDocument $document): \DOMElement
    {
        // Implementation for document header creation
        return $xml->createElement('Encabezado');
    }

    protected function createDocumentDetails(DOMDocument $xml, TaxDocument $document): array
    {
        // Implementation for document details creation
        return [];
    }

    protected function createDocumentTotals(DOMDocument $xml, TaxDocument $document): \DOMElement
    {
        // Implementation for document totals creation
        return $xml->createElement('Totales');
    }

    protected function addXMLSignature(string $xml, $privateKey, $certificate): string
    {
        // Implementation for XML digital signature
        return $xml;
    }

    protected function createEnvelope(string $signedXml, Tenant $tenant): string
    {
        // Implementation for SOAP envelope creation
        return $signedXml;
    }

    protected function processUploadResponse(array $response, TaxDocument $document): array
    {
        // Implementation for processing upload response
        return ['success' => true, 'status' => 'sent'];
    }

    // ... Additional helper methods for other operations
}