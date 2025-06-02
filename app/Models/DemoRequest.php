<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DemoRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_name',
        'rut',
        'contact_name',
        'email',
        'phone',
        'business_type',
        'employees',
        'message',
        'status',
        'contacted_at',
        'demo_scheduled_at',
        'notes',
        'assigned_to'
    ];

    protected $casts = [
        'contacted_at' => 'datetime',
        'demo_scheduled_at' => 'datetime',
        'notes' => 'array'
    ];

    public function getStatusBadgeAttribute()
    {
        return match($this->status) {
            'pending' => ['color' => 'yellow', 'text' => 'Pendiente'],
            'contacted' => ['color' => 'blue', 'text' => 'Contactado'],
            'demo_scheduled' => ['color' => 'purple', 'text' => 'Demo Agendada'],
            'demo_completed' => ['color' => 'green', 'text' => 'Demo Completada'],
            'converted' => ['color' => 'emerald', 'text' => 'Convertido'],
            'declined' => ['color' => 'red', 'text' => 'Declinado'],
            default => ['color' => 'gray', 'text' => 'Desconocido']
        };
    }

    public function addNote($note, $author = null)
    {
        $notes = $this->notes ?? [];
        $notes[] = [
            'content' => $note,
            'author' => $author,
            'created_at' => now()->toISOString()
        ];
        
        $this->update(['notes' => $notes]);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeRecent($query)
    {
        return $query->where('created_at', '>=', now()->subDays(7));
    }
}
