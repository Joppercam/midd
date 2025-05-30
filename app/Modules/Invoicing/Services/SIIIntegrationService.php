<?php

namespace App\Modules\Invoicing\Services;

use App\Models\Tenant;
use App\Models\TaxDocument;
use App\Models\SiiEventLog;
use App\Services\SII\SIIService;
use App\Services\SII\SIIAuthService;
use App\Services\SII\DTEService;
use App\Services\SII\AdvancedSIIService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SIIIntegrationService
{
    protected $siiService;
    protected $siiAuthService;
    protected $dteService;
    protected $advancedSIIService;

    public function __construct(
        SIIService $siiService,
        SIIAuthService $siiAuthService,
        DTEService $dteService,
        AdvancedSIIService $advancedSIIService
    ) {
        $this->siiService = $siiService;
        $this->siiAuthService = $siiAuthService;
        $this->dteService = $dteService;
        $this->advancedSIIService = $advancedSIIService;
    }

    public function getConfiguration(Tenant $tenant): array
    {
        return [
            'resolution_number' => $tenant->sii_resolution_number,
            'resolution_date' => $tenant->sii_resolution_date,
            'environment' => $tenant->sii_environment ?? 'certification',
            'auto_send' => $tenant->sii_auto_send ?? false,
            'auto_retry' => $tenant->sii_auto_retry ?? true,
            'retry_attempts' => $tenant->sii_retry_attempts ?? 3,
            'has_certificate' => $this->hasCertificate($tenant),
            'last_connection_test' => $tenant->sii_last_test_at,
            'connection_status' => $tenant->sii_connection_status,
        ];
    }

    public function updateConfiguration(Tenant $tenant, array $config): void
    {
        $tenant->update([
            'sii_resolution_number' => $config['resolution_number'],
            'sii_resolution_date' => $config['resolution_date'],
            'sii_environment' => $config['environment'],
            'sii_auto_send' => $config['auto_send'] ?? false,
            'sii_auto_retry' => $config['auto_retry'] ?? true,
            'sii_retry_attempts' => $config['retry_attempts'] ?? 3,
        ]);

        $this->logEvent($tenant, 'configuration_updated', 'info', 'Configuración SII actualizada', $config);
    }

    public function testConnection(Tenant $tenant): array
    {
        try {
            // Test authentication
            $authResult = $this->siiAuthService->authenticate($tenant);
            
            if (!$authResult['success']) {
                $tenant->update([
                    'sii_connection_status' => 'failed',
                    'sii_last_test_at' => now(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Error de autenticación: ' . $authResult['message'],
                ];
            }

            // Test basic SII services
            $servicesTest = $this->testSIIServices($tenant, $authResult['token']);
            
            $status = $servicesTest['success'] ? 'connected' : 'limited';
            
            $tenant->update([
                'sii_connection_status' => $status,
                'sii_last_test_at' => now(),
            ]);

            $this->logEvent($tenant, 'connection_test', 'info', 'Prueba de conexión realizada', [
                'success' => $servicesTest['success'],
                'services_available' => $servicesTest['services_available'] ?? [],
            ]);

            return [
                'success' => $servicesTest['success'],
                'message' => $servicesTest['message'],
                'data' => [
                    'auth_token_valid' => true,
                    'services_available' => $servicesTest['services_available'] ?? [],
                ],
            ];

        } catch (\Exception $e) {
            $tenant->update([
                'sii_connection_status' => 'failed',
                'sii_last_test_at' => now(),
            ]);

            $this->logEvent($tenant, 'connection_test', 'error', 'Error en prueba de conexión', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error en la prueba de conexión: ' . $e->getMessage(),
            ];
        }
    }

    public function canSwitchToProduction(Tenant $tenant): bool
    {
        // Check requirements for production environment
        $requirements = [
            'has_certificate' => $this->hasCertificate($tenant),
            'has_resolution' => !empty($tenant->sii_resolution_number),
            'connection_tested' => $tenant->sii_connection_status === 'connected',
            'documents_sent_in_cert' => $this->hasSuccessfulDocumentsInCertification($tenant),
        ];

        return array_reduce($requirements, function ($carry, $item) {
            return $carry && $item;
        }, true);
    }

    public function switchEnvironment(Tenant $tenant, string $environment): array
    {
        try {
            if ($environment === 'production' && !$this->canSwitchToProduction($tenant)) {
                return [
                    'success' => false,
                    'message' => 'No se cumplen los requisitos para cambiar a producción.',
                ];
            }

            $oldEnvironment = $tenant->sii_environment;
            
            $tenant->update([
                'sii_environment' => $environment,
                'sii_connection_status' => null, // Reset connection status
            ]);

            // Test connection in new environment
            $testResult = $this->testConnection($tenant);

            $this->logEvent($tenant, 'environment_switch', 'info', 'Cambio de ambiente SII', [
                'from' => $oldEnvironment,
                'to' => $environment,
                'connection_test_result' => $testResult['success'],
            ]);

            return [
                'success' => true,
                'message' => "Ambiente cambiado exitosamente a {$environment}.",
            ];

        } catch (\Exception $e) {
            $this->logEvent($tenant, 'environment_switch', 'error', 'Error al cambiar ambiente', [
                'environment' => $environment,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error al cambiar ambiente: ' . $e->getMessage(),
            ];
        }
    }

    public function getDocuments(array $filters): \Illuminate\Pagination\LengthAwarePaginator
    {
        $tenantId = auth()->user()->tenant_id;
        
        $query = TaxDocument::where('tenant_id', $tenantId)
            ->with(['customer', 'items']);

        // Apply filters
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                  ->orWhere('folio', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($customerQuery) use ($search) {
                      $customerQuery->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['sii_status'])) {
            $query->where('sii_status', $filters['sii_status']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        return $query->orderBy('date', 'desc')
            ->paginate(20)
            ->withQueryString();
    }

    public function queryDocumentStatus(TaxDocument $document): array
    {
        try {
            if (empty($document->sii_track_id)) {
                throw new \Exception('El documento no tiene Track ID del SII.');
            }

            $result = $this->advancedSIIService->queryDocumentStatus(
                $document->tenant,
                $document->sii_track_id
            );

            if ($result['success']) {
                // Update document status
                $document->update([
                    'sii_status' => $result['status'],
                    'sii_response' => $result['response'],
                    'sii_last_query_at' => now(),
                ]);

                $this->logEvent($document->tenant, 'status_query', 'info', 'Consulta de estado realizada', [
                    'document_id' => $document->id,
                    'track_id' => $document->sii_track_id,
                    'status' => $result['status'],
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            $this->logEvent($document->tenant, 'status_query', 'error', 'Error en consulta de estado', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function bulkSendDocuments(array $documentIds, int $tenantId): array
    {
        $sent = 0;
        $errors = 0;
        $results = [];

        $documents = TaxDocument::where('tenant_id', $tenantId)
            ->whereIn('id', $documentIds)
            ->where('sii_status', 'pending')
            ->get();

        foreach ($documents as $document) {
            try {
                $result = $this->sendDocument($document);
                
                if ($result['success']) {
                    $sent++;
                    $results[] = [
                        'document_id' => $document->id,
                        'status' => 'sent',
                        'track_id' => $result['track_id'],
                    ];
                } else {
                    $errors++;
                    $results[] = [
                        'document_id' => $document->id,
                        'status' => 'error',
                        'message' => $result['message'],
                    ];
                }

            } catch (\Exception $e) {
                $errors++;
                $results[] = [
                    'document_id' => $document->id,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return [
            'sent' => $sent,
            'errors' => $errors,
            'results' => $results,
        ];
    }

    public function bulkQueryStatus(array $documentIds, int $tenantId): array
    {
        $results = [];

        $documents = TaxDocument::where('tenant_id', $tenantId)
            ->whereIn('id', $documentIds)
            ->whereNotNull('sii_track_id')
            ->get();

        foreach ($documents as $document) {
            try {
                $status = $this->queryDocumentStatus($document);
                $results[] = [
                    'document_id' => $document->id,
                    'success' => true,
                    'status' => $status,
                ];

            } catch (\Exception $e) {
                $results[] = [
                    'document_id' => $document->id,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    public function sendDocument(TaxDocument $document): array
    {
        try {
            // Generate DTE if not exists
            if (empty($document->xml_content)) {
                $dteResult = $this->dteService->createDTE($document, $document->tenant);
                if (!$dteResult['success']) {
                    throw new \Exception('Error al generar DTE: ' . $dteResult['message']);
                }
            }

            // Authenticate with SII
            $authResult = $this->siiAuthService->authenticate($document->tenant);
            if (!$authResult['success']) {
                throw new \Exception('Error de autenticación: ' . $authResult['message']);
            }

            // Send to SII
            $result = $this->dteService->sendDTE($document, $authResult['token']);

            if ($result['success']) {
                $document->update([
                    'sii_status' => 'sent',
                    'sii_track_id' => $result['track_id'],
                    'sii_sent_at' => now(),
                ]);

                $this->logEvent($document->tenant, 'document_sent', 'info', 'Documento enviado al SII', [
                    'document_id' => $document->id,
                    'track_id' => $result['track_id'],
                ]);
            } else {
                $document->update([
                    'sii_status' => 'error',
                    'sii_response' => $result['message'],
                ]);

                $this->logEvent($document->tenant, 'document_send_error', 'error', 'Error al enviar documento', [
                    'document_id' => $document->id,
                    'error' => $result['message'],
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            $this->logEvent($document->tenant, 'document_send_error', 'error', 'Excepción al enviar documento', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function getFolioStatus(Tenant $tenant): array
    {
        // This would query folio status from SII or local database
        // For now, return a placeholder structure
        return [
            'factura_electronica' => [
                'available' => 100,
                'used' => 50,
                'last_request' => '2024-01-15',
            ],
            'boleta_electronica' => [
                'available' => 500,
                'used' => 120,
                'last_request' => '2024-01-10',
            ],
        ];
    }

    public function requestFolios(Tenant $tenant, string $documentType, int $quantity): array
    {
        try {
            $result = $this->advancedSIIService->requestFolios($tenant, $documentType, $quantity);

            $this->logEvent($tenant, 'folio_request', 'info', 'Solicitud de folios', [
                'document_type' => $documentType,
                'quantity' => $quantity,
                'success' => $result['success'],
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logEvent($tenant, 'folio_request', 'error', 'Error en solicitud de folios', [
                'document_type' => $documentType,
                'quantity' => $quantity,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function downloadFolios(Tenant $tenant, string $type)
    {
        // Implementation for downloading folios
        // This would typically return a file download response
        throw new \Exception('Funcionalidad de descarga de folios no implementada.');
    }

    public function getStatistics(int $tenantId): array
    {
        $stats = [
            'documents_by_status' => TaxDocument::where('tenant_id', $tenantId)
                ->selectRaw('sii_status, COUNT(*) as count')
                ->groupBy('sii_status')
                ->pluck('count', 'sii_status')
                ->toArray(),
            
            'documents_this_month' => TaxDocument::where('tenant_id', $tenantId)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            
            'success_rate' => $this->calculateSuccessRate($tenantId),
            
            'recent_activity' => SiiEventLog::where('tenant_id', $tenantId)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(),
        ];

        return $stats;
    }

    public function generateSummaryReport(int $tenantId, string $period, ?string $documentType = null): array
    {
        $startDate = $this->getStartDateForPeriod($period);
        
        $query = TaxDocument::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $startDate);

        if ($documentType) {
            $query->where('type', $documentType);
        }

        $documents = $query->get();

        return [
            'period' => $period,
            'document_type' => $documentType,
            'total_documents' => $documents->count(),
            'by_status' => $documents->groupBy('sii_status')->map->count(),
            'total_amount' => $documents->sum('total'),
            'success_rate' => $this->calculateSuccessRateForCollection($documents),
            'monthly_breakdown' => $this->getMonthlyBreakdown($documents),
        ];
    }

    protected function hasCertificate(Tenant $tenant): bool
    {
        $certificatePath = storage_path('app/sii/certificates/' . $tenant->id . '/certificate.pem');
        return file_exists($certificatePath);
    }

    protected function hasSuccessfulDocumentsInCertification(Tenant $tenant): bool
    {
        return TaxDocument::where('tenant_id', $tenant->id)
            ->where('sii_status', 'accepted')
            ->exists();
    }

    protected function testSIIServices(Tenant $tenant, string $token): array
    {
        try {
            $services = ['query_status', 'send_document'];
            $availableServices = [];

            foreach ($services as $service) {
                try {
                    // Test each service
                    $available = true; // Placeholder - would test actual service
                    
                    if ($available) {
                        $availableServices[] = $service;
                    }
                } catch (\Exception $e) {
                    // Service not available
                }
            }

            return [
                'success' => !empty($availableServices),
                'message' => empty($availableServices) ? 'No hay servicios disponibles' : 'Servicios disponibles: ' . implode(', ', $availableServices),
                'services_available' => $availableServices,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al probar servicios: ' . $e->getMessage(),
                'services_available' => [],
            ];
        }
    }

    protected function calculateSuccessRate(int $tenantId): float
    {
        $total = TaxDocument::where('tenant_id', $tenantId)
            ->whereNotNull('sii_status')
            ->count();

        if ($total === 0) {
            return 0;
        }

        $successful = TaxDocument::where('tenant_id', $tenantId)
            ->where('sii_status', 'accepted')
            ->count();

        return round(($successful / $total) * 100, 2);
    }

    protected function calculateSuccessRateForCollection(Collection $documents): float
    {
        $total = $documents->whereNotNull('sii_status')->count();
        
        if ($total === 0) {
            return 0;
        }

        $successful = $documents->where('sii_status', 'accepted')->count();
        
        return round(($successful / $total) * 100, 2);
    }

    protected function getStartDateForPeriod(string $period): Carbon
    {
        switch ($period) {
            case 'week':
                return now()->startOfWeek();
            case 'month':
                return now()->startOfMonth();
            case 'quarter':
                return now()->startOfQuarter();
            case 'year':
                return now()->startOfYear();
            default:
                return now()->startOfMonth();
        }
    }

    protected function getMonthlyBreakdown(Collection $documents): array
    {
        return $documents->groupBy(function ($document) {
            return $document->created_at->format('Y-m');
        })->map(function ($group) {
            return [
                'count' => $group->count(),
                'amount' => $group->sum('total'),
                'success_rate' => $this->calculateSuccessRateForCollection($group),
            ];
        })->toArray();
    }

    protected function logEvent(Tenant $tenant, string $eventType, string $level, string $message, array $context = []): void
    {
        SiiEventLog::create([
            'tenant_id' => $tenant->id,
            'event_type' => $eventType,
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'user_id' => auth()->id(),
        ]);
    }
}