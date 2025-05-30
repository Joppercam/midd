<?php

namespace App\Http\Controllers\SII;

use App\Http\Controllers\Controller;
use App\Services\SII\XMLSignerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Exception;

class CertificateController extends Controller
{
    private XMLSignerService $xmlSigner;

    public function __construct(XMLSignerService $xmlSigner)
    {
        $this->xmlSigner = $xmlSigner;
    }

    /**
     * Show certificate management page
     */
    public function index()
    {
        $tenant = auth()->user()->tenant;
        $certificateInfo = null;
        $hasCertificate = false;

        try {
            // Check if certificate exists for this tenant
            $certificatePath = storage_path('app/sii/certificates/' . $tenant->id . '/certificate.pem');
            if (file_exists($certificatePath)) {
                $hasCertificate = true;
                
                // Update signer paths temporarily
                $reflectionClass = new \ReflectionClass($this->xmlSigner);
                $certProperty = $reflectionClass->getProperty('certificatePath');
                $certProperty->setAccessible(true);
                $certProperty->setValue($this->xmlSigner, $certificatePath);
                
                $certificateInfo = $this->xmlSigner->getCertificateInfo();
            }
        } catch (Exception $e) {
            Log::error('Error reading certificate info: ' . $e->getMessage());
        }

        return Inertia::render('SII/Certificate', [
            'hasCertificate' => $hasCertificate,
            'certificateInfo' => $certificateInfo,
        ]);
    }

    /**
     * Upload and process certificate
     */
    public function upload(Request $request)
    {
        $request->validate([
            'certificate' => 'required|file|mimes:p12,pfx|max:2048',
            'password' => 'required|string',
        ]);

        $tenant = auth()->user()->tenant;

        try {
            // Create tenant certificate directory
            $certDir = storage_path('app/sii/certificates/' . $tenant->id);
            if (!is_dir($certDir)) {
                mkdir($certDir, 0755, true);
            }

            // Save uploaded file temporarily
            $uploadedFile = $request->file('certificate');
            $tempPath = $uploadedFile->storeAs('temp', 'cert_' . $tenant->id . '.p12');
            $p12Path = storage_path('app/' . $tempPath);

            // Convert P12 to PEM
            $this->xmlSigner->convertP12ToPEM(
                $p12Path,
                $request->password,
                $certDir
            );

            // Remove temporary file
            unlink($p12Path);

            // Store certificate password encrypted in tenant settings
            $tenant->update([
                'certificate_password' => encrypt($request->password),
                'certificate_uploaded_at' => now(),
            ]);

            return redirect()->route('sii.certificate.index')
                ->with('success', 'Certificado digital cargado correctamente.');

        } catch (Exception $e) {
            Log::error('Error uploading certificate', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()
                ->withErrors(['certificate' => 'Error al procesar el certificado: ' . $e->getMessage()]);
        }
    }

    /**
     * Delete certificate
     */
    public function destroy()
    {
        $tenant = auth()->user()->tenant;

        try {
            $certDir = storage_path('app/sii/certificates/' . $tenant->id);
            
            // Delete certificate files
            $files = ['certificate.pem', 'private_key.pem', 'ca_bundle.pem'];
            foreach ($files as $file) {
                $filePath = $certDir . '/' . $file;
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            // Clear certificate info from tenant
            $tenant->update([
                'certificate_password' => null,
                'certificate_uploaded_at' => null,
            ]);

            return redirect()->route('sii.certificate.index')
                ->with('success', 'Certificado digital eliminado correctamente.');

        } catch (Exception $e) {
            Log::error('Error deleting certificate', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()
                ->withErrors(['error' => 'Error al eliminar el certificado.']);
        }
    }

    /**
     * Test certificate by signing a test document
     */
    public function test()
    {
        $tenant = auth()->user()->tenant;

        try {
            // Create test XML
            $testXml = '<?xml version="1.0" encoding="UTF-8"?>
                <TestDocument>
                    <Message>Test de firma digital</Message>
                    <Date>' . now()->toIso8601String() . '</Date>
                    <Tenant>' . $tenant->name . '</Tenant>
                </TestDocument>';

            // Set tenant certificate paths
            $certificatePath = storage_path('app/sii/certificates/' . $tenant->id . '/certificate.pem');
            $privateKeyPath = storage_path('app/sii/certificates/' . $tenant->id . '/private_key.pem');
            
            $reflectionClass = new \ReflectionClass($this->xmlSigner);
            
            $certProperty = $reflectionClass->getProperty('certificatePath');
            $certProperty->setAccessible(true);
            $certProperty->setValue($this->xmlSigner, $certificatePath);
            
            $keyProperty = $reflectionClass->getProperty('privateKeyPath');
            $keyProperty->setAccessible(true);
            $keyProperty->setValue($this->xmlSigner, $privateKeyPath);
            
            $passwordProperty = $reflectionClass->getProperty('privateKeyPassword');
            $passwordProperty->setAccessible(true);
            $passwordProperty->setValue($this->xmlSigner, decrypt($tenant->certificate_password));

            // Sign the test document
            $signedXml = $this->xmlSigner->signXML($testXml);

            // Verify the signature
            $isValid = $this->xmlSigner->verifySignature($signedXml);

            return response()->json([
                'success' => true,
                'message' => 'Certificado probado exitosamente',
                'signature_valid' => $isValid,
                'signed_xml' => base64_encode($signedXml),
            ]);

        } catch (Exception $e) {
            Log::error('Error testing certificate', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al probar el certificado: ' . $e->getMessage(),
            ], 500);
        }
    }
}