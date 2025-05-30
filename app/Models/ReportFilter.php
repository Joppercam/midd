<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportFilter extends TenantAwareModel
{
    protected $fillable = [
        'user_id',
        'report_template_id',
        'name',
        'description',
        'filters',
        'is_default',
        'is_public',
    ];

    protected $casts = [
        'filters' => 'array',
        'is_default' => 'boolean',
        'is_public' => 'boolean',
    ];

    /**
     * User who created this filter
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Report template this filter is for
     */
    public function reportTemplate(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class);
    }

    /**
     * Get filter count
     */
    public function getFilterCountAttribute(): int
    {
        return count($this->filters ?? []);
    }

    /**
     * Get filter summary
     */
    public function getFilterSummaryAttribute(): string
    {
        $filters = $this->filters ?? [];
        $summary = [];

        foreach ($filters as $key => $value) {
            if (!empty($value)) {
                $summary[] = ucfirst(str_replace('_', ' ', $key)) . ': ' . (is_array($value) ? implode(', ', $value) : $value);
            }
        }

        return implode(' | ', $summary) ?: 'Sin filtros';
    }

    /**
     * Apply filters to a query
     */
    public function applyToQuery($query, array $additionalFilters = [])
    {
        $allFilters = array_merge($this->filters ?? [], $additionalFilters);

        foreach ($allFilters as $field => $value) {
            if (empty($value)) {
                continue;
            }

            switch ($field) {
                case 'date_from':
                    $query->where('created_at', '>=', $value);
                    break;
                case 'date_to':
                    $query->where('created_at', '<=', $value);
                    break;
                case 'status':
                    if (is_array($value)) {
                        $query->whereIn('status', $value);
                    } else {
                        $query->where('status', $value);
                    }
                    break;
                case 'customer_id':
                    if (is_array($value)) {
                        $query->whereIn('customer_id', $value);
                    } else {
                        $query->where('customer_id', $value);
                    }
                    break;
                case 'category_id':
                    if (is_array($value)) {
                        $query->whereIn('category_id', $value);
                    } else {
                        $query->where('category_id', $value);
                    }
                    break;
                case 'amount_min':
                    $query->where('total_amount', '>=', $value);
                    break;
                case 'amount_max':
                    $query->where('total_amount', '<=', $value);
                    break;
                default:
                    // Handle custom fields dynamically
                    if (is_array($value)) {
                        $query->whereIn($field, $value);
                    } else {
                        $query->where($field, $value);
                    }
                    break;
            }
        }

        return $query;
    }

    /**
     * Validate filters against template parameters
     */
    public function validateFilters(): array
    {
        $template = $this->reportTemplate;
        if (!$template) {
            return ['template' => 'Template de reporte no encontrado'];
        }

        return $template->validateParameters($this->filters ?? []);
    }

    /**
     * Scope for public filters
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope for private filters
     */
    public function scopePrivate($query)
    {
        return $query->where('is_public', false);
    }

    /**
     * Scope for default filters
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope by user (include public filters)
     */
    public function scopeAccessibleByUser($query, $userId)
    {
        return $query->where(function($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhere('is_public', true);
        });
    }

    /**
     * Scope by template
     */
    public function scopeByTemplate($query, $templateId)
    {
        return $query->where('report_template_id', $templateId);
    }
}