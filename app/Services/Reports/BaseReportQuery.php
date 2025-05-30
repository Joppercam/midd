<?php

namespace App\Services\Reports;

use App\Models\Tenant;
use Illuminate\Support\Collection;

abstract class BaseReportQuery
{
    protected Tenant $tenant;
    protected array $parameters;

    public function __construct()
    {
        $this->tenant = tenant();
    }

    /**
     * Get report data
     */
    abstract public function getData(array $parameters = []): array;

    /**
     * Get report title
     */
    abstract public function getTitle(array $parameters = []): string;

    /**
     * Get report description
     */
    abstract public function getDescription(array $parameters = []): string;

    /**
     * Set parameters for the report
     */
    public function setParameters(array $parameters): self
    {
        $this->parameters = $parameters;
        return $this;
    }

    /**
     * Get parameter value with default
     */
    protected function getParameter(string $key, $default = null)
    {
        return $this->parameters[$key] ?? $default;
    }

    /**
     * Get date range parameters
     */
    protected function getDateRange(): array
    {
        $dateFrom = $this->getParameter('date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $this->getParameter('date_to', now()->endOfMonth()->format('Y-m-d'));

        return [
            'from' => \Carbon\Carbon::parse($dateFrom)->startOfDay(),
            'to' => \Carbon\Carbon::parse($dateTo)->endOfDay(),
        ];
    }

    /**
     * Apply date range filter to query
     */
    protected function applyDateRange($query, string $column = 'created_at')
    {
        $dateRange = $this->getDateRange();
        return $query->whereBetween($column, [$dateRange['from'], $dateRange['to']]);
    }

    /**
     * Apply tenant filter
     */
    protected function applyTenantFilter($query)
    {
        return $query->where('tenant_id', $this->tenant->id);
    }

    /**
     * Format currency for display
     */
    protected function formatCurrency($amount): string
    {
        return '$' . number_format($amount, 0, ',', '.');
    }

    /**
     * Format date for display
     */
    protected function formatDate($date): string
    {
        if (!$date) return '';
        
        if (is_string($date)) {
            $date = \Carbon\Carbon::parse($date);
        }
        
        return $date->format('d/m/Y');
    }

    /**
     * Format datetime for display
     */
    protected function formatDateTime($datetime): string
    {
        if (!$datetime) return '';
        
        if (is_string($datetime)) {
            $datetime = \Carbon\Carbon::parse($datetime);
        }
        
        return $datetime->format('d/m/Y H:i');
    }

    /**
     * Calculate percentage change
     */
    protected function calculatePercentageChange($current, $previous): array
    {
        if ($previous == 0) {
            $percentage = $current > 0 ? 100 : 0;
        } else {
            $percentage = (($current - $previous) / $previous) * 100;
        }

        return [
            'value' => round($percentage, 1),
            'direction' => $percentage > 0 ? 'up' : ($percentage < 0 ? 'down' : 'same'),
            'formatted' => ($percentage > 0 ? '+' : '') . round($percentage, 1) . '%'
        ];
    }

    /**
     * Get summary statistics
     */
    protected function getSummaryStats(Collection $data, string $valueColumn): array
    {
        $values = $data->pluck($valueColumn)->filter();
        
        return [
            'total' => $values->sum(),
            'average' => $values->avg() ?: 0,
            'min' => $values->min() ?: 0,
            'max' => $values->max() ?: 0,
            'count' => $values->count(),
        ];
    }

    /**
     * Group data by date period
     */
    protected function groupByDatePeriod(Collection $data, string $dateColumn, string $period = 'day'): Collection
    {
        return $data->groupBy(function ($item) use ($dateColumn, $period) {
            $date = \Carbon\Carbon::parse($item[$dateColumn]);
            
            switch ($period) {
                case 'hour':
                    return $date->format('Y-m-d H:00');
                case 'day':
                    return $date->format('Y-m-d');
                case 'week':
                    return $date->startOfWeek()->format('Y-m-d');
                case 'month':
                    return $date->format('Y-m');
                case 'quarter':
                    return $date->format('Y') . '-Q' . $date->quarter;
                case 'year':
                    return $date->format('Y');
                default:
                    return $date->format('Y-m-d');
            }
        });
    }

    /**
     * Get comparison period data
     */
    protected function getComparisonData(string $period = 'previous'): array
    {
        $currentRange = $this->getDateRange();
        
        switch ($period) {
            case 'previous':
                $diff = $currentRange['from']->diffInDays($currentRange['to']);
                $comparisonFrom = $currentRange['from']->copy()->subDays($diff + 1);
                $comparisonTo = $currentRange['from']->copy()->subDay();
                break;
                
            case 'last_year':
                $comparisonFrom = $currentRange['from']->copy()->subYear();
                $comparisonTo = $currentRange['to']->copy()->subYear();
                break;
                
            default:
                return [];
        }

        return [
            'from' => $comparisonFrom,
            'to' => $comparisonTo,
        ];
    }

    /**
     * Build chart data structure
     */
    protected function buildChartData(Collection $data, string $labelColumn, string $valueColumn, array $options = []): array
    {
        $labels = [];
        $values = [];
        $backgroundColors = $options['colors'] ?? ['#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6'];
        
        $data->each(function ($item, $index) use (&$labels, &$values, $labelColumn, $valueColumn) {
            $labels[] = $item[$labelColumn];
            $values[] = $item[$valueColumn];
        });

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => $values,
                    'backgroundColor' => array_slice($backgroundColors, 0, count($values)),
                    'borderWidth' => 1,
                ]
            ],
            'options' => array_merge([
                'responsive' => true,
                'maintainAspectRatio' => false,
            ], $options['chartOptions'] ?? [])
        ];
    }

    /**
     * Apply filters from parameters
     */
    protected function applyFilters($query, array $filterMappings = []): void
    {
        foreach ($this->parameters as $key => $value) {
            if (empty($value)) continue;

            // Skip date parameters as they're handled separately
            if (in_array($key, ['date_from', 'date_to'])) continue;

            // Use custom mapping if available
            $column = $filterMappings[$key] ?? $key;

            if (is_array($value)) {
                $query->whereIn($column, $value);
            } else {
                $query->where($column, $value);
            }
        }
    }

    /**
     * Get metadata for the report
     */
    public function getMetadata(array $parameters = []): array
    {
        $this->setParameters($parameters);
        
        return [
            'title' => $this->getTitle($parameters),
            'description' => $this->getDescription($parameters),
            'generated_at' => now()->format('d/m/Y H:i:s'),
            'generated_by' => auth()->user()->name ?? 'Sistema',
            'tenant' => $this->tenant->name,
            'parameters' => $this->formatParameters($parameters),
            'date_range' => $this->getDateRange(),
        ];
    }

    /**
     * Format parameters for display
     */
    protected function formatParameters(array $parameters): array
    {
        $formatted = [];
        
        foreach ($parameters as $key => $value) {
            if (empty($value)) continue;
            
            $label = $this->getParameterLabel($key);
            $formattedValue = $this->formatParameterValue($key, $value);
            
            $formatted[$label] = $formattedValue;
        }
        
        return $formatted;
    }

    /**
     * Get parameter label
     */
    protected function getParameterLabel(string $key): string
    {
        $labels = [
            'date_from' => 'Fecha Desde',
            'date_to' => 'Fecha Hasta',
            'customer_id' => 'Cliente',
            'supplier_id' => 'Proveedor',
            'category_id' => 'Categoría',
            'status' => 'Estado',
            'payment_method' => 'Método de Pago',
            'include_details' => 'Incluir Detalles',
            'include_zero_stock' => 'Incluir Sin Stock',
            'low_stock_only' => 'Solo Stock Bajo',
        ];

        return $labels[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }

    /**
     * Format parameter value for display
     */
    protected function formatParameterValue(string $key, $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Sí' : 'No';
        }
        
        if (in_array($key, ['date_from', 'date_to']) && $value) {
            return $this->formatDate($value);
        }
        
        if (is_array($value)) {
            return implode(', ', $value);
        }
        
        return (string) $value;
    }
}