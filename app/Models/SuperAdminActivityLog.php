<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuperAdminActivityLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'super_admin_id',
        'action',
        'description',
        'properties',
        'tenant_id',
        'ip_address',
        'user_agent',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'properties' => 'array',
    ];

    /**
     * Get the super admin that owns this activity log
     */
    public function superAdmin()
    {
        return $this->belongsTo(SuperAdmin::class);
    }

    /**
     * Get the tenant associated with this activity (if any)
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}