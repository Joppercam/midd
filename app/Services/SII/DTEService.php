<?php

namespace App\Services\SII;

use App\Models\Tenant;
use App\Models\TaxDocument;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use phpseclib3\Crypt\RSA;
use phpseclib3\File\X509;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

class DTEService
{
    private XMLGeneratorService $xmlGenerator;
    private string $envioUrl = 'https://maullin.sii.cl/cgi_dte/UPL/DTEUpload';
    private string $consultaUrl = 'https://maullin.sii.cl/cgi_dte/RTC/RTCAnotacion.cgi';
    
    public function __construct(XMLGeneratorService $xmlGenerator)
    {
        $this->xmlGenerator = $xmlGenerator;
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
            $response = $this->sendToSII($envioDTE, $token);
            
            // Update document status
            $document->update([
                'sii_status' => 'sent',
                'sent_at' => Carbon::now(),
                'sii_track_id' => $response['trackingId'] ?? null,
            ]);

            return [
                'success' => true,
                'tracking_id' => $response['trackingId'] ?? null,
                'response' => $response,
            ];
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
            $response = Http::withHeaders([
                'Cookie' => 'TOKEN=' . $token,
            ])->asForm()->post($this->consultaUrl, [
                'TRACKID' => $trackingId,
                'RUT_EMISOR' => $tenant->rut,
            ]);

            if (!$response->successful()) {
                throw new Exception('Failed to check DTE status: ' . $response->status());
            }

            $xml = simplexml_load_string($response->body());
            
            if ($xml === false) {
                throw new Exception('Invalid XML response from SII');
            }

            return $this->parseDTEStatusResponse($xml);
        } catch (Exception $e) {
            Log::error('Error checking DTE status', [
                'tracking_id' => $trackingId,
                'error' => $e->getMessage()
            ]);
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
        $certificatePath = storage_path('app/sii/certificates/' . $tenant->id . '/cert.pem');
        $privateKeyPath = storage_path('app/sii/certificates/' . $tenant->id . '/key.pem');
        
        if (!file_exists($certificatePath) || !file_exists($privateKeyPath)) {
            throw new Exception('Certificate files not found');
        }

        // Sign the XML (simplified version - actual implementation would be more complex)
        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        
        // Create signature
        $objDSig = new XMLSecurityDSig();
        $objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
        $objDSig->addReference(
            $doc,
            XMLSecurityDSig::SHA1,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature']
        );

        // Create key
        $objKey = new XMLSecurityKey(
            XMLSecurityKey::RSA_SHA1,
            ['type' => 'private']
        );
        $objKey->loadKey($privateKeyPath, true);

        // Sign
        $objDSig->sign($objKey);
        
        // Add certificate
        $objDSig->add509Cert(file_get_contents($certificatePath));
        
        // Append signature
        $objDSig->appendSignature($doc->documentElement);

        return $doc->saveXML();
    }

    /**
     * Validate DTE
     *
     * @param string $xml
     * @throws Exception
     */
    private function validateDTE(string $xml): void
    {
        // Basic validation - implement full SII validation rules
        $doc = new \DOMDocument();
        if (!$doc->loadXML($xml)) {
            throw new Exception('Invalid XML document');
        }

        // Additional validations can be added here
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
     * @return array
     * @throws Exception
     */
    private function sendToSII(string $xml, string $token): array
    {
        $response = Http::withHeaders([
            'Cookie' => 'TOKEN=' . $token,
        ])->attach(
            'archivo',
            $xml,
            'envio_' . uniqid() . '.xml'
        )->post($this->envioUrl);

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
     * Parse DTE status response
     *
     * @param \SimpleXMLElement $xml
     * @return array
     */
    private function parseDTEStatusResponse(\SimpleXMLElement $xml): array
    {
        $namespaces = $xml->getNamespaces(true);
        $xml->registerXPathNamespace('SII', $namespaces['SII'] ?? 'http://www.sii.cl/XMLSchema');

        $status = [
            'estado' => (string) $xml->xpath('//ESTADO')[0] ?? 'unknown',
            'glosa' => (string) $xml->xpath('//GLOSA')[0] ?? '',
            'aceptados' => (int) $xml->xpath('//ACEPTADOS')[0] ?? 0,
            'rechazados' => (int) $xml->xpath('//RECHAZADOS')[0] ?? 0,
            'reparos' => (int) $xml->xpath('//REPAROS')[0] ?? 0,
            'detalles' => [],
        ];

        // Parse detail errors if any
        $errores = $xml->xpath('//ERROR');
        foreach ($errores as $error) {
            $status['detalles'][] = [
                'codigo' => (string) $error->attributes()->CODIGO ?? '',
                'descripcion' => (string) $error ?? '',
            ];
        }

        return $status;
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
}