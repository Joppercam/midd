<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class CafFile extends TenantAwareModel
{
    use Auditable;

    protected $fillable = [
        'tenant_id',
        'folio_range_id',
        'document_type',
        'file_path',
        'xml_content',
        'rut_emisor',
        'rut_envia',
        'range_start',
        'range_end',
        'fecha_autorizacion',
        'public_key',
        'private_key',
        'signature',
        'is_processed',
        'processed_at',
        'validation_errors',
    ];

    protected $casts = [
        'fecha_autorizacion' => 'date',
        'is_processed' => 'boolean',
        'processed_at' => 'datetime',
        'validation_errors' => 'array',
    ];

    /**
     * Get the tenant that owns this CAF file
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the folio range
     */
    public function folioRange(): BelongsTo
    {
        return $this->belongsTo(FolioRange::class);
    }

    /**
     * Get total folios in this CAF
     */
    public function getTotalFoliosAttribute(): int
    {
        return $this->range_end - $this->range_start + 1;
    }

    /**
     * Check if CAF is valid
     */
    public function getIsValidAttribute(): bool
    {
        return $this->is_processed && empty($this->validation_errors);
    }

    /**
     * Parse CAF XML content
     */
    public function parseXmlContent(): array
    {
        try {
            $xml = simplexml_load_string($this->xml_content);
            
            if (!$xml) {
                throw new \Exception('Invalid XML content');
            }

            $caf = $xml->AUTORIZACION->CAF ?? null;
            
            if (!$caf) {
                throw new \Exception('CAF node not found in XML');
            }

            $data = [
                'rut_emisor' => (string) $caf->DA->RE,
                'rut_envia' => (string) $caf->DA->RS,
                'document_type' => (int) $caf->DA->TD,
                'range_start' => (int) $caf->DA->RNG->D,
                'range_end' => (int) $caf->DA->RNG->H,
                'fecha_autorizacion' => (string) $caf->DA->FA,
                'public_key' => (string) $caf->RSAPK->M,
                'public_key_exp' => (string) $caf->RSAPK->E,
                'idk' => (string) $caf->DA->IDK,
                'signature' => (string) $caf->FRMA,
                'signature_algorithm' => (string) $caf->FRMA['algoritmo'],
            ];

            return $data;
        } catch (\Exception $e) {
            throw new \Exception('Error parsing CAF XML: ' . $e->getMessage());
        }
    }

    /**
     * Validate CAF against tenant
     */
    public function validateForTenant(Tenant $tenant): array
    {
        $errors = [];
        $cafData = $this->parseXmlContent();

        // Validate RUT emisor
        if ($cafData['rut_emisor'] !== $tenant->rut) {
            $errors[] = "RUT emisor in CAF ({$cafData['rut_emisor']}) doesn't match tenant RUT ({$tenant->rut})";
        }

        // Validate document type
        if (!in_array($cafData['document_type'], array_keys(FolioRange::DOCUMENT_TYPES))) {
            $errors[] = "Invalid document type: {$cafData['document_type']}";
        }

        // Validate range
        if ($cafData['range_start'] > $cafData['range_end']) {
            $errors[] = "Invalid folio range: start ({$cafData['range_start']}) is greater than end ({$cafData['range_end']})";
        }

        // Check for overlapping ranges
        $overlapping = FolioRange::where('tenant_id', $tenant->id)
            ->where('document_type', $cafData['document_type'])
            ->where(function ($query) use ($cafData) {
                $query->whereBetween('start_folio', [$cafData['range_start'], $cafData['range_end']])
                    ->orWhereBetween('end_folio', [$cafData['range_start'], $cafData['range_end']])
                    ->orWhere(function ($q) use ($cafData) {
                        $q->where('start_folio', '<=', $cafData['range_start'])
                          ->where('end_folio', '>=', $cafData['range_end']);
                    });
            })
            ->exists();

        if ($overlapping) {
            $errors[] = "Folio range overlaps with existing range";
        }

        return $errors;
    }

    /**
     * Process CAF file and create folio range
     */
    public function process(): FolioRange
    {
        if ($this->is_processed) {
            throw new \Exception('CAF file already processed');
        }

        $cafData = $this->parseXmlContent();
        
        // Create folio range
        $folioRange = FolioRange::create([
            'tenant_id' => $this->tenant_id,
            'document_type' => $cafData['document_type'],
            'start_folio' => $cafData['range_start'],
            'end_folio' => $cafData['range_end'],
            'current_folio' => $cafData['range_start'] - 1,
            'caf_file_path' => $this->file_path,
            'caf_content' => $this->xml_content,
            'authorization_date' => $cafData['fecha_autorizacion'],
            'is_active' => true,
            'environment' => $this->tenant->sii_environment ?? 'production',
            'metadata' => [
                'idk' => $cafData['idk'],
                'public_key' => $cafData['public_key'],
                'public_key_exp' => $cafData['public_key_exp'],
                'signature' => $cafData['signature'],
                'signature_algorithm' => $cafData['signature_algorithm'],
            ],
        ]);

        // Update CAF file
        $this->update([
            'folio_range_id' => $folioRange->id,
            'is_processed' => true,
            'processed_at' => now(),
            'rut_emisor' => $cafData['rut_emisor'],
            'rut_envia' => $cafData['rut_envia'] ?? $cafData['rut_emisor'],
            'range_start' => $cafData['range_start'],
            'range_end' => $cafData['range_end'],
            'fecha_autorizacion' => $cafData['fecha_autorizacion'],
            'public_key' => $cafData['public_key'],
            'signature' => $cafData['signature'],
        ]);

        return $folioRange;
    }
}