<?php

namespace App\Services\SII;

use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use Exception;
use Illuminate\Support\Facades\Log;

class XMLGeneratorService
{
    private DOMDocument $doc;
    private string $siiNamespace = 'http://www.sii.cl/SiiDte';

    /**
     * Generate DTE XML document
     *
     * @param array $data
     * @return string
     * @throws Exception
     */
    public function generateDTE(array $data): string
    {
        try {
            $this->doc = new DOMDocument('1.0', 'ISO-8859-1');
            $this->doc->preserveWhiteSpace = false;
            $this->doc->formatOutput = true;

            // Create DTE root element
            $dte = $this->doc->createElement('DTE');
            $dte->setAttribute('version', '1.0');
            $dte->setAttribute('xmlns', $this->siiNamespace);
            $this->doc->appendChild($dte);

            // Add Documento
            $documento = $this->createDocumento($data);
            $dte->appendChild($documento);

            // Add Signature placeholder
            $signature = $this->createSignaturePlaceholder();
            $dte->appendChild($signature);

            return $this->doc->saveXML();
        } catch (Exception $e) {
            Log::error('Error generating DTE XML', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw new Exception('Failed to generate DTE XML: ' . $e->getMessage());
        }
    }

    /**
     * Generate EnvioDTE XML document (wrapper for multiple DTEs)
     *
     * @param array $data
     * @param array $dtes
     * @return string
     * @throws Exception
     */
    public function generateEnvioDTE(array $data, array $dtes): string
    {
        try {
            $this->doc = new DOMDocument('1.0', 'ISO-8859-1');
            $this->doc->preserveWhiteSpace = false;
            $this->doc->formatOutput = true;

            // Create EnvioDTE root element
            $envioDte = $this->doc->createElement('EnvioDTE');
            $envioDte->setAttribute('version', '1.0');
            $envioDte->setAttribute('xmlns', $this->siiNamespace);
            $envioDte->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
            $envioDte->setAttribute('xsi:schemaLocation', $this->siiNamespace . ' EnvioDTE_v10.xsd');
            $this->doc->appendChild($envioDte);

            // Add SetDTE
            $setDte = $this->createSetDTE($data, $dtes);
            $envioDte->appendChild($setDte);

            // Add Signature placeholder
            $signature = $this->createSignaturePlaceholder();
            $envioDte->appendChild($signature);

            return $this->doc->saveXML();
        } catch (Exception $e) {
            Log::error('Error generating EnvioDTE XML', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw new Exception('Failed to generate EnvioDTE XML: ' . $e->getMessage());
        }
    }

    /**
     * Create Documento element
     *
     * @param array $data
     * @return DOMElement
     */
    private function createDocumento(array $data): DOMElement
    {
        $documento = $this->doc->createElement('Documento');
        $documento->setAttribute('ID', 'DOC' . $data['folio'] ?? uniqid());

        // Encabezado
        $encabezado = $this->createEncabezado($data);
        $documento->appendChild($encabezado);

        // Detalle
        if (isset($data['detalle']) && is_array($data['detalle'])) {
            foreach ($data['detalle'] as $item) {
                $detalle = $this->createDetalle($item);
                $documento->appendChild($detalle);
            }
        }

        // Totales
        $totales = $this->createTotales($data);
        $documento->appendChild($totales);

        return $documento;
    }

    /**
     * Create Encabezado element
     *
     * @param array $data
     * @return DOMElement
     */
    private function createEncabezado(array $data): DOMElement
    {
        $encabezado = $this->doc->createElement('Encabezado');

        // IdDoc
        $idDoc = $this->doc->createElement('IdDoc');
        $this->addElement($idDoc, 'TipoDTE', $data['tipo_dte'] ?? 33);
        $this->addElement($idDoc, 'Folio', $data['folio'] ?? 0);
        $this->addElement($idDoc, 'FchEmis', $data['fecha_emision'] ?? Carbon::now()->format('Y-m-d'));
        $this->addElement($idDoc, 'FmaPago', $data['forma_pago'] ?? 1);
        $this->addElement($idDoc, 'FchVenc', $data['fecha_vencimiento'] ?? Carbon::now()->addDays(30)->format('Y-m-d'));
        $encabezado->appendChild($idDoc);

        // Emisor
        $emisor = $this->createEmisor($data['emisor'] ?? []);
        $encabezado->appendChild($emisor);

        // Receptor
        $receptor = $this->createReceptor($data['receptor'] ?? []);
        $encabezado->appendChild($receptor);

        return $encabezado;
    }

    /**
     * Create Emisor element
     *
     * @param array $data
     * @return DOMElement
     */
    private function createEmisor(array $data): DOMElement
    {
        $emisor = $this->doc->createElement('Emisor');
        
        $this->addElement($emisor, 'RUTEmisor', $data['rut'] ?? '');
        $this->addElement($emisor, 'RznSoc', $data['razon_social'] ?? '');
        $this->addElement($emisor, 'GiroEmis', $data['giro'] ?? '');
        $this->addElement($emisor, 'Acteco', $data['actividad_economica'] ?? '');
        $this->addElement($emisor, 'DirOrigen', $data['direccion'] ?? '');
        $this->addElement($emisor, 'CmnaOrigen', $data['comuna'] ?? '');
        $this->addElement($emisor, 'CdgSIISucur', $data['codigo_sucursal'] ?? '');

        return $emisor;
    }

    /**
     * Create Receptor element
     *
     * @param array $data
     * @return DOMElement
     */
    private function createReceptor(array $data): DOMElement
    {
        $receptor = $this->doc->createElement('Receptor');
        
        $this->addElement($receptor, 'RUTRecep', $data['rut'] ?? '');
        $this->addElement($receptor, 'RznSocRecep', $data['razon_social'] ?? '');
        $this->addElement($receptor, 'GiroRecep', $data['giro'] ?? '');
        $this->addElement($receptor, 'DirRecep', $data['direccion'] ?? '');
        $this->addElement($receptor, 'CmnaRecep', $data['comuna'] ?? '');

        return $receptor;
    }

    /**
     * Create Detalle element
     *
     * @param array $data
     * @return DOMElement
     */
    private function createDetalle(array $data): DOMElement
    {
        $detalle = $this->doc->createElement('Detalle');
        
        $this->addElement($detalle, 'NroLinDet', $data['numero_linea'] ?? 1);
        $this->addElement($detalle, 'NmbItem', $data['nombre_item'] ?? '');
        $this->addElement($detalle, 'DscItem', $data['descripcion'] ?? '');
        $this->addElement($detalle, 'QtyItem', $data['cantidad'] ?? 1);
        $this->addElement($detalle, 'UnmdItem', $data['unidad_medida'] ?? '');
        $this->addElement($detalle, 'PrcItem', $data['precio_unitario'] ?? 0);
        $this->addElement($detalle, 'MontoItem', $data['monto_item'] ?? 0);

        return $detalle;
    }

    /**
     * Create Totales element
     *
     * @param array $data
     * @return DOMElement
     */
    private function createTotales(array $data): DOMElement
    {
        $totales = $this->doc->createElement('Totales');
        
        $this->addElement($totales, 'MntNeto', $data['monto_neto'] ?? 0);
        $this->addElement($totales, 'MntExe', $data['monto_exento'] ?? 0);
        $this->addElement($totales, 'IVA', $data['iva'] ?? 0);
        $this->addElement($totales, 'MntTotal', $data['monto_total'] ?? 0);

        return $totales;
    }

    /**
     * Create SetDTE element for EnvioDTE
     *
     * @param array $data
     * @param array $dtes
     * @return DOMElement
     */
    private function createSetDTE(array $data, array $dtes): DOMElement
    {
        $setDte = $this->doc->createElement('SetDTE');
        $setDte->setAttribute('ID', 'SetDoc' . uniqid());

        // Caratula
        $caratula = $this->createCaratula($data);
        $setDte->appendChild($caratula);

        // Add DTEs
        foreach ($dtes as $dteXml) {
            $dteDoc = new DOMDocument();
            $dteDoc->loadXML($dteXml);
            $importedNode = $this->doc->importNode($dteDoc->documentElement, true);
            $setDte->appendChild($importedNode);
        }

        return $setDte;
    }

    /**
     * Create Caratula element
     *
     * @param array $data
     * @return DOMElement
     */
    private function createCaratula(array $data): DOMElement
    {
        $caratula = $this->doc->createElement('Caratula');
        $caratula->setAttribute('version', '1.0');

        $this->addElement($caratula, 'RutEmisor', $data['rut_emisor'] ?? '');
        $this->addElement($caratula, 'RutEnvia', $data['rut_envia'] ?? '');
        $this->addElement($caratula, 'RutReceptor', $data['rut_receptor'] ?? '');
        $this->addElement($caratula, 'FchResol', $data['fecha_resolucion'] ?? '');
        $this->addElement($caratula, 'NroResol', $data['numero_resolucion'] ?? '');
        $this->addElement($caratula, 'TmstFirmaEnv', Carbon::now()->format('Y-m-d\TH:i:s'));

        // SubTotDTE
        if (isset($data['subtotales']) && is_array($data['subtotales'])) {
            foreach ($data['subtotales'] as $subtotal) {
                $subTotDte = $this->doc->createElement('SubTotDTE');
                $this->addElement($subTotDte, 'TpoDTE', $subtotal['tipo_dte'] ?? '');
                $this->addElement($subTotDte, 'NroDTE', $subtotal['cantidad'] ?? 0);
                $caratula->appendChild($subTotDte);
            }
        }

        return $caratula;
    }

    /**
     * Create signature placeholder
     *
     * @return DOMElement
     */
    private function createSignaturePlaceholder(): DOMElement
    {
        $signature = $this->doc->createElement('Signature');
        $signature->setAttribute('xmlns', 'http://www.w3.org/2000/09/xmldsig#');
        
        // This is a placeholder - actual signature will be added by the signing service
        $signedInfo = $this->doc->createElement('SignedInfo');
        $signature->appendChild($signedInfo);

        return $signature;
    }

    /**
     * Add element with text content
     *
     * @param DOMElement $parent
     * @param string $name
     * @param mixed $value
     */
    private function addElement(DOMElement $parent, string $name, $value): void
    {
        if ($value !== null && $value !== '') {
            $element = $this->doc->createElement($name, htmlspecialchars((string)$value));
            $parent->appendChild($element);
        }
    }

    /**
     * Validate XML against XSD schema
     *
     * @param string $xml
     * @param string $xsdPath
     * @return bool
     * @throws Exception
     */
    public function validateXML(string $xml, string $xsdPath): bool
    {
        libxml_use_internal_errors(true);
        
        $doc = new DOMDocument();
        $doc->loadXML($xml);
        
        if (!file_exists($xsdPath)) {
            throw new Exception('XSD schema file not found: ' . $xsdPath);
        }
        
        if (!$doc->schemaValidate($xsdPath)) {
            $errors = libxml_get_errors();
            $errorMessages = [];
            
            foreach ($errors as $error) {
                $errorMessages[] = sprintf(
                    "Line %d: %s",
                    $error->line,
                    trim($error->message)
                );
            }
            
            libxml_clear_errors();
            
            throw new Exception('XML validation failed: ' . implode('; ', $errorMessages));
        }
        
        return true;
    }
}