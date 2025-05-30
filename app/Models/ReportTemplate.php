<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportTemplate extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',
        'format',
        'default_parameters',
        'available_parameters',
        'query_class',
        'view_template',
        'is_active',
    ];

    protected $casts = [
        'default_parameters' => 'array',
        'available_parameters' => 'array',
        'is_active' => 'boolean',
    ];

    const TYPES = [
        'sales' => 'Reportes de Ventas',
        'financial' => 'Reportes Financieros',
        'inventory' => 'Reportes de Inventario',
        'customers' => 'Reportes de Clientes',
        'suppliers' => 'Reportes de Proveedores',
        'tax' => 'Reportes Tributarios',
        'analytics' => 'Reportes de Análisis',
        'custom' => 'Reportes Personalizados',
    ];

    const FORMATS = [
        'pdf' => 'PDF',
        'excel' => 'Excel (XLSX)',
        'csv' => 'CSV',
    ];

    /**
     * Scheduled reports using this template
     */
    public function scheduledReports(): HasMany
    {
        return $this->hasMany(ScheduledReport::class);
    }

    /**
     * Report executions using this template
     */
    public function reportExecutions(): HasMany
    {
        return $this->hasMany(ReportExecution::class);
    }

    /**
     * Report filters for this template
     */
    public function reportFilters(): HasMany
    {
        return $this->hasMany(ReportFilter::class);
    }

    /**
     * Get the query class instance
     */
    public function getQueryInstance()
    {
        if (!class_exists($this->query_class)) {
            throw new \Exception("Query class {$this->query_class} not found");
        }

        return new $this->query_class();
    }

    /**
     * Get type label
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Get format label
     */
    public function getFormatLabelAttribute(): string
    {
        return self::FORMATS[$this->format] ?? $this->format;
    }

    /**
     * Check if template supports a specific format
     */
    public function supportsFormat(string $format): bool
    {
        $availableFormats = $this->available_parameters['formats'] ?? array_keys(self::FORMATS);
        return in_array($format, $availableFormats);
    }

    /**
     * Get merged parameters (defaults + custom)
     */
    public function getMergedParameters(array $customParameters = []): array
    {
        $defaults = $this->default_parameters ?? [];
        return array_merge($defaults, $customParameters);
    }

    /**
     * Validate parameters against available parameters
     */
    public function validateParameters(array $parameters): array
    {
        $available = $this->available_parameters ?? [];
        $errors = [];

        foreach ($available as $param => $config) {
            $required = $config['required'] ?? false;
            $type = $config['type'] ?? 'string';
            $value = $parameters[$param] ?? null;

            if ($required && ($value === null || $value === '')) {
                $errors[$param] = "El parámetro {$param} es requerido";
                continue;
            }

            if ($value !== null) {
                switch ($type) {
                    case 'date':
                        if (!strtotime($value)) {
                            $errors[$param] = "El parámetro {$param} debe ser una fecha válida";
                        }
                        break;
                    case 'integer':
                        if (!is_numeric($value) || (int)$value != $value) {
                            $errors[$param] = "El parámetro {$param} debe ser un número entero";
                        }
                        break;
                    case 'boolean':
                        if (!is_bool($value) && !in_array($value, [0, 1, '0', '1', 'true', 'false'])) {
                            $errors[$param] = "El parámetro {$param} debe ser verdadero o falso";
                        }
                        break;
                    case 'array':
                        if (!is_array($value)) {
                            $errors[$param] = "El parámetro {$param} debe ser un array";
                        }
                        break;
                }

                // Check options if defined
                if (isset($config['options']) && !in_array($value, $config['options'])) {
                    $errors[$param] = "El parámetro {$param} debe ser uno de: " . implode(', ', $config['options']);
                }
            }
        }

        return $errors;
    }

    /**
     * Scope for active templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope by format
     */
    public function scopeByFormat($query, string $format)
    {
        return $query->where('format', $format);
    }
}