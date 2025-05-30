<?php

namespace App\Modules\CRM\Models;

use App\Models\TenantAwareModel;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Opportunity extends TenantAwareModel
{
    use SoftDeletes;

    protected $table = 'crm_opportunities';

    protected $fillable = [
        'contact_id',
        'company_id',
        'owner_id',
        'pipeline_id',
        'stage_id',
        'name',
        'description',
        'amount',
        'probability',
        'expected_close_date',
        'actual_close_date',
        'status',
        'lost_reason',
        'competitor',
        'products',
        'custom_fields',
        'stage_duration',
        'stage_changed_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'probability' => 'decimal:2',
        'expected_close_date' => 'date',
        'actual_close_date' => 'date',
        'products' => 'array',
        'custom_fields' => 'array',
        'stage_changed_at' => 'datetime'
    ];

    protected static function booted()
    {
        static::creating(function ($opportunity) {
            if (!$opportunity->stage_changed_at) {
                $opportunity->stage_changed_at = now();
            }
        });

        static::updating(function ($opportunity) {
            if ($opportunity->isDirty('stage_id')) {
                $opportunity->stage_duration = now()->diffInDays($opportunity->stage_changed_at);
                $opportunity->stage_changed_at = now();
            }

            if ($opportunity->isDirty('status')) {
                if ($opportunity->status === 'won' || $opportunity->status === 'lost') {
                    $opportunity->actual_close_date = now();
                }
            }
        });
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'stage_id');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'related');
    }

    public function communications(): MorphMany
    {
        return $this->morphMany(Communication::class, 'communicable');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable', 'crm_taggables');
    }

    public function getWeightedValueAttribute(): float
    {
        return $this->amount * ($this->probability / 100);
    }

    public function getDaysInCurrentStageAttribute(): int
    {
        return now()->diffInDays($this->stage_changed_at);
    }

    public function getDaysToCloseAttribute(): ?int
    {
        if (!$this->expected_close_date) return null;
        return now()->diffInDays($this->expected_close_date, false);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->expected_close_date && $this->expected_close_date->isPast() && $this->status === 'open';
    }

    public function getAgeAttribute(): int
    {
        return $this->created_at->diffInDays(now());
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeWon($query)
    {
        return $query->where('status', 'won');
    }

    public function scopeLost($query)
    {
        return $query->where('status', 'lost');
    }

    public function scopeByOwner($query, $userId)
    {
        return $query->where('owner_id', $userId);
    }

    public function scopeClosingSoon($query, $days = 30)
    {
        return $query->open()
            ->whereNotNull('expected_close_date')
            ->whereBetween('expected_close_date', [now(), now()->addDays($days)]);
    }

    public function scopeOverdue($query)
    {
        return $query->open()
            ->whereNotNull('expected_close_date')
            ->where('expected_close_date', '<', now());
    }

    public function scopeInStage($query, $stageId)
    {
        return $query->where('stage_id', $stageId);
    }

    public function scopeInPipeline($query, $pipelineId)
    {
        return $query->where('pipeline_id', $pipelineId);
    }

    public function moveToStage(PipelineStage $stage): void
    {
        $this->update([
            'stage_id' => $stage->id,
            'probability' => $stage->probability
        ]);
    }

    public function markAsWon(): void
    {
        $this->update([
            'status' => 'won',
            'probability' => 100
        ]);
    }

    public function markAsLost(string $reason, ?string $competitor = null): void
    {
        $this->update([
            'status' => 'lost',
            'lost_reason' => $reason,
            'competitor' => $competitor,
            'probability' => 0
        ]);
    }

    public function assignTo(User $user): void
    {
        $this->update(['owner_id' => $user->id]);
    }

    public function updateProbability(float $probability): void
    {
        $this->update(['probability' => max(0, min(100, $probability))]);
    }

    public function addProduct(array $product): void
    {
        $products = $this->products ?? [];
        $products[] = $product;
        $this->update(['products' => $products]);
    }

    public function removeProduct(int $index): void
    {
        $products = $this->products ?? [];
        unset($products[$index]);
        $this->update(['products' => array_values($products)]);
    }

    public function calculateTotalAmount(): float
    {
        if (!$this->products) return 0;

        return collect($this->products)->sum(function ($product) {
            return ($product['quantity'] ?? 1) * ($product['price'] ?? 0);
        });
    }
}