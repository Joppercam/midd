<?php

namespace App\Services\SII;

use App\Models\Tenant;
use App\Models\TaxDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Exception;

class FolioManagerService
{
    // Chilean document types with their corresponding codes
    private const DOCUMENT_TYPES = [
        33 => 'Factura Electrónica',
        34 => 'Factura No Afecta o Exenta Electrónica',
        39 => 'Boleta Electrónica',
        52 => 'Guía de Despacho Electrónica',
        56 => 'Nota de Débito Electrónica',
        61 => 'Nota de Crédito Electrónica',
    ];

    /**
     * Get next available folio for a document type
     *
     * @param Tenant $tenant
     * @param int $documentType
     * @return int
     * @throws Exception
     */
    public function getNextFolio(Tenant $tenant, int $documentType): int
    {
        if (!isset(self::DOCUMENT_TYPES[$documentType])) {
            throw new Exception("Tipo de documento no válido: {$documentType}");
        }

        $cacheKey = "folio_counter_{$tenant->id}_{$documentType}";
        
        return Cache::lock("folio_lock_{$tenant->id}_{$documentType}", 10)->block(5, function () use ($tenant, $documentType, $cacheKey) {
            // Get current folio from cache or database
            $currentFolio = Cache::get($cacheKey);
            
            if ($currentFolio === null) {
                $currentFolio = $this->getCurrentFolioFromDatabase($tenant, $documentType);
                Cache::put($cacheKey, $currentFolio, now()->addHours(24));
            }

            $nextFolio = $currentFolio + 1;

            // Validate folio is within authorized range
            $this->validateFolioRange($tenant, $documentType, $nextFolio);

            // Update cache
            Cache::put($cacheKey, $nextFolio, now()->addHours(24));

            Log::info('Folio asignado', [
                'tenant_id' => $tenant->id,
                'document_type' => $documentType,
                'folio' => $nextFolio,
            ]);

            return $nextFolio;
        });
    }

    /**
     * Reserve a specific folio for a document
     *
     * @param Tenant $tenant
     * @param int $documentType
     * @param int $folio
     * @return bool
     * @throws Exception
     */
    public function reserveFolio(Tenant $tenant, int $documentType, int $folio): bool
    {
        if (!isset(self::DOCUMENT_TYPES[$documentType])) {
            throw new Exception("Tipo de documento no válido: {$documentType}");
        }

        // Validate folio is within authorized range
        $this->validateFolioRange($tenant, $documentType, $folio);

        // Check if folio is already used
        $exists = TaxDocument::where('tenant_id', $tenant->id)
            ->where('document_type', $documentType)
            ->where('folio', $folio)
            ->exists();

        if ($exists) {
            throw new Exception("El folio {$folio} ya está en uso para el tipo de documento {$documentType}");
        }

        return true;
    }

    /**
     * Release a folio (when document is cancelled)
     *
     * @param Tenant $tenant
     * @param int $documentType
     * @param int $folio
     * @return bool
     */
    public function releaseFolio(Tenant $tenant, int $documentType, int $folio): bool
    {
        try {
            // Update document status to cancelled
            $document = TaxDocument::where('tenant_id', $tenant->id)
                ->where('document_type', $documentType)
                ->where('folio', $folio)
                ->first();

            if ($document) {
                $document->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancelled_reason' => 'Folio liberado por anulación',
                ]);

                Log::info('Folio liberado', [
                    'tenant_id' => $tenant->id,
                    'document_type' => $documentType,
                    'folio' => $folio,
                    'document_id' => $document->id,
                ]);

                return true;
            }

            return false;
        } catch (Exception $e) {
            Log::error('Error liberando folio', [
                'tenant_id' => $tenant->id,
                'document_type' => $documentType,
                'folio' => $folio,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get folio status for a tenant and document type
     *
     * @param Tenant $tenant
     * @param int $documentType
     * @return array
     */
    public function getFolioStatus(Tenant $tenant, int $documentType): array
    {
        $range = $this->getFolioRange($tenant, $documentType);
        $currentFolio = $this->getCurrentFolioFromDatabase($tenant, $documentType);
        
        $usedFolios = TaxDocument::where('tenant_id', $tenant->id)
            ->where('document_type', $documentType)
            ->count();

        $availableFolios = $range['to'] - $currentFolio;
        $usagePercentage = $range['total'] > 0 ? ($usedFolios / $range['total']) * 100 : 0;

        return [
            'document_type' => $documentType,
            'document_type_name' => self::DOCUMENT_TYPES[$documentType],
            'current_folio' => $currentFolio,
            'next_folio' => $currentFolio + 1,
            'range_from' => $range['from'],
            'range_to' => $range['to'],
            'total_range' => $range['total'],
            'used_folios' => $usedFolios,
            'available_folios' => $availableFolios,
            'usage_percentage' => round($usagePercentage, 2),
            'needs_renewal' => $usagePercentage > 80, // Alert when 80% used
        ];
    }

    /**
     * Get all folio statuses for a tenant
     *
     * @param Tenant $tenant
     * @return array
     */
    public function getAllFolioStatuses(Tenant $tenant): array
    {
        $statuses = [];
        
        foreach (array_keys(self::DOCUMENT_TYPES) as $documentType) {
            $statuses[] = $this->getFolioStatus($tenant, $documentType);
        }

        return $statuses;
    }

    /**
     * Update folio range for a document type (when new CAF is received)
     *
     * @param Tenant $tenant
     * @param int $documentType
     * @param int $rangeFrom
     * @param int $rangeTo
     * @param string $cafXml
     * @return bool
     * @throws Exception
     */
    public function updateFolioRange(Tenant $tenant, int $documentType, int $rangeFrom, int $rangeTo, string $cafXml = ''): bool
    {
        if (!isset(self::DOCUMENT_TYPES[$documentType])) {
            throw new Exception("Tipo de documento no válido: {$documentType}");
        }

        if ($rangeFrom >= $rangeTo) {
            throw new Exception("El rango de folios no es válido");
        }

        try {
            DB::beginTransaction();

            // Store folio range configuration
            $folioConfig = $tenant->folio_ranges ?? [];
            $folioConfig[$documentType] = [
                'from' => $rangeFrom,
                'to' => $rangeTo,
                'total' => $rangeTo - $rangeFrom + 1,
                'caf_xml' => $cafXml,
                'updated_at' => now()->toISOString(),
            ];

            $tenant->update(['folio_ranges' => $folioConfig]);

            // Reset folio counter if new range starts before current folio
            $currentFolio = $this->getCurrentFolioFromDatabase($tenant, $documentType);
            if ($rangeFrom > $currentFolio) {
                $this->resetFolioCounter($tenant, $documentType, $rangeFrom - 1);
            }

            DB::commit();

            // Clear cache
            Cache::forget("folio_counter_{$tenant->id}_{$documentType}");

            Log::info('Rango de folios actualizado', [
                'tenant_id' => $tenant->id,
                'document_type' => $documentType,
                'range_from' => $rangeFrom,
                'range_to' => $rangeTo,
            ]);

            return true;
        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Error actualizando rango de folios', [
                'tenant_id' => $tenant->id,
                'document_type' => $documentType,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Validate if folio is within authorized range
     *
     * @param Tenant $tenant
     * @param int $documentType
     * @param int $folio
     * @throws Exception
     */
    private function validateFolioRange(Tenant $tenant, int $documentType, int $folio): void
    {
        $range = $this->getFolioRange($tenant, $documentType);
        
        if ($folio < $range['from'] || $folio > $range['to']) {
            throw new Exception(
                "El folio {$folio} está fuera del rango autorizado ({$range['from']}-{$range['to']}) " .
                "para el tipo de documento {$documentType}"
            );
        }
    }

    /**
     * Get folio range for a document type
     *
     * @param Tenant $tenant
     * @param int $documentType
     * @return array
     */
    private function getFolioRange(Tenant $tenant, int $documentType): array
    {
        $folioRanges = $tenant->folio_ranges ?? [];
        
        if (!isset($folioRanges[$documentType])) {
            // Default range for testing/certification environment
            return [
                'from' => 1,
                'to' => 1000000, // Large range for development
                'total' => 1000000,
            ];
        }

        return $folioRanges[$documentType];
    }

    /**
     * Get current folio from database
     *
     * @param Tenant $tenant
     * @param int $documentType
     * @return int
     */
    private function getCurrentFolioFromDatabase(Tenant $tenant, int $documentType): int
    {
        $lastFolio = TaxDocument::where('tenant_id', $tenant->id)
            ->where('document_type', $documentType)
            ->max('folio');

        if ($lastFolio === null) {
            // Return the minimum folio from range minus 1, so next folio will be the minimum
            $range = $this->getFolioRange($tenant, $documentType);
            return $range['from'] - 1;
        }

        return $lastFolio;
    }

    /**
     * Reset folio counter for a document type
     *
     * @param Tenant $tenant
     * @param int $documentType
     * @param int $newCounter
     * @return void
     */
    private function resetFolioCounter(Tenant $tenant, int $documentType, int $newCounter): void
    {
        $cacheKey = "folio_counter_{$tenant->id}_{$documentType}";
        Cache::put($cacheKey, $newCounter, now()->addHours(24));
        
        Log::info('Contador de folios reiniciado', [
            'tenant_id' => $tenant->id,
            'document_type' => $documentType,
            'new_counter' => $newCounter,
        ]);
    }

    /**
     * Get available document types
     *
     * @return array
     */
    public function getDocumentTypes(): array
    {
        return self::DOCUMENT_TYPES;
    }

    /**
     * Validate and assign folio to a tax document
     *
     * @param TaxDocument $document
     * @param Tenant $tenant
     * @return int
     * @throws Exception
     */
    public function assignFolioToDocument(TaxDocument $document, Tenant $tenant): int
    {
        // Map internal document types to SII document types
        $siiDocumentType = $this->mapToSIIDocumentType($document->type);
        
        // Get next folio
        $folio = $this->getNextFolio($tenant, $siiDocumentType);
        
        // Update document with folio
        $document->update([
            'folio' => $folio,
            'document_type' => $siiDocumentType,
            'sii_document_type' => $siiDocumentType,
        ]);

        return $folio;
    }

    /**
     * Map internal document types to SII document types
     *
     * @param string $internalType
     * @return int
     * @throws Exception
     */
    private function mapToSIIDocumentType(string $internalType): int
    {
        $mapping = [
            'invoice' => 33, // Factura Electrónica
            'invoice_exempt' => 34, // Factura No Afecta o Exenta
            'receipt' => 39, // Boleta Electrónica
            'delivery_note' => 52, // Guía de Despacho
            'debit_note' => 56, // Nota de Débito
            'credit_note' => 61, // Nota de Crédito
        ];

        if (!isset($mapping[$internalType])) {
            throw new Exception("Tipo de documento interno no reconocido: {$internalType}");
        }

        return $mapping[$internalType];
    }

    /**
     * Generate folio report for a tenant
     *
     * @param Tenant $tenant
     * @param Carbon|null $fromDate
     * @param Carbon|null $toDate
     * @return array
     */
    public function generateFolioReport(Tenant $tenant, ?Carbon $fromDate = null, ?Carbon $toDate = null): array
    {
        $fromDate = $fromDate ?? now()->startOfMonth();
        $toDate = $toDate ?? now()->endOfMonth();

        $report = [
            'period' => [
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
            ],
            'document_types' => [],
            'summary' => [
                'total_documents' => 0,
                'total_folios_used' => 0,
                'gaps_detected' => [],
            ],
        ];

        foreach (array_keys(self::DOCUMENT_TYPES) as $documentType) {
            $documents = TaxDocument::where('tenant_id', $tenant->id)
                ->where('document_type', $documentType)
                ->whereBetween('created_at', [$fromDate, $toDate])
                ->orderBy('folio')
                ->get(['folio', 'status', 'created_at']);

            $folios = $documents->pluck('folio')->toArray();
            $gaps = $this->detectGaps($folios);

            $typeReport = [
                'document_type' => $documentType,
                'document_type_name' => self::DOCUMENT_TYPES[$documentType],
                'documents_count' => $documents->count(),
                'folios_used' => $folios,
                'folio_range' => [
                    'min' => min($folios) ?? 0,
                    'max' => max($folios) ?? 0,
                ],
                'gaps' => $gaps,
                'status_breakdown' => $documents->groupBy('status')->map->count(),
            ];

            $report['document_types'][] = $typeReport;
            $report['summary']['total_documents'] += $documents->count();
            $report['summary']['total_folios_used'] += count($folios);
            
            if (!empty($gaps)) {
                $report['summary']['gaps_detected'][$documentType] = $gaps;
            }
        }

        return $report;
    }

    /**
     * Detect gaps in folio sequence
     *
     * @param array $folios
     * @return array
     */
    private function detectGaps(array $folios): array
    {
        if (empty($folios)) {
            return [];
        }

        sort($folios);
        $gaps = [];

        for ($i = 0; $i < count($folios) - 1; $i++) {
            $expected = $folios[$i] + 1;
            $actual = $folios[$i + 1];
            
            if ($actual > $expected) {
                $gaps[] = [
                    'from' => $expected,
                    'to' => $actual - 1,
                    'count' => $actual - $expected,
                ];
            }
        }

        return $gaps;
    }
}