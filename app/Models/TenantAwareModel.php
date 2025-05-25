<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Traits\BelongsToTenant;

abstract class TenantAwareModel extends Model
{
    use HasUuids, BelongsToTenant;
    
    protected $guarded = ['id', 'tenant_id'];
    
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}