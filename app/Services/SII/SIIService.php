<?php

namespace App\Services\SII;

use App\Models\Tenant;
use App\Models\TaxDocument;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class SIIService
{
    private SIIAuthService $authService;
    private DTEService $dteService;
    
    public function __construct(
        SIIAuthService $authService,
        DTEService $dteService
    ) {
        $this->authService = $authService;
        $this->dteService = $dteService;
    }

    /**
     * Process tax document for SII
     *
     * @param TaxDocument $document
     * @return array
     * @throws Exception
     */
    public function processInvoice(TaxDocument $document): array
    {
        try {
            // Get tenant from authenticated user
            $tenant = Auth::user()->tenant;
            
            // Get authentication token
            $token = $this->getAuthToken($tenant);
            
            // Create DTE
            $dteResult = $this->dteService->createDTE($document, $tenant);
            
            // Send DTE to SII
            $sendResult = $this->dteService->sendDTE($document, $token);
            
            return [
                'success' => true,
                'folio' => $dteResult['folio'],
                'tipo_dte' => $dteResult['tipo_dte'],
                'tracking_id' => $sendResult['tracking_id'],
                'message' => 'Tax document processed successfully',
            ];
        } catch (Exception $e) {
            Log::error('Error processing tax document for SII', [
                'document_id' => $document->id,
                'tenant_id' => Auth::user()->tenant_id,
                'error' => $e->getMessage(),
            ]);
            
            throw new Exception('Failed to process tax document: ' . $e->getMessage());
        }
    }

    /**
     * Process batch of tax documents
     *
     * @param array $documents
     * @param Tenant $tenant
     * @return array
     * @throws Exception
     */
    public function processBatch(array $documents, Tenant $tenant): array
    {
        try {
            $results = [];
            $errors = [];
            
            // Get authentication token
            $token = $this->getAuthToken($tenant);
            
            foreach ($documents as $document) {
                try {
                    // Create DTE for each document
                    $dteResult = $this->dteService->createDTE($document, $tenant);
                    
                    $results[] = [
                        'document_id' => $document->id,
                        'success' => true,
                        'folio' => $dteResult['folio'],
                        'tipo_dte' => $dteResult['tipo_dte'],
                    ];
                } catch (Exception $e) {
                    $errors[] = [
                        'document_id' => $document->id,
                        'error' => $e->getMessage(),
                    ];
                }
            }
            
            // Send all DTEs together
            if (!empty($results)) {
                try {
                    $successfulDocuments = array_filter($documents, function ($document) use ($results) {
                        return collect($results)->firstWhere('document_id', $document->id) !== null;
                    });
                    
                    $sendResult = $this->sendBatchDTE($successfulDocuments, $token);
                    
                    return [
                        'success' => true,
                        'processed' => count($results),
                        'errors' => $errors,
                        'tracking_id' => $sendResult['tracking_id'],
                    ];
                } catch (Exception $e) {
                    Log::error('Error sending batch to SII', [
                        'error' => $e->getMessage(),
                    ]);
                    
                    throw new Exception('Failed to send batch: ' . $e->getMessage());
                }
            }
            
            return [
                'success' => false,
                'processed' => 0,
                'errors' => $errors,
            ];
        } catch (Exception $e) {
            Log::error('Error processing batch for SII', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
            
            throw new Exception('Failed to process batch: ' . $e->getMessage());
        }
    }

    /**
     * Check tax document status
     *
     * @param TaxDocument $document
     * @param Tenant $tenant
     * @return array
     * @throws Exception
     */
    public function checkInvoiceStatus(TaxDocument $document, Tenant $tenant): array
    {
        try {
            if (!$document->sii_track_id) {
                throw new Exception('Tax document has no tracking ID');
            }
            
            // Get authentication token
            $token = $this->getAuthToken($tenant);
            
            // Check status
            $status = $this->dteService->checkDTEStatus($document->sii_track_id, $token, $tenant);
            
            // Update document status based on response
            $this->updateInvoiceStatus($document, $status);
            
            return $status;
        } catch (Exception $e) {
            Log::error('Error checking tax document status', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);
            
            throw new Exception('Failed to check tax document status: ' . $e->getMessage());
        }
    }

    /**
     * Validate tenant configuration for SII
     *
     * @param Tenant $tenant
     * @return array
     */
    public function validateCompanyConfiguration(Tenant $tenant): array
    {
        $errors = [];
        $warnings = [];
        
        // Check required fields
        if (empty($tenant->rut)) {
            $errors[] = 'Tenant RUT is required';
        }
        
        if (empty($tenant->name)) {
            $errors[] = 'Tenant name is required';
        }
        
        if (empty($tenant->business_activity)) {
            $errors[] = 'Business activity is required';
        }
        
        if (empty($tenant->economic_activity_code)) {
            $errors[] = 'Economic activity code is required';
        }
        
        if (empty($tenant->address)) {
            $errors[] = 'Tenant address is required';
        }
        
        if (empty($tenant->commune)) {
            $errors[] = 'Tenant commune is required';
        }
        
        if (empty($tenant->sii_resolution_date)) {
            $errors[] = 'SII resolution date is required';
        }
        
        if (empty($tenant->sii_resolution_number)) {
            $errors[] = 'SII resolution number is required';
        }
        
        // Check certificate files
        $certificatePath = storage_path('app/sii/certificates/' . $tenant->id . '/cert.pem');
        $privateKeyPath = storage_path('app/sii/certificates/' . $tenant->id . '/key.pem');
        
        if (!file_exists($certificatePath)) {
            $errors[] = 'Certificate file not found';
        }
        
        if (!file_exists($privateKeyPath)) {
            $errors[] = 'Private key file not found';
        }
        
        // Optional fields warnings
        if (empty($tenant->authorized_sender_rut)) {
            $warnings[] = 'Authorized sender RUT not configured, using tenant RUT';
        }
        
        if (empty($tenant->branch_code)) {
            $warnings[] = 'Branch code not configured';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Upload digital certificate
     *
     * @param Tenant $tenant
     * @param string $certificateContent
     * @param string $privateKeyContent
     * @param string|null $password
     * @return bool
     * @throws Exception
     */
    public function uploadCertificate(
        Tenant $tenant,
        string $certificateContent,
        string $privateKeyContent,
        ?string $password = null
    ): bool {
        try {
            $certificateDir = storage_path('app/sii/certificates/' . $tenant->id);
            
            // Create directory if it doesn't exist
            if (!is_dir($certificateDir)) {
                mkdir($certificateDir, 0755, true);
            }
            
            // Save certificate
            file_put_contents($certificateDir . '/cert.pem', $certificateContent);
            
            // Save private key (optionally decrypt if password provided)
            if ($password) {
                // Decrypt private key if needed
                $privateKey = openssl_pkey_get_private($privateKeyContent, $password);
                if ($privateKey === false) {
                    throw new Exception('Invalid private key or password');
                }
                openssl_pkey_export($privateKey, $privateKeyPem);
                file_put_contents($certificateDir . '/key.pem', $privateKeyPem);
            } else {
                file_put_contents($certificateDir . '/key.pem', $privateKeyContent);
            }
            
            // Set proper permissions
            chmod($certificateDir . '/cert.pem', 0600);
            chmod($certificateDir . '/key.pem', 0600);
            
            return true;
        } catch (Exception $e) {
            Log::error('Error uploading certificate', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
            
            throw new Exception('Failed to upload certificate: ' . $e->getMessage());
        }
    }

    /**
     * Get authentication token with caching
     *
     * @param Tenant $tenant
     * @return string
     * @throws Exception
     */
    private function getAuthToken(Tenant $tenant): string
    {
        $cacheKey = 'sii_token_' . $tenant->id;
        
        return Cache::remember($cacheKey, now()->addMinutes(50), function () {
            return $this->authService->getAuthToken();
        });
    }

    /**
     * Send batch DTE
     *
     * @param array $documents
     * @param string $token
     * @return array
     * @throws Exception
     */
    private function sendBatchDTE(array $documents, string $token): array
    {
        if (empty($documents)) {
            throw new Exception('No documents to send');
        }
        
        // Use the first document to get the tenant
        $tenant = $documents[0]->tenant;
        
        // For batch sending, we need to send each document individually
        // since the DTEService handles the EnvioDTE creation internally
        $response = null;
        $trackingId = null;
        
        // For now, send the first document and get tracking ID
        if (!empty($documents)) {
            $sendResult = $this->dteService->sendDTE($documents[0], $token);
            $trackingId = $sendResult['tracking_id'] ?? null;
            $response = $sendResult['response'] ?? null;
        }
        if ($trackingId) {
            foreach ($documents as $document) {
                $document->update([
                    'sii_status' => 'sent',
                    'sent_at' => now(),
                    'sii_track_id' => $trackingId,
                ]);
            }
        }
        
        return [
            'success' => true,
            'tracking_id' => $trackingId,
            'response' => $response,
        ];
    }

    /**
     * Update tax document status based on SII response
     *
     * @param TaxDocument $document
     * @param array $status
     */
    private function updateInvoiceStatus(TaxDocument $document, array $status): void
    {
        $estado = strtolower($status['estado'] ?? '');
        
        $statusMap = [
            'aceptado' => 'accepted',
            'rechazado' => 'rejected',
            'reparo' => 'accepted_with_objections',
            'procesando' => 'processing',
        ];
        
        $dteStatus = $statusMap[$estado] ?? 'unknown';
        
        $document->update([
            'sii_status' => $dteStatus,
            'sii_response' => $status['glosa'] ?? '',
            'checked_at' => now(),
        ]);
        
        // Log any errors
        if (!empty($status['detalles'])) {
            Log::warning('DTE has errors', [
                'document_id' => $document->id,
                'errors' => $status['detalles'],
            ]);
        }
    }

    /**
     * Generate test set for certification
     *
     * @param Tenant $tenant
     * @param string $setType
     * @return array
     * @throws Exception
     */
    public function generateTestSet(Tenant $tenant, string $setType = 'basico'): array
    {
        // This method would generate the required test documents for SII certification
        // Implementation depends on the specific test set requirements
        
        throw new Exception('Test set generation not implemented yet');
    }
}