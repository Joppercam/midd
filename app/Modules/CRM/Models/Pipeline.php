<?php

namespace App\Modules\CRM\Models;

use App\Models\TenantAwareModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pipeline extends TenantAwareModel
{
    protected $table = 'crm_pipelines';

    protected $fillable = [
        'name',
        'description',
        'is_default',
        'is_active'
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean'
    ];

    protected static function booted()
    {
        static::creating(function ($pipeline) {
            // Si es el primer pipeline o se marca como default, quitar default de otros
            if ($pipeline->is_default) {
                static::where('tenant_id', $pipeline->tenant_id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });

        static::updating(function ($pipeline) {
            if ($pipeline->isDirty('is_default') && $pipeline->is_default) {
                static::where('tenant_id', $pipeline->tenant_id)
                    ->where('id', '!=', $pipeline->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });
    }

    public function stages(): HasMany
    {
        return $this->hasMany(PipelineStage::class)->orderBy('order');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function getStageCountAttribute(): int
    {
        return $this->stages()->count();
    }

    public function getOpportunityCountAttribute(): int
    {
        return $this->opportunities()->count();
    }

    public function getOpenOpportunityCountAttribute(): int
    {
        return $this->opportunities()->where('status', 'open')->count();
    }

    public function getTotalValueAttribute(): float
    {
        return $this->opportunities()->sum('amount');
    }

    public function getWeightedValueAttribute(): float
    {
        return $this->opportunities()
            ->where('status', 'open')
            ->get()
            ->sum('weighted_value');
    }

    public function getConversionRateAttribute(): float
    {
        $total = $this->opportunities()->whereIn('status', ['won', 'lost'])->count();
        if ($total === 0) return 0;
        
        $won = $this->opportunities()->where('status', 'won')->count();
        return round(($won / $total) * 100, 2);
    }

    public function getAverageDealSizeAttribute(): float
    {
        $won = $this->opportunities()->where('status', 'won');
        $count = $won->count();
        
        if ($count === 0) return 0;
        
        return round($won->sum('amount') / $count, 2);
    }

    public function getAverageSalesCycleAttribute(): int
    {
        $wonDeals = $this->opportunities()
            ->where('status', 'won')
            ->whereNotNull('actual_close_date')
            ->get();
            
        if ($wonDeals->isEmpty()) return 0;
        
        $totalDays = $wonDeals->sum(function ($deal) {
            return $deal->created_at->diffInDays($deal->actual_close_date);
        });
        
        return round($totalDays / $wonDeals->count());
    }

    public function createDefaultStages(): void
    {
        $defaultStages = [
            ['name' => 'Prospecto', 'order' => 1, 'probability' => 10, 'color' => '#gray'],
            ['name' => 'Calificación', 'order' => 2, 'probability' => 20, 'color' => '#blue'],
            ['name' => 'Propuesta', 'order' => 3, 'probability' => 50, 'color' => '#yellow'],
            ['name' => 'Negociación', 'order' => 4, 'probability' => 75, 'color' => '#orange'],
            ['name' => 'Ganado', 'order' => 5, 'probability' => 100, 'color' => '#green', 'is_won' => true],
            ['name' => 'Perdido', 'order' => 6, 'probability' => 0, 'color' => '#red', 'is_lost' => true],
        ];

        foreach ($defaultStages as $stage) {
            $this->stages()->create($stage);
        }
    }

    public function getStageByOrder(int $order): ?PipelineStage
    {
        return $this->stages()->where('order', $order)->first();
    }

    public function getFirstStage(): ?PipelineStage
    {
        return $this->stages()->orderBy('order')->first();
    }

    public function getWonStage(): ?PipelineStage
    {
        return $this->stages()->where('is_won', true)->first();
    }

    public function getLostStage(): ?PipelineStage
    {
        return $this->stages()->where('is_lost', true)->first();
    }
}