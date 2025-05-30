<?php

namespace App\Modules\Invoicing\Services;

use App\Models\Tenant;
use App\Services\SII\XMLSignerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class CertificateService
{
    protected $xmlSigner;

    public function __construct(XMLSignerService $xmlSigner)
    {
        $this->xmlSigner = $xmlSigner;
    }

    public function getCertificateInfo(Tenant $tenant): array
    {
        try {
            $certificatePath = $this->getCertificatePath($tenant);
            
            if (!file_exists($certificatePath)) {
                return [
                    'exists' => false,
                    'info' => null,
                    'expires_soon' => false,
                    'days_until_expiry' => null,
                ];
            }

            // Update signer paths temporarily to read this tenant's certificate
            $this->updateSignerPaths($tenant);
            
            $info = $this->xmlSigner->getCertificateInfo();
            
            // Calculate expiry information
            $expiryDate = Carbon::parse($info['validTo']);
            $daysUntilExpiry = now()->diffInDays($expiryDate, false);
            $expiresSoon = $daysUntilExpiry <= 30 && $daysUntilExpiry >= 0;

            return [
                'exists' => true,
                'info' => array_merge($info, [
                    'file_path' => $certificatePath,
                    'file_size' => filesize($certificatePath),
                    'uploaded_at' => Carbon::createFromTimestamp(filemtime($certificatePath)),
                ]),
                'expires_soon' => $expiresSoon,
                'days_until_expiry' => $daysUntilExpiry,
            ];

        } catch (Exception $e) {
            Log::error('Error getting certificate info', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'exists' => false,
                'info' => null,
                'expires_soon' => false,
                'days_until_expiry' => null,
            ];
        }
    }

    public function uploadCertificate(Tenant $tenant, UploadedFile $file, string $password, ?string $alias = null, bool $isRenewal = false): array
    {
        try {
            // Create tenant certificate directory
            $certDir = $this->getCertificateDirectory($tenant);
            if (!is_dir($certDir)) {
                mkdir($certDir, 0755, true);
            }

            // Save uploaded file temporarily
            $tempPath = $file->storeAs('temp', 'cert_' . $tenant->id . '_' . time() . '.p12');
            $p12Path = storage_path('app/' . $tempPath);

            // Validate certificate before processing
            $validationResult = $this->validateP12Certificate($p12Path, $password);
            if (!$validationResult['valid']) {
                Storage::delete($tempPath);
                return [
                    'success' => false,
                    'message' => $validationResult['message'],
                ];
            }

            // Define output paths
            $certificatePath = $this->getCertificatePath($tenant);
            $keyPath = $this->getKeyPath($tenant);

            // Backup existing certificate if this is a renewal
            if ($isRenewal && file_exists($certificatePath)) {
                $this->createCertificateBackup($tenant);
            }

            // Convert P12 to PEM format
            $conversionResult = $this->convertP12ToPEM($p12Path, $password, $certificatePath, $keyPath, $alias);
            
            // Clean up temporary file
            Storage::delete($tempPath);

            if (!$conversionResult['success']) {
                return [
                    'success' => false,
                    'message' => $conversionResult['message'],
                ];
            }

            // Test the certificate
            $testResult = $this->testCertificate($tenant);
            if (!$testResult['success']) {
                // Remove the uploaded certificate if test fails
                $this->deleteCertificateFiles($tenant);
                
                return [
                    'success' => false,
                    'message' => 'El certificado se subió pero falló la prueba de validación: ' . $testResult['message'],
                ];
            }

            // Update tenant certificate info
            $this->updateTenantCertificateInfo($tenant);

            // Log the upload
            $this->logCertificateAction($tenant, 'uploaded', [
                'is_renewal' => $isRenewal,
                'alias' => $alias,
                'file_size' => $file->getSize(),
            ]);

            return [
                'success' => true,
                'message' => $isRenewal ? 'Certificado renovado exitosamente.' : 'Certificado subido exitosamente.',
            ];

        } catch (Exception $e) {
            Log::error('Certificate upload error', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Error interno al procesar el certificado.',
            ];
        }
    }

    public function deleteCertificate(Tenant $tenant): array
    {
        try {
            // Create backup before deletion
            $backupResult = $this->createCertificateBackup($tenant);
            
            // Delete certificate files
            $this->deleteCertificateFiles($tenant);

            // Clear tenant certificate info
            $tenant->update([
                'certificate_serial' => null,
                'certificate_subject' => null,
                'certificate_issuer' => null,
                'certificate_valid_from' => null,
                'certificate_valid_to' => null,
            ]);

            // Log the deletion
            $this->logCertificateAction($tenant, 'deleted', [
                'backup_created' => $backupResult['success'],
            ]);

            return [
                'success' => true,
                'message' => 'Certificado eliminado exitosamente.',
            ];

        } catch (Exception $e) {
            Log::error('Certificate deletion error', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error al eliminar el certificado.',
            ];
        }
    }

    public function testCertificate(Tenant $tenant): array
    {
        try {
            $certificatePath = $this->getCertificatePath($tenant);
            $keyPath = $this->getKeyPath($tenant);

            if (!file_exists($certificatePath) || !file_exists($keyPath)) {
                return [
                    'success' => false,
                    'message' => 'Archivos de certificado no encontrados.',
                ];
            }

            // Update signer paths
            $this->updateSignerPaths($tenant);

            // Test certificate validity
            $info = $this->xmlSigner->getCertificateInfo();
            
            // Check if certificate is expired
            $expiryDate = Carbon::parse($info['validTo']);
            if ($expiryDate->isPast()) {
                return [
                    'success' => false,
                    'message' => 'El certificado ha expirado.',
                    'details' => $info,
                ];
            }

            // Test signing capability with a simple XML
            $testXml = '<test>Test XML for certificate validation</test>';
            $signedXml = $this->xmlSigner->signXML($testXml);

            if (empty($signedXml)) {
                return [
                    'success' => false,
                    'message' => 'Error al firmar XML de prueba.',
                ];
            }

            return [
                'success' => true,
                'message' => 'Certificado válido y funcional.',
                'details' => $info,
            ];

        } catch (Exception $e) {
            Log::error('Certificate test error', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error al probar el certificado: ' . $e->getMessage(),
            ];
        }
    }

    public function validateCertificateFile(UploadedFile $file, string $password): array
    {
        try {
            $tempPath = $file->storeAs('temp', 'validate_cert_' . time() . '.p12');
            $p12Path = storage_path('app/' . $tempPath);

            $result = $this->validateP12Certificate($p12Path, $password);
            
            Storage::delete($tempPath);
            
            return $result;

        } catch (Exception $e) {
            return [
                'valid' => false,
                'message' => 'Error al validar el archivo: ' . $e->getMessage(),
            ];
        }
    }

    public function backupCertificate(Tenant $tenant): array
    {
        return $this->createCertificateBackup($tenant);
    }

    public function restoreCertificateBackup(Tenant $tenant): array
    {
        try {
            $backupDir = $this->getBackupDirectory($tenant);
            $backupCertPath = $backupDir . '/certificate.pem';
            $backupKeyPath = $backupDir . '/private_key.pem';

            if (!file_exists($backupCertPath) || !file_exists($backupKeyPath)) {
                return [
                    'success' => false,
                    'message' => 'No se encontró respaldo del certificado.',
                ];
            }

            $certificatePath = $this->getCertificatePath($tenant);
            $keyPath = $this->getKeyPath($tenant);

            copy($backupCertPath, $certificatePath);
            copy($backupKeyPath, $keyPath);

            $this->logCertificateAction($tenant, 'restored');

            return [
                'success' => true,
                'message' => 'Certificado restaurado desde respaldo.',
            ];

        } catch (Exception $e) {
            Log::error('Certificate restore error', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error al restaurar el certificado.',
            ];
        }
    }

    public function getCertificateHistory(Tenant $tenant): array
    {
        // This would typically come from a database table
        // For now, return a simple structure
        return [
            'uploads' => [],
            'renewals' => [],
            'deletions' => [],
            'tests' => [],
        ];
    }

    protected function getCertificateDirectory(Tenant $tenant): string
    {
        return storage_path('app/sii/certificates/' . $tenant->id);
    }

    protected function getCertificatePath(Tenant $tenant): string
    {
        return $this->getCertificateDirectory($tenant) . '/certificate.pem';
    }

    protected function getKeyPath(Tenant $tenant): string
    {
        return $this->getCertificateDirectory($tenant) . '/private_key.pem';
    }

    protected function getBackupDirectory(Tenant $tenant): string
    {
        return $this->getCertificateDirectory($tenant) . '/backup';
    }

    protected function updateSignerPaths(Tenant $tenant): void
    {
        $reflectionClass = new \ReflectionClass($this->xmlSigner);
        
        $certProperty = $reflectionClass->getProperty('certificatePath');
        $certProperty->setAccessible(true);
        $certProperty->setValue($this->xmlSigner, $this->getCertificatePath($tenant));
        
        $keyProperty = $reflectionClass->getProperty('privateKeyPath');
        $keyProperty->setAccessible(true);
        $keyProperty->setValue($this->xmlSigner, $this->getKeyPath($tenant));
    }

    protected function validateP12Certificate(string $p12Path, string $password): array
    {
        try {
            $p12Content = file_get_contents($p12Path);
            
            if (!openssl_pkcs12_read($p12Content, $certs, $password)) {
                return [
                    'valid' => false,
                    'message' => 'Contraseña incorrecta o archivo de certificado inválido.',
                ];
            }

            // Validate certificate content
            $certInfo = openssl_x509_parse($certs['cert']);
            if (!$certInfo) {
                return [
                    'valid' => false,
                    'message' => 'No se pudo leer la información del certificado.',
                ];
            }

            // Check if certificate is expired
            $validTo = Carbon::createFromTimestamp($certInfo['validTo_time_t']);
            if ($validTo->isPast()) {
                return [
                    'valid' => false,
                    'message' => 'El certificado ha expirado.',
                    'info' => $certInfo,
                ];
            }

            // Check if certificate is for digital signing
            $warnings = [];
            if (!isset($certInfo['extensions']['keyUsage']) || 
                strpos($certInfo['extensions']['keyUsage'], 'Digital Signature') === false) {
                $warnings[] = 'El certificado podría no estar habilitado para firma digital.';
            }

            return [
                'valid' => true,
                'message' => 'Certificado válido.',
                'info' => $certInfo,
                'warnings' => $warnings,
            ];

        } catch (Exception $e) {
            return [
                'valid' => false,
                'message' => 'Error al validar el certificado: ' . $e->getMessage(),
            ];
        }
    }

    protected function convertP12ToPEM(string $p12Path, string $password, string $certPath, string $keyPath, ?string $alias = null): array
    {
        try {
            $p12Content = file_get_contents($p12Path);
            
            if (!openssl_pkcs12_read($p12Content, $certs, $password)) {
                return [
                    'success' => false,
                    'message' => 'Error al leer el archivo P12.',
                ];
            }

            // Save certificate
            if (!file_put_contents($certPath, $certs['cert'])) {
                return [
                    'success' => false,
                    'message' => 'Error al guardar el certificado.',
                ];
            }

            // Save private key
            if (!file_put_contents($keyPath, $certs['pkey'])) {
                unlink($certPath); // Clean up certificate file
                return [
                    'success' => false,
                    'message' => 'Error al guardar la clave privada.',
                ];
            }

            // Set appropriate permissions
            chmod($certPath, 0600);
            chmod($keyPath, 0600);

            return [
                'success' => true,
                'message' => 'Certificado convertido exitosamente.',
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error en la conversión: ' . $e->getMessage(),
            ];
        }
    }

    protected function deleteCertificateFiles(Tenant $tenant): void
    {
        $certificatePath = $this->getCertificatePath($tenant);
        $keyPath = $this->getKeyPath($tenant);

        if (file_exists($certificatePath)) {
            unlink($certificatePath);
        }

        if (file_exists($keyPath)) {
            unlink($keyPath);
        }
    }

    protected function createCertificateBackup(Tenant $tenant): array
    {
        try {
            $certificatePath = $this->getCertificatePath($tenant);
            $keyPath = $this->getKeyPath($tenant);

            if (!file_exists($certificatePath) || !file_exists($keyPath)) {
                return [
                    'success' => false,
                    'message' => 'No hay certificado para respaldar.',
                ];
            }

            $backupDir = $this->getBackupDirectory($tenant);
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            $timestamp = now()->format('Y-m-d_H-i-s');
            $backupCertPath = $backupDir . '/certificate_' . $timestamp . '.pem';
            $backupKeyPath = $backupDir . '/private_key_' . $timestamp . '.pem';

            copy($certificatePath, $backupCertPath);
            copy($keyPath, $backupKeyPath);

            // Also create current backup (overwrites previous)
            copy($certificatePath, $backupDir . '/certificate.pem');
            copy($keyPath, $backupDir . '/private_key.pem');

            return [
                'success' => true,
                'message' => 'Respaldo creado exitosamente.',
            ];

        } catch (Exception $e) {
            Log::error('Certificate backup error', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error al crear respaldo.',
            ];
        }
    }

    protected function updateTenantCertificateInfo(Tenant $tenant): void
    {
        try {
            $this->updateSignerPaths($tenant);
            $info = $this->xmlSigner->getCertificateInfo();

            $tenant->update([
                'certificate_serial' => $info['serialNumber'] ?? null,
                'certificate_subject' => $info['name'] ?? null,
                'certificate_issuer' => $info['issuer'] ?? null,
                'certificate_valid_from' => isset($info['validFrom']) ? Carbon::parse($info['validFrom']) : null,
                'certificate_valid_to' => isset($info['validTo']) ? Carbon::parse($info['validTo']) : null,
            ]);

        } catch (Exception $e) {
            Log::error('Error updating tenant certificate info', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function logCertificateAction(Tenant $tenant, string $action, array $context = []): void
    {
        Log::info("Certificate {$action}", array_merge([
            'tenant_id' => $tenant->id,
            'action' => $action,
            'timestamp' => now(),
        ], $context));
    }
}