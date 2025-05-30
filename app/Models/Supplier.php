<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\BelongsToTenant;
use App\Traits\Auditable;

class Supplier extends Model
{
    use HasFactory, BelongsToTenant, Auditable;

    protected $fillable = [
        'tenant_id',
        'rut',
        'name',
        'type',
        'business_name',
        'email',
        'phone',
        'address',
        'city',
        'commune',
        'region',
        'payment_terms',
        'notes',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    // Formatear RUT
    public function getFormattedRutAttribute(): string
    {
        if (!$this->rut) return '';
        
        $rut = preg_replace('/[^0-9kK]/', '', $this->rut);
        if (strlen($rut) < 2) return $rut;
        
        $dv = strtoupper(substr($rut, -1));
        $number = substr($rut, 0, -1);
        
        return number_format($number, 0, ',', '.') . '-' . $dv;
    }

    // Obtener etiqueta del tipo
    public function getTypeLabelAttribute(): string
    {
        $labels = [
            'person' => 'Persona Natural',
            'company' => 'Empresa'
        ];

        return $labels[$this->type] ?? $this->type;
    }

    // Obtener etiqueta de condiciones de pago
    public function getPaymentTermsLabelAttribute(): string
    {
        $labels = [
            'immediate' => 'Contado',
            '15_days' => '15 días',
            '30_days' => '30 días',
            '60_days' => '60 días',
            '90_days' => '90 días'
        ];

        return $labels[$this->payment_terms] ?? $this->payment_terms;
    }

    // Total de gastos
    public function getTotalExpensesAttribute(): float
    {
        return $this->expenses()
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->sum('total_amount');
    }

    // Saldo pendiente
    public function getPendingBalanceAttribute(): float
    {
        return $this->expenses()
            ->where('status', 'pending')
            ->sum('balance');
    }

    // Scope para proveedores activos
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope para proveedores por tipo
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    // Buscar por texto
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('rut', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('business_name', 'like', "%{$search}%");
        });
    }

    // Validar RUT chileno
    public static function validateRut($rut): bool
    {
        $rut = preg_replace('/[^0-9kK]/', '', $rut);
        if (strlen($rut) < 2) return false;
        
        $dv = strtoupper(substr($rut, -1));
        $number = substr($rut, 0, -1);
        
        $sum = 0;
        $multiplier = 2;
        
        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $sum += $number[$i] * $multiplier;
            $multiplier = $multiplier == 7 ? 2 : $multiplier + 1;
        }
        
        $calculatedDv = 11 - ($sum % 11);
        if ($calculatedDv == 11) $calculatedDv = '0';
        if ($calculatedDv == 10) $calculatedDv = 'K';
        
        return $dv == $calculatedDv;
    }
}