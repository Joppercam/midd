<?php

namespace App\Modules\CRM\Models;

use App\Models\TenantAwareModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends TenantAwareModel
{
    use SoftDeletes;

    protected $table = 'crm_contacts';

    protected $fillable = [
        'company_id',
        'owner_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'mobile',
        'position',
        'department',
        'lead_status',
        'contact_type',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'birth_date',
        'social_networks',
        'notes',
        'custom_fields',
        'source',
        'score'
    ];

    protected $casts = [
        'birth_date' => 'date',
        'social_networks' => 'array',
        'custom_fields' => 'array',
        'score' => 'decimal:2'
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
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

    public function campaignMemberships(): HasMany
    {
        return $this->hasMany(CampaignMember::class);
    }

    public function campaigns()
    {
        return $this->belongsToMany(Campaign::class, 'crm_campaign_members')
            ->withPivot(['status', 'sent_at', 'opened_at', 'clicked_at', 'responded_at', 'converted_at', 'revenue_generated'])
            ->withTimestamps();
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getDisplayNameAttribute(): string
    {
        $name = $this->full_name;
        if ($this->company) {
            $name .= " ({$this->company->name})";
        }
        return $name;
    }

    public function getIsLeadAttribute(): bool
    {
        return $this->contact_type === 'lead';
    }

    public function getIsCustomerAttribute(): bool
    {
        return $this->contact_type === 'customer';
    }

    public function getIsQualifiedAttribute(): bool
    {
        return in_array($this->lead_status, ['qualified', 'proposal', 'negotiation', 'won']);
    }

    public function scopeLeads($query)
    {
        return $query->where('contact_type', 'lead');
    }

    public function scopeCustomers($query)
    {
        return $query->where('contact_type', 'customer');
    }

    public function scopeByOwner($query, $userId)
    {
        return $query->where('owner_id', $userId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('lead_status', $status);
    }

    public function scopeQualified($query)
    {
        return $query->whereIn('lead_status', ['qualified', 'proposal', 'negotiation']);
    }

    public function scopeWithScore($query, $operator, $score)
    {
        return $query->where('score', $operator, $score);
    }

    public function convertToCustomer(): void
    {
        $this->update([
            'contact_type' => 'customer',
            'lead_status' => 'won'
        ]);
    }

    public function updateScore(int $points): void
    {
        $newScore = max(0, min(100, $this->score + $points));
        $this->update(['score' => $newScore]);
    }

    public function assignTo(User $user): void
    {
        $this->update(['owner_id' => $user->id]);
    }

    public function getNextActivity()
    {
        return $this->activities()
            ->where('status', 'pending')
            ->orderBy('due_date')
            ->first();
    }

    public function getLastCommunication()
    {
        return $this->communications()
            ->latest('occurred_at')
            ->first();
    }

    public function getDaysSinceLastContact(): ?int
    {
        $lastComm = $this->getLastCommunication();
        return $lastComm ? now()->diffInDays($lastComm->occurred_at) : null;
    }

    public function getTotalOpportunityValue(): float
    {
        return $this->opportunities()
            ->where('status', 'won')
            ->sum('amount');
    }
}