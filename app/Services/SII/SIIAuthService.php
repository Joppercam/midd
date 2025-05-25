<?php

namespace App\Services\SII;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use phpseclib3\Crypt\RSA;
use phpseclib3\File\X509;

class SIIAuthService
{
    private string $authUrl = 'https://maullin.sii.cl/cgi_AUT2000/CAutInicio.cgi';
    private string $tokenUrl = 'https://maullin.sii.cl/cgi_AUT2000/CAutAvanzada.cgi';
    private ?string $certificatePath = null;
    private ?string $privateKeyPath = null;
    private ?string $privateKeyPassword = null;

    public function __construct()
    {
        $this->certificatePath = config('sii.certificate_path');
        $this->privateKeyPath = config('sii.private_key_path');
        $this->privateKeyPassword = config('sii.private_key_password');
    }

    /**
     * Get authentication token from SII
     *
     * @return string
     * @throws Exception
     */
    public function getAuthToken(): string
    {
        try {
            // Step 1: Get seed from SII
            $seed = $this->getSeed();
            
            // Step 2: Sign the seed with digital certificate
            $signedSeed = $this->signSeed($seed);
            
            // Step 3: Get token using signed seed
            $token = $this->getToken($signedSeed);
            
            return $token;
        } catch (Exception $e) {
            Log::error('SII Authentication failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new Exception('Failed to authenticate with SII: ' . $e->getMessage());
        }
    }

    /**
     * Get seed from SII
     *
     * @return string
     * @throws Exception
     */
    private function getSeed(): string
    {
        $response = Http::timeout(30)->get($this->authUrl);
        
        if (!$response->successful()) {
            throw new Exception('Failed to get seed from SII: ' . $response->status());
        }

        $xml = simplexml_load_string($response->body());
        
        if ($xml === false) {
            throw new Exception('Invalid XML response from SII');
        }

        $namespaces = $xml->getNamespaces(true);
        $xml->registerXPathNamespace('SII', $namespaces['SII'] ?? 'http://www.sii.cl/XMLSchema');
        
        $seedNodes = $xml->xpath('//SEMILLA');
        
        if (empty($seedNodes)) {
            throw new Exception('Seed not found in SII response');
        }

        return (string) $seedNodes[0];
    }

    /**
     * Sign seed with digital certificate
     *
     * @param string $seed
     * @return string
     * @throws Exception
     */
    private function signSeed(string $seed): string
    {
        if (!file_exists($this->certificatePath)) {
            throw new Exception('Certificate file not found: ' . $this->certificatePath);
        }

        if (!file_exists($this->privateKeyPath)) {
            throw new Exception('Private key file not found: ' . $this->privateKeyPath);
        }

        // Load certificate
        $x509 = new X509();
        $x509->loadX509(file_get_contents($this->certificatePath));

        // Load private key
        $privateKey = RSA::load(
            file_get_contents($this->privateKeyPath),
            $this->privateKeyPassword
        );

        // Create the signed seed XML
        $signedXml = $this->createSignedSeedXml($seed, $x509, $privateKey);

        return base64_encode($signedXml);
    }

    /**
     * Create signed seed XML document
     *
     * @param string $seed
     * @param X509 $x509
     * @param RSA $privateKey
     * @return string
     */
    private function createSignedSeedXml(string $seed, X509 $x509, RSA $privateKey): string
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        
        // Create root element
        $root = $doc->createElement('getToken');
        $doc->appendChild($root);
        
        // Create item element
        $item = $doc->createElement('item');
        $root->appendChild($item);
        
        // Add seed
        $semilla = $doc->createElement('Semilla', $seed);
        $item->appendChild($semilla);
        
        // Create signature
        $privateKey->withPadding(RSA::SIGNATURE_PKCS1);
        $signature = $privateKey->sign($seed);
        
        // Create Signature element
        $signatureElement = $doc->createElement('Signature');
        $signatureElement->setAttribute('xmlns', 'http://www.w3.org/2000/09/xmldsig#');
        $doc->documentElement->appendChild($signatureElement);
        
        // SignedInfo
        $signedInfo = $doc->createElement('SignedInfo');
        $signatureElement->appendChild($signedInfo);
        
        $canonMethod = $doc->createElement('CanonicalizationMethod');
        $canonMethod->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $signedInfo->appendChild($canonMethod);
        
        $signMethod = $doc->createElement('SignatureMethod');
        $signMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#rsa-sha1');
        $signedInfo->appendChild($signMethod);
        
        $reference = $doc->createElement('Reference');
        $reference->setAttribute('URI', '');
        $signedInfo->appendChild($reference);
        
        $transforms = $doc->createElement('Transforms');
        $reference->appendChild($transforms);
        
        $transform = $doc->createElement('Transform');
        $transform->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
        $transforms->appendChild($transform);
        
        $digestMethod = $doc->createElement('DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
        $reference->appendChild($digestMethod);
        
        $digestValue = $doc->createElement('DigestValue', base64_encode(sha1($seed, true)));
        $reference->appendChild($digestValue);
        
        // SignatureValue
        $signatureValue = $doc->createElement('SignatureValue', base64_encode($signature));
        $signatureElement->appendChild($signatureValue);
        
        // KeyInfo
        $keyInfo = $doc->createElement('KeyInfo');
        $signatureElement->appendChild($keyInfo);
        
        $keyValue = $doc->createElement('KeyValue');
        $keyInfo->appendChild($keyValue);
        
        $rsaKeyValue = $doc->createElement('RSAKeyValue');
        $keyValue->appendChild($rsaKeyValue);
        
        $publicKey = $privateKey->getPublicKey();
        $modulus = $doc->createElement('Modulus', base64_encode($publicKey->toString('Raw')));
        $rsaKeyValue->appendChild($modulus);
        
        $exponent = $doc->createElement('Exponent', 'AQAB');
        $rsaKeyValue->appendChild($exponent);
        
        $x509Data = $doc->createElement('X509Data');
        $keyInfo->appendChild($x509Data);
        
        $x509Certificate = $doc->createElement('X509Certificate', base64_encode($x509->saveX509($x509->currentCert)));
        $x509Data->appendChild($x509Certificate);
        
        return $doc->saveXML();
    }

    /**
     * Get token using signed seed
     *
     * @param string $signedSeed
     * @return string
     * @throws Exception
     */
    private function getToken(string $signedSeed): string
    {
        $response = Http::timeout(30)
            ->asForm()
            ->post($this->tokenUrl, [
                'pszXml' => $signedSeed
            ]);
        
        if (!$response->successful()) {
            throw new Exception('Failed to get token from SII: ' . $response->status());
        }

        $xml = simplexml_load_string($response->body());
        
        if ($xml === false) {
            throw new Exception('Invalid XML response from SII');
        }

        $namespaces = $xml->getNamespaces(true);
        $xml->registerXPathNamespace('SII', $namespaces['SII'] ?? 'http://www.sii.cl/XMLSchema');
        
        $tokenNodes = $xml->xpath('//TOKEN');
        
        if (empty($tokenNodes)) {
            throw new Exception('Token not found in SII response');
        }

        return (string) $tokenNodes[0];
    }

    /**
     * Verify if current token is valid
     *
     * @param string $token
     * @return bool
     */
    public function isTokenValid(string $token): bool
    {
        // Token validation logic
        // This is a simplified check - implement according to SII requirements
        return !empty($token) && strlen($token) > 10;
    }
}