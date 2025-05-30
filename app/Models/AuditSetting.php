<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'model_class',
        'is_enabled',
        'events',
        'excluded_fields',
        'masked_fields',
        'retention_days'
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'events' => 'array',
        'excluded_fields' => 'array',
        'masked_fields' => 'array'
    ];

    protected static $defaultEvents = [
        'created',
        'updated',
        'deleted',
        'restored'
    ];

    protected static $defaultExcludedFields = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
        'remember_token',
        'email_verified_at'
    ];

    protected static $defaultMaskedFields = [
        'password',
        'token',
        'secret',
        'api_key',
        'certificate_password'
    ];

    public static function getSettingsForModel(string $modelClass, ?int $tenantId = null): ?self
    {
        return self::where('model_class', $modelClass)
            ->where(function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId)
                    ->orWhereNull('tenant_id'); // Global settings
            })
            ->orderBy('tenant_id', 'desc') // Tenant settings take precedence
            ->first();
    }

    public static function createDefaultSettings(string $modelClass, ?int $tenantId = null): self
    {
        return self::create([
            'tenant_id' => $tenantId,
            'model_class' => $modelClass,
            'is_enabled' => true,
            'events' => self::$defaultEvents,
            'excluded_fields' => self::$defaultExcludedFields,
            'masked_fields' => self::$defaultMaskedFields,
            'retention_days' => 90
        ]);
    }

    public function shouldAuditEvent(string $event): bool
    {
        if (!$this->is_enabled) {
            return false;
        }

        if (!$this->events) {
            return in_array($event, self::$defaultEvents);
        }

        return in_array($event, $this->events);
    }

    public function shouldExcludeField(string $field): bool
    {
        $excludedFields = $this->excluded_fields ?: self::$defaultExcludedFields;
        return in_array($field, $excludedFields);
    }

    public function getFieldsToAudit(array $attributes): array
    {
        $excludedFields = $this->excluded_fields ?: self::$defaultExcludedFields;
        return array_diff_key($attributes, array_flip($excludedFields));
    }

    public function getModelLabel(): string
    {
        $labels = [
            'App\Models\User' => 'Usuarios',
            'App\Models\Customer' => 'Clientes',
            'App\Models\Product' => 'Productos',
            'App\Models\TaxDocument' => 'Documentos Tributarios',
            'App\Models\Payment' => 'Pagos',
            'App\Models\Expense' => 'Gastos',
            'App\Models\Supplier' => 'Proveedores',
            'App\Models\BankAccount' => 'Cuentas Bancarias',
            'App\Models\Category' => 'Categorías'
        ];
        
        return $labels[$this->model_class] ?? class_basename($this->model_class);
    }

    public static function getAuditableModels(): array
    {
        return [
            'App\Models\User' => 'Usuarios',
            'App\Models\Customer' => 'Clientes',
            'App\Models\Product' => 'Productos',
            'App\Models\TaxDocument' => 'Documentos Tributarios',
            'App\Models\Payment' => 'Pagos',
            'App\Models\Expense' => 'Gastos',
            'App\Models\Supplier' => 'Proveedores',
            'App\Models\BankAccount' => 'Cuentas Bancarias',
            'App\Models\Category' => 'Categorías',
            'App\Models\ApiToken' => 'Tokens API',
            'App\Models\Tenant' => 'Empresas'
        ];
    }
}