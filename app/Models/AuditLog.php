<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Arr;

class AuditLog extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'user_name',
        'user_email',
        'event',
        'auditable_type',
        'auditable_id',
        'auditable_name',
        'old_values',
        'new_values',
        'changed_fields',
        'ip_address',
        'user_agent',
        'url',
        'method',
        'tags',
        'metadata'
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'changed_fields' => 'array',
        'tags' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime'
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public static function log(
        string $event,
        Model $model,
        array $oldValues = null,
        array $newValues = null,
        array $metadata = []
    ): self {
        $user = auth()->user();
        $request = request();
        
        // Get changed fields
        $changedFields = [];
        if ($oldValues && $newValues) {
            foreach ($newValues as $key => $value) {
                if (!isset($oldValues[$key]) || $oldValues[$key] !== $value) {
                    $changedFields[] = $key;
                }
            }
        }
        
        // Apply field masking
        $settings = AuditSetting::getSettingsForModel(get_class($model));
        if ($settings && $settings->masked_fields) {
            $oldValues = self::maskFields($oldValues, $settings->masked_fields);
            $newValues = self::maskFields($newValues, $settings->masked_fields);
        }
        
        return self::create([
            'tenant_id' => $model->tenant_id ?? $user?->tenant_id,
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'user_email' => $user?->email,
            'event' => $event,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'auditable_name' => self::getAuditableName($model),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changed_fields' => $changedFields,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'url' => $request?->fullUrl(),
            'method' => $request?->method(),
            'tags' => $metadata['tags'] ?? null,
            'metadata' => Arr::except($metadata, ['tags'])
        ]);
    }

    protected static function maskFields(?array $values, array $maskedFields): ?array
    {
        if (!$values) {
            return $values;
        }
        
        foreach ($maskedFields as $field) {
            if (isset($values[$field])) {
                $values[$field] = '***MASKED***';
            }
        }
        
        return $values;
    }

    protected static function getAuditableName(Model $model): ?string
    {
        // Try common name fields
        $nameFields = ['name', 'title', 'document_number', 'email', 'rut', 'sku'];
        
        foreach ($nameFields as $field) {
            if (!empty($model->$field)) {
                return $model->$field;
            }
        }
        
        // Try to get display name method
        if (method_exists($model, 'getAuditName')) {
            return $model->getAuditName();
        }
        
        return null;
    }

    public function getEventLabel(): string
    {
        return match($this->event) {
            'created' => 'Creado',
            'updated' => 'Actualizado',
            'deleted' => 'Eliminado',
            'restored' => 'Restaurado',
            'attached' => 'Asociado',
            'detached' => 'Desasociado',
            'synced' => 'Sincronizado',
            default => ucfirst($this->event)
        };
    }

    public function getEventColor(): string
    {
        return match($this->event) {
            'created' => 'green',
            'updated' => 'blue',
            'deleted' => 'red',
            'restored' => 'yellow',
            default => 'gray'
        };
    }

    public function getModelLabel(): string
    {
        $modelLabels = [
            'App\Models\User' => 'Usuario',
            'App\Models\Customer' => 'Cliente',
            'App\Models\Product' => 'Producto',
            'App\Models\TaxDocument' => 'Documento Tributario',
            'App\Models\Payment' => 'Pago',
            'App\Models\Expense' => 'Gasto',
            'App\Models\Supplier' => 'Proveedor',
            'App\Models\BankAccount' => 'Cuenta Bancaria',
            'App\Models\Category' => 'Categoría'
        ];
        
        return $modelLabels[$this->auditable_type] ?? class_basename($this->auditable_type);
    }

    public function getDiff(): array
    {
        $diff = [];
        
        if (!$this->old_values || !$this->new_values) {
            return $diff;
        }
        
        foreach ($this->changed_fields as $field) {
            $diff[$field] = [
                'old' => $this->old_values[$field] ?? null,
                'new' => $this->new_values[$field] ?? null
            ];
        }
        
        return $diff;
    }

    public function getFormattedDiff(): array
    {
        $diff = $this->getDiff();
        $formatted = [];
        
        foreach ($diff as $field => $values) {
            $formatted[] = [
                'field' => $this->getFieldLabel($field),
                'old' => $this->formatValue($field, $values['old']),
                'new' => $this->formatValue($field, $values['new'])
            ];
        }
        
        return $formatted;
    }

    protected function getFieldLabel(string $field): string
    {
        $labels = [
            'name' => 'Nombre',
            'email' => 'Email',
            'rut' => 'RUT',
            'phone' => 'Teléfono',
            'address' => 'Dirección',
            'status' => 'Estado',
            'amount' => 'Monto',
            'total_amount' => 'Monto Total',
            'payment_status' => 'Estado de Pago',
            'due_date' => 'Fecha de Vencimiento',
            'issue_date' => 'Fecha de Emisión'
        ];
        
        return $labels[$field] ?? str_replace('_', ' ', ucfirst($field));
    }

    protected function formatValue(string $field, $value)
    {
        if ($value === null) {
            return 'N/A';
        }
        
        // Format dates
        if (str_contains($field, 'date') || str_contains($field, '_at')) {
            return \Carbon\Carbon::parse($value)->format('d/m/Y H:i');
        }
        
        // Format money
        if (str_contains($field, 'amount') || str_contains($field, 'price')) {
            return '$' . number_format($value, 0, ',', '.');
        }
        
        // Format booleans
        if (is_bool($value)) {
            return $value ? 'Sí' : 'No';
        }
        
        return $value;
    }

    public function scopeForModel($query, Model $model)
    {
        return $query->where('auditable_type', get_class($model))
            ->where('auditable_id', $model->getKey());
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByEvent($query, $event)
    {
        return $query->where('event', $event);
    }

    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public static function cleanup(): int
    {
        $count = 0;
        
        $settings = AuditSetting::where('retention_days', '>', 0)->get();
        
        foreach ($settings as $setting) {
            $deleted = self::where('auditable_type', $setting->model_class)
                ->where('created_at', '<', now()->subDays($setting->retention_days))
                ->delete();
                
            $count += $deleted;
        }
        
        return $count;
    }
}