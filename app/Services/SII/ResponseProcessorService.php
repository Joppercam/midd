<?php

namespace App\Services\SII;

use App\Models\TaxDocument;
use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

class ResponseProcessorService
{
    /**
     * Process upload response from SII
     *
     * @param array $response
     * @param TaxDocument $document
     * @return array
     */
    public function processUploadResponse(array $response, TaxDocument $document): array
    {
        try {
            DB::beginTransaction();

            // Extract response data
            $status = $response['STATUS'] ?? null;
            $trackId = $response['TRACKID'] ?? null;
            $timestamp = $response['TIMESTAMP'] ?? null;
            $detail = $response['DETAIL'] ?? null;

            // Update document with response data
            $document->update([
                'sii_track_id' => $trackId,
                'sii_status' => $this->mapUploadStatus($status),
                'sii_status_detail' => $detail,
                'sent_at' => $timestamp ? Carbon::parse($timestamp) : now(),
                'sii_response' => json_encode($response),
            ]);

            // Log the response
            Log::info('SII upload response processed', [
                'document_id' => $document->id,
                'track_id' => $trackId,
                'status' => $status,
            ]);

            DB::commit();

            return [
                'success' => $status === '0',
                'track_id' => $trackId,
                'status' => $this->mapUploadStatus($status),
                'message' => $this->getUploadStatusMessage($status, $detail),
                'timestamp' => $timestamp,
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error processing upload response', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
                'response' => $response,
            ]);
            throw $e;
        }
    }

    /**
     * Process status query response from SII
     *
     * @param string $xmlResponse
     * @param string $trackId
     * @return array
     */
    public function processStatusResponse(string $xmlResponse, string $trackId): array
    {
        try {
            $xml = new SimpleXMLElement($xmlResponse);
            
            // Register namespaces
            $namespaces = $xml->getNamespaces(true);
            foreach ($namespaces as $prefix => $namespace) {
                $xml->registerXPathNamespace($prefix, $namespace);
            }

            // Extract main status
            $estado = (string) ($xml->xpath('//ESTADO')[0] ?? 'DOK');
            $glosa = (string) ($xml->xpath('//GLOSA')[0] ?? '');
            $numAtencion = (string) ($xml->xpath('//NUM_ATENCION')[0] ?? '');

            // Extract statistics
            $informados = (int) ($xml->xpath('//INFORMADOS')[0] ?? 0);
            $aceptados = (int) ($xml->xpath('//ACEPTADOS')[0] ?? 0);
            $rechazados = (int) ($xml->xpath('//RECHAZADOS')[0] ?? 0);
            $reparos = (int) ($xml->xpath('//REPAROS')[0] ?? 0);

            // Extract document details
            $documentDetails = $this->extractDocumentDetails($xml);

            // Update affected documents
            $this->updateDocumentsFromStatus($trackId, $estado, $documentDetails);

            return [
                'track_id' => $trackId,
                'estado' => $estado,
                'glosa' => $glosa,
                'num_atencion' => $numAtencion,
                'statistics' => [
                    'informados' => $informados,
                    'aceptados' => $aceptados,
                    'rechazados' => $rechazados,
                    'reparos' => $reparos,
                ],
                'documents' => $documentDetails,
                'processed_at' => now()->toIso8601String(),
            ];

        } catch (Exception $e) {
            Log::error('Error processing status response', [
                'track_id' => $trackId,
                'error' => $e->getMessage(),
            ]);
            throw new Exception('Failed to process status response: ' . $e->getMessage());
        }
    }

    /**
     * Process acceptance/rejection response (RespuestaDTE)
     *
     * @param string $xmlResponse
     * @return array
     */
    public function processAcceptanceResponse(string $xmlResponse): array
    {
        try {
            $doc = new DOMDocument();
            $doc->loadXML($xmlResponse);
            
            $xpath = new DOMXPath($doc);
            $xpath->registerNamespace('ns', 'http://www.sii.cl/SiiDte');

            // Extract response identification
            $rutResponde = $xpath->query('//ns:RutResponde')->item(0)->nodeValue ?? '';
            $rutRecibe = $xpath->query('//ns:RutRecibe')->item(0)->nodeValue ?? '';
            $fechaFirma = $xpath->query('//ns:TmstFirmaResp')->item(0)->nodeValue ?? '';

            // Extract document responses
            $responses = [];
            $resultadoDtes = $xpath->query('//ns:ResultadoDTE');
            
            foreach ($resultadoDtes as $resultado) {
                $tipoDte = $xpath->query('ns:TipoDTE', $resultado)->item(0)->nodeValue ?? '';
                $folio = $xpath->query('ns:Folio', $resultado)->item(0)->nodeValue ?? '';
                $fechaEmision = $xpath->query('ns:FchEmis', $resultado)->item(0)->nodeValue ?? '';
                $rutEmisor = $xpath->query('ns:RUTEmisor', $resultado)->item(0)->nodeValue ?? '';
                $rutReceptor = $xpath->query('ns:RUTRecep', $resultado)->item(0)->nodeValue ?? '';
                $montoTotal = $xpath->query('ns:MntTotal', $resultado)->item(0)->nodeValue ?? '';
                $estado = $xpath->query('ns:EstadoDTE', $resultado)->item(0)->nodeValue ?? '';
                $estadoGlosa = $xpath->query('ns:EstadoDTEGlosa', $resultado)->item(0)->nodeValue ?? '';
                
                $responses[] = [
                    'tipo_dte' => $tipoDte,
                    'folio' => $folio,
                    'fecha_emision' => $fechaEmision,
                    'rut_emisor' => $rutEmisor,
                    'rut_receptor' => $rutReceptor,
                    'monto_total' => $montoTotal,
                    'estado' => $estado,
                    'estado_glosa' => $estadoGlosa,
                    'aceptado' => $estado === '0',
                ];

                // Update document status
                $this->updateDocumentFromAcceptance($tipoDte, $folio, $rutEmisor, $estado, $estadoGlosa);
            }

            return [
                'rut_responde' => $rutResponde,
                'rut_recibe' => $rutRecibe,
                'fecha_firma' => $fechaFirma,
                'responses' => $responses,
                'total_documentos' => count($responses),
                'aceptados' => collect($responses)->where('aceptado', true)->count(),
                'rechazados' => collect($responses)->where('aceptado', false)->count(),
            ];

        } catch (Exception $e) {
            Log::error('Error processing acceptance response', [
                'error' => $e->getMessage(),
            ]);
            throw new Exception('Failed to process acceptance response: ' . $e->getMessage());
        }
    }

    /**
     * Extract document details from status XML
     *
     * @param SimpleXMLElement $xml
     * @return array
     */
    private function extractDocumentDetails(SimpleXMLElement $xml): array
    {
        $details = [];
        
        // Look for document detail nodes
        $detalles = $xml->xpath('//DETALLE_REP_RECH') ?: $xml->xpath('//DETALLE');
        
        foreach ($detalles as $detalle) {
            $tipoDte = (string) ($detalle->xpath('TIPO_DOC')[0] ?? '');
            $folio = (string) ($detalle->xpath('FOLIO')[0] ?? '');
            $estado = (string) ($detalle->xpath('ESTADO')[0] ?? '');
            $descripcion = (string) ($detalle->xpath('DESC_ERR')[0] ?? '');
            $rutEmisor = (string) ($detalle->xpath('RUT_EMISOR')[0] ?? '');
            
            if ($tipoDte && $folio) {
                $details[] = [
                    'tipo_dte' => $tipoDte,
                    'folio' => $folio,
                    'estado' => $estado,
                    'descripcion' => $descripcion,
                    'rut_emisor' => $rutEmisor,
                ];
            }
        }

        // Also check for error details
        $errores = $xml->xpath('//ERROR');
        foreach ($errores as $error) {
            $codigo = (string) ($error->attributes()->CODIGO ?? '');
            $descripcion = (string) $error;
            
            if ($codigo) {
                $details[] = [
                    'tipo' => 'error',
                    'codigo' => $codigo,
                    'descripcion' => $descripcion,
                ];
            }
        }

        return $details;
    }

    /**
     * Update documents based on status response
     *
     * @param string $trackId
     * @param string $estado
     * @param array $documentDetails
     */
    private function updateDocumentsFromStatus(string $trackId, string $estado, array $documentDetails): void
    {
        try {
            // Update all documents with this track ID
            $documents = TaxDocument::where('sii_track_id', $trackId)->get();
            
            foreach ($documents as $document) {
                $newStatus = $this->mapQueryStatus($estado);
                $document->update([
                    'sii_status' => $newStatus,
                    'sii_last_check' => now(),
                ]);

                // If we have specific details for this document
                $detail = collect($documentDetails)->first(function ($detail) use ($document) {
                    return isset($detail['folio']) && 
                           $detail['folio'] == $document->number &&
                           $detail['tipo_dte'] == $this->getTipoDTE($document->type);
                });

                if ($detail) {
                    $document->update([
                        'sii_status_detail' => $detail['descripcion'] ?? null,
                        'sii_status' => $this->mapDetailStatus($detail['estado'] ?? $estado),
                    ]);
                }
            }
        } catch (Exception $e) {
            Log::error('Error updating documents from status', [
                'track_id' => $trackId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update document from acceptance response
     *
     * @param string $tipoDte
     * @param string $folio
     * @param string $rutEmisor
     * @param string $estado
     * @param string $estadoGlosa
     */
    private function updateDocumentFromAcceptance(
        string $tipoDte, 
        string $folio, 
        string $rutEmisor, 
        string $estado, 
        string $estadoGlosa
    ): void {
        try {
            $document = TaxDocument::where('number', $folio)
                ->whereHas('tenant', function ($query) use ($rutEmisor) {
                    $query->where('rut', $rutEmisor);
                })
                ->where('type', $this->getDocumentType($tipoDte))
                ->first();

            if ($document) {
                $document->update([
                    'sii_status' => $estado === '0' ? 'accepted' : 'rejected',
                    'sii_status_detail' => $estadoGlosa,
                    'sii_acceptance_status' => $estado,
                    'sii_accepted_at' => $estado === '0' ? now() : null,
                    'sii_rejected_at' => $estado !== '0' ? now() : null,
                ]);
            }
        } catch (Exception $e) {
            Log::error('Error updating document from acceptance', [
                'tipo_dte' => $tipoDte,
                'folio' => $folio,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Map upload status code to status string
     *
     * @param string|null $status
     * @return string
     */
    private function mapUploadStatus(?string $status): string
    {
        $statusMap = [
            '0' => 'sent',
            '1' => 'error_schema',
            '2' => 'error_signature',
            '3' => 'error_system',
            '5' => 'error_authentication',
            '6' => 'error_authorization',
            '7' => 'error_upload',
            '99' => 'error_other',
        ];

        return $statusMap[$status] ?? 'unknown';
    }

    /**
     * Map query status to internal status
     *
     * @param string $estado
     * @return string
     */
    private function mapQueryStatus(string $estado): string
    {
        $statusMap = [
            'DOK' => 'accepted',
            'DNK' => 'accepted_with_discrepancies',
            'FAU' => 'rejected_sender',
            'FNA' => 'not_found',
            'FAN' => 'rejected_content',
            'EMP' => 'rejected_company',
            'TMC' => 'rejected_rut_change',
            'TMD' => 'rejected_dte_type_change',
            'AND' => 'not_registered',
            'RSC' => 'rejected_exceeded_range',
            'RCH' => 'rejected',
        ];

        return $statusMap[$estado] ?? 'processing';
    }

    /**
     * Map detail status to internal status
     *
     * @param string $estado
     * @return string
     */
    private function mapDetailStatus(string $estado): string
    {
        $statusMap = [
            'ACEPTADO' => 'accepted',
            'RECHAZADO' => 'rejected',
            'REPARO' => 'accepted_with_discrepancies',
        ];

        return $statusMap[strtoupper($estado)] ?? 'processing';
    }

    /**
     * Get upload status message
     *
     * @param string|null $status
     * @param string|null $detail
     * @return string
     */
    private function getUploadStatusMessage(?string $status, ?string $detail): string
    {
        $messages = [
            '0' => 'Envío recibido correctamente',
            '1' => 'Error en esquema del documento',
            '2' => 'Error en firma del documento',
            '3' => 'Error interno del sistema SII',
            '5' => 'Error de autenticación',
            '6' => 'Empresa no autorizada para enviar documentos',
            '7' => 'Error en la recepción del envío',
            '99' => 'Error desconocido',
        ];

        $message = $messages[$status] ?? 'Estado desconocido';
        
        if ($detail) {
            $message .= ': ' . $detail;
        }

        return $message;
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
            'invoice' => 33,
            'invoice_exempt' => 34,
            'purchase' => 46,
            'debit_note' => 56,
            'credit_note' => 61,
            'receipt' => 39,
            'receipt_exempt' => 41,
        ];

        return $types[$documentType] ?? 33;
    }

    /**
     * Get document type from tipo DTE
     *
     * @param string $tipoDte
     * @return string
     */
    private function getDocumentType(string $tipoDte): string
    {
        $types = [
            '33' => 'invoice',
            '34' => 'invoice_exempt',
            '46' => 'purchase',
            '56' => 'debit_note',
            '61' => 'credit_note',
            '39' => 'receipt',
            '41' => 'receipt_exempt',
        ];

        return $types[$tipoDte] ?? 'invoice';
    }

    /**
     * Parse and log response errors
     *
     * @param string $xmlResponse
     * @return array
     */
    public function parseResponseErrors(string $xmlResponse): array
    {
        $errors = [];
        
        try {
            $xml = new SimpleXMLElement($xmlResponse);
            $errorNodes = $xml->xpath('//ERROR');
            
            foreach ($errorNodes as $error) {
                $errors[] = [
                    'code' => (string) ($error->attributes()->CODIGO ?? ''),
                    'message' => (string) $error,
                    'line' => (string) ($error->attributes()->LINEA ?? ''),
                    'column' => (string) ($error->attributes()->COLUMNA ?? ''),
                    'type' => (string) ($error->attributes()->TIPO ?? 'ERROR'),
                ];
            }
        } catch (Exception $e) {
            Log::error('Error parsing response errors', [
                'error' => $e->getMessage(),
            ]);
        }

        return $errors;
    }
}