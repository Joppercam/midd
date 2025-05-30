<?php

namespace App\Services\SII;

use DOMDocument;
use DOMXPath;
use Exception;
use Illuminate\Support\Facades\Log;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

class XMLSignerService
{
    private string $certificatePath;
    private string $privateKeyPath;
    private string $privateKeyPassword;

    public function __construct()
    {
        $this->certificatePath = storage_path('app/sii/certificates/certificate.pem');
        $this->privateKeyPath = storage_path('app/sii/certificates/private_key.pem');
        $this->privateKeyPassword = config('sii.certificate_password', '');
    }

    /**
     * Sign XML document with digital certificate
     *
     * @param string $xml
     * @param string $referenceId
     * @return string
     * @throws Exception
     */
    public function signXML(string $xml, string $referenceId = null): string
    {
        try {
            // Validate certificate files exist
            $this->validateCertificateFiles();

            // Load XML document
            $doc = new DOMDocument();
            $doc->preserveWhiteSpace = false;
            $doc->formatOutput = true;
            $doc->loadXML($xml);

            // Create signature object
            $objDSig = new XMLSecurityDSig();

            // Configure signature method
            $objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
            
            // Add reference - sign the entire document or specific element
            if ($referenceId) {
                $objDSig->addReference(
                    $doc,
                    XMLSecurityDSig::SHA1,
                    ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
                    ['force_uri' => true, 'id_name' => 'ID', 'overwrite' => false]
                );
            } else {
                $objDSig->addReference(
                    $doc,
                    XMLSecurityDSig::SHA1,
                    ['http://www.w3.org/2000/09/xmldsig#enveloped-signature']
                );
            }

            // Create key object
            $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, ['type' => 'private']);

            // Load private key
            $privateKeyContent = file_get_contents($this->privateKeyPath);
            $objKey->loadKey($privateKeyContent, false);

            // If password protected
            if (!empty($this->privateKeyPassword)) {
                $objKey->passphrase = $this->privateKeyPassword;
            }

            // Sign the document
            $objDSig->sign($objKey);

            // Add certificate to signature
            $certificateContent = file_get_contents($this->certificatePath);
            $objDSig->add509Cert($certificateContent, true);

            // Append signature to document
            $this->appendSignature($doc, $objDSig);

            return $doc->saveXML();

        } catch (Exception $e) {
            Log::error('Error signing XML', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new Exception('Failed to sign XML: ' . $e->getMessage());
        }
    }

    /**
     * Sign multiple DTEs for batch sending
     *
     * @param array $dtes Array of XML strings
     * @return array Array of signed XML strings
     * @throws Exception
     */
    public function signMultipleDTEs(array $dtes): array
    {
        $signedDtes = [];

        foreach ($dtes as $index => $dte) {
            try {
                $signedDtes[] = $this->signXML($dte);
            } catch (Exception $e) {
                throw new Exception("Failed to sign DTE at index {$index}: " . $e->getMessage());
            }
        }

        return $signedDtes;
    }

    /**
     * Verify XML signature
     *
     * @param string $xml
     * @return bool
     * @throws Exception
     */
    public function verifySignature(string $xml): bool
    {
        try {
            $doc = new DOMDocument();
            $doc->loadXML($xml);

            $objXMLSecDSig = new XMLSecurityDSig();

            // Find signature
            $objDSig = $objXMLSecDSig->locateSignature($doc);
            if (!$objDSig) {
                throw new Exception('Cannot locate signature in document');
            }

            // Canonicalize
            $objXMLSecDSig->canonicalizeSignedInfo();

            // Validate reference
            if (!$objXMLSecDSig->validateReference()) {
                throw new Exception('Reference validation failed');
            }

            // Get public key from certificate
            $objKey = $objXMLSecDSig->locateKey();
            if (!$objKey) {
                throw new Exception('Cannot locate key in signature');
            }

            // Load certificate
            $certificateContent = file_get_contents($this->certificatePath);
            $objKey->loadKey($certificateContent, false, true);

            // Verify signature
            return $objXMLSecDSig->verify($objKey) === 1;

        } catch (Exception $e) {
            Log::error('Error verifying signature', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Extract certificate information
     *
     * @return array
     * @throws Exception
     */
    public function getCertificateInfo(): array
    {
        $this->validateCertificateFiles();

        $certificateContent = file_get_contents($this->certificatePath);
        $certInfo = openssl_x509_parse($certificateContent);

        if (!$certInfo) {
            throw new Exception('Failed to parse certificate');
        }

        return [
            'subject' => $certInfo['subject'] ?? [],
            'issuer' => $certInfo['issuer'] ?? [],
            'valid_from' => date('Y-m-d H:i:s', $certInfo['validFrom_time_t'] ?? 0),
            'valid_to' => date('Y-m-d H:i:s', $certInfo['validTo_time_t'] ?? 0),
            'serial_number' => $certInfo['serialNumber'] ?? '',
            'is_valid' => $this->isCertificateValid($certInfo)
        ];
    }

    /**
     * Check if certificate is currently valid
     *
     * @param array $certInfo
     * @return bool
     */
    private function isCertificateValid(array $certInfo): bool
    {
        $now = time();
        $validFrom = $certInfo['validFrom_time_t'] ?? 0;
        $validTo = $certInfo['validTo_time_t'] ?? 0;

        return $now >= $validFrom && $now <= $validTo;
    }

    /**
     * Validate certificate files exist
     *
     * @throws Exception
     */
    private function validateCertificateFiles(): void
    {
        if (!file_exists($this->certificatePath)) {
            throw new Exception('Certificate file not found: ' . $this->certificatePath);
        }

        if (!file_exists($this->privateKeyPath)) {
            throw new Exception('Private key file not found: ' . $this->privateKeyPath);
        }
    }

    /**
     * Append signature to document
     *
     * @param DOMDocument $doc
     * @param XMLSecurityDSig $objDSig
     * @throws Exception
     */
    private function appendSignature(DOMDocument $doc, XMLSecurityDSig $objDSig): void
    {
        // Find existing signature placeholder
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
        
        $signaturePlaceholder = $xpath->query('//ds:Signature')->item(0);
        
        if ($signaturePlaceholder) {
            // Replace placeholder with actual signature
            $signaturePlaceholder->parentNode->replaceChild(
                $doc->importNode($objDSig->sigNode, true),
                $signaturePlaceholder
            );
        } else {
            // Append signature to root element
            $doc->documentElement->appendChild($objDSig->sigNode);
        }
    }

    /**
     * Convert P12 certificate to PEM format
     *
     * @param string $p12Path
     * @param string $p12Password
     * @param string $outputPath
     * @return bool
     * @throws Exception
     */
    public function convertP12ToPEM(string $p12Path, string $p12Password, string $outputPath): bool
    {
        try {
            if (!file_exists($p12Path)) {
                throw new Exception('P12 file not found: ' . $p12Path);
            }

            // Read P12 file
            $p12Content = file_get_contents($p12Path);
            
            // Parse P12
            $certs = [];
            if (!openssl_pkcs12_read($p12Content, $certs, $p12Password)) {
                throw new Exception('Failed to read P12 certificate. Invalid password?');
            }

            // Ensure output directory exists
            $outputDir = dirname($outputPath);
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            // Save certificate
            $certPath = $outputPath . '/certificate.pem';
            file_put_contents($certPath, $certs['cert']);
            chmod($certPath, 0600);

            // Save private key
            $keyPath = $outputPath . '/private_key.pem';
            file_put_contents($keyPath, $certs['pkey']);
            chmod($keyPath, 0600);

            // Save CA certificates if present
            if (!empty($certs['extracerts'])) {
                $caPath = $outputPath . '/ca_bundle.pem';
                file_put_contents($caPath, implode("\n", $certs['extracerts']));
                chmod($caPath, 0600);
            }

            Log::info('P12 certificate converted successfully', [
                'output_path' => $outputPath
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Error converting P12 certificate', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}