<?php

namespace App\Services\SII;

use DOMDocument;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class XSDValidatorService
{
    private array $schemaUrls = [
        'DTE_v10' => 'http://www.sii.cl/SiiDte/DTE_v10.xsd',
        'EnvioDTE_v10' => 'http://www.sii.cl/SiiDte/EnvioDTE_v10.xsd',
        'SiiTypes_v10' => 'http://www.sii.cl/SiiDte/SiiTypes_v10.xsd',
        'xmldsig_v10' => 'http://www.sii.cl/SiiDte/xmldsig_v10.xsd',
        'EnvioBOLETA_v11' => 'http://www.sii.cl/SiiDte/EnvioBOLETA_v11.xsd',
        'RespuestaDTE_v10' => 'http://www.sii.cl/SiiDte/RespuestaDTE_v10.xsd',
        'ReciboDTE_v10' => 'http://www.sii.cl/SiiDte/ReciboDTE_v10.xsd',
    ];

    private string $schemaPath;

    public function __construct()
    {
        $this->schemaPath = storage_path('app/sii/schemas');
    }

    /**
     * Download all required XSD schemas from SII
     *
     * @return array
     * @throws Exception
     */
    public function downloadSchemas(): array
    {
        $results = [];

        foreach ($this->schemaUrls as $name => $url) {
            try {
                $response = Http::timeout(30)->get($url);
                
                if ($response->successful()) {
                    $filename = $name . '.xsd';
                    $filePath = $this->schemaPath . '/' . $filename;
                    
                    file_put_contents($filePath, $response->body());
                    
                    $results[$name] = [
                        'success' => true,
                        'message' => "Schema {$name} downloaded successfully",
                        'path' => $filePath,
                    ];
                } else {
                    $results[$name] = [
                        'success' => false,
                        'message' => "Failed to download schema {$name}: HTTP {$response->status()}",
                    ];
                }
            } catch (Exception $e) {
                $results[$name] = [
                    'success' => false,
                    'message' => "Error downloading schema {$name}: {$e->getMessage()}",
                ];
            }
        }

        // Download additional schemas referenced by main schemas
        $this->downloadReferencedSchemas();

        return $results;
    }

    /**
     * Download additional referenced schemas
     */
    private function downloadReferencedSchemas(): void
    {
        // W3C XML Schema
        $w3cSchemas = [
            'http://www.w3.org/TR/xmldsig-core/xmldsig-core-schema.xsd',
            'http://www.w3.org/2001/XMLSchema.xsd',
        ];

        foreach ($w3cSchemas as $url) {
            try {
                $response = Http::timeout(30)->get($url);
                if ($response->successful()) {
                    $filename = basename($url);
                    file_put_contents($this->schemaPath . '/' . $filename, $response->body());
                }
            } catch (Exception $e) {
                Log::warning("Could not download W3C schema: {$url}", ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Validate DTE XML against XSD schema
     *
     * @param string $xml
     * @param string $documentType
     * @return array
     */
    public function validateDTE(string $xml, string $documentType = 'DTE'): array
    {
        try {
            // Enable internal error handling
            libxml_use_internal_errors(true);
            libxml_clear_errors();

            $doc = new DOMDocument();
            $doc->preserveWhiteSpace = false;
            $doc->formatOutput = true;
            
            if (!$doc->loadXML($xml)) {
                return $this->getLibXmlErrors('Failed to load XML document');
            }

            // Determine schema based on document type
            $schemaFile = $this->getSchemaForDocument($doc, $documentType);
            $schemaPath = $this->schemaPath . '/' . $schemaFile;

            if (!file_exists($schemaPath)) {
                throw new Exception("Schema file not found: {$schemaFile}. Please download schemas first.");
            }

            // Validate against schema
            if (!$doc->schemaValidate($schemaPath)) {
                return $this->getLibXmlErrors('XML validation failed');
            }

            return [
                'valid' => true,
                'message' => 'Document is valid according to SII schema',
                'schema' => $schemaFile,
            ];

        } catch (Exception $e) {
            return [
                'valid' => false,
                'message' => $e->getMessage(),
                'errors' => [],
            ];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors(false);
        }
    }

    /**
     * Validate EnvioDTE XML
     *
     * @param string $xml
     * @return array
     */
    public function validateEnvioDTE(string $xml): array
    {
        return $this->validateDTE($xml, 'EnvioDTE');
    }

    /**
     * Get schema file for document type
     *
     * @param DOMDocument $doc
     * @param string $type
     * @return string
     */
    private function getSchemaForDocument(DOMDocument $doc, string $type): string
    {
        $rootElement = $doc->documentElement->nodeName;

        $schemaMap = [
            'DTE' => 'DTE_v10.xsd',
            'EnvioDTE' => 'EnvioDTE_v10.xsd',
            'EnvioBOLETA' => 'EnvioBOLETA_v11.xsd',
            'RespuestaDTE' => 'RespuestaDTE_v10.xsd',
            'ReciboDTE' => 'ReciboDTE_v10.xsd',
        ];

        // Check by root element name
        if (isset($schemaMap[$rootElement])) {
            return $schemaMap[$rootElement];
        }

        // Check by type parameter
        if (isset($schemaMap[$type])) {
            return $schemaMap[$type];
        }

        // Default to DTE schema
        return 'DTE_v10.xsd';
    }

    /**
     * Get LibXML errors formatted
     *
     * @param string $mainMessage
     * @return array
     */
    private function getLibXmlErrors(string $mainMessage): array
    {
        $errors = libxml_get_errors();
        $errorDetails = [];

        foreach ($errors as $error) {
            $errorDetails[] = [
                'level' => $this->getErrorLevel($error->level),
                'code' => $error->code,
                'message' => trim($error->message),
                'line' => $error->line,
                'column' => $error->column,
            ];
        }

        return [
            'valid' => false,
            'message' => $mainMessage,
            'errors' => $errorDetails,
        ];
    }

    /**
     * Get error level string
     *
     * @param int $level
     * @return string
     */
    private function getErrorLevel(int $level): string
    {
        switch ($level) {
            case LIBXML_ERR_WARNING:
                return 'warning';
            case LIBXML_ERR_ERROR:
                return 'error';
            case LIBXML_ERR_FATAL:
                return 'fatal';
            default:
                return 'unknown';
        }
    }

    /**
     * Check if schemas are downloaded
     *
     * @return bool
     */
    public function schemasExist(): bool
    {
        foreach ($this->schemaUrls as $name => $url) {
            $filename = $name . '.xsd';
            if (!file_exists($this->schemaPath . '/' . $filename)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get list of available schemas
     *
     * @return array
     */
    public function getAvailableSchemas(): array
    {
        $schemas = [];
        
        foreach ($this->schemaUrls as $name => $url) {
            $filename = $name . '.xsd';
            $filePath = $this->schemaPath . '/' . $filename;
            
            $schemas[] = [
                'name' => $name,
                'filename' => $filename,
                'exists' => file_exists($filePath),
                'size' => file_exists($filePath) ? filesize($filePath) : 0,
                'url' => $url,
            ];
        }

        return $schemas;
    }

    /**
     * Validate specific elements of DTE
     *
     * @param array $dteData
     * @return array
     */
    public function validateDTEData(array $dteData): array
    {
        $errors = [];

        // Validate RUT format
        if (isset($dteData['emisor']['rut'])) {
            if (!$this->validateRUT($dteData['emisor']['rut'])) {
                $errors[] = 'RUT del emisor es inválido';
            }
        }

        if (isset($dteData['receptor']['rut'])) {
            if (!$this->validateRUT($dteData['receptor']['rut'])) {
                $errors[] = 'RUT del receptor es inválido';
            }
        }

        // Validate document type
        $validTypes = [33, 34, 39, 41, 46, 52, 56, 61, 110, 111, 112];
        if (isset($dteData['tipo_dte']) && !in_array($dteData['tipo_dte'], $validTypes)) {
            $errors[] = 'Tipo de documento no válido: ' . $dteData['tipo_dte'];
        }

        // Validate amounts
        if (isset($dteData['monto_total']) && $dteData['monto_total'] < 0) {
            $errors[] = 'El monto total no puede ser negativo';
        }

        // Validate dates
        if (isset($dteData['fecha_emision'])) {
            $fecha = \DateTime::createFromFormat('Y-m-d', $dteData['fecha_emision']);
            if (!$fecha) {
                $errors[] = 'Formato de fecha de emisión inválido';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate Chilean RUT
     *
     * @param string $rut
     * @return bool
     */
    private function validateRUT(string $rut): bool
    {
        $rut = preg_replace('/[^0-9kK]/', '', $rut);
        
        if (strlen($rut) < 2) {
            return false;
        }

        $numero = substr($rut, 0, -1);
        $dv = strtoupper(substr($rut, -1));

        // Calculate verification digit
        $suma = 0;
        $factor = 2;
        
        for ($i = strlen($numero) - 1; $i >= 0; $i--) {
            $suma += $numero[$i] * $factor;
            $factor = $factor == 7 ? 2 : $factor + 1;
        }

        $dvEsperado = 11 - ($suma % 11);
        
        if ($dvEsperado == 11) {
            $dvEsperado = '0';
        } elseif ($dvEsperado == 10) {
            $dvEsperado = 'K';
        } else {
            $dvEsperado = (string)$dvEsperado;
        }

        return $dv === $dvEsperado;
    }
}