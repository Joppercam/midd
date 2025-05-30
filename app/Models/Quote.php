<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;
use App\Traits\Auditable;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Quote extends Model
{
    use BelongsToTenant, Auditable;

    protected $fillable = [
        'tenant_id',
        'uuid',
        'quote_number',
        'customer_id',
        'user_id',
        'issue_date',
        'expiry_date',
        'status',
        'subtotal',
        'tax',
        'total',
        'notes',
        'terms',
        'payment_conditions',
        'validity_days',
        'converted_to_invoice_id',
        'sent_at',
        'approved_at',
        'rejected_at',
        'converted_at',
        'rejection_reason',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'sent_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'converted_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($quote) {
            if (empty($quote->uuid)) {
                $quote->uuid = Str::uuid();
            }
            if (empty($quote->quote_number)) {
                $quote->quote_number = self::generateQuoteNumber($quote->tenant_id);
            }
            if (empty($quote->user_id)) {
                $quote->user_id = auth()->id();
            }
            if (empty($quote->issue_date)) {
                $quote->issue_date = Carbon::now();
            }
            if (empty($quote->expiry_date)) {
                $quote->expiry_date = Carbon::now()->addDays($quote->validity_days ?? 30);
            }
            if (empty($quote->status)) {
                $quote->status = 'draft';
            }
        });
    }

    public static function generateQuoteNumber($tenantId)
    {
        $lastQuote = self::where('tenant_id', $tenantId)
            ->whereYear('issue_date', Carbon::now()->year)
            ->orderBy('quote_number', 'desc')
            ->first();

        if ($lastQuote) {
            $lastNumber = intval(substr($lastQuote->quote_number, -6));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return 'COT-' . Carbon::now()->year . '-' . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
    }

    // Relaciones
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(QuoteItem::class)->orderBy('position');
    }

    public function invoice()
    {
        return $this->belongsTo(TaxDocument::class, 'converted_to_invoice_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['draft', 'sent', 'approved']);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'sent')
            ->where('expiry_date', '>=', Carbon::today());
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'sent')
            ->where('expiry_date', '<', Carbon::today());
    }

    // Helpers
    public function markAsSent()
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => Carbon::now(),
        ]);
    }

    public function markAsApproved()
    {
        $this->update([
            'status' => 'approved',
            'approved_at' => Carbon::now(),
        ]);
    }

    public function markAsRejected($reason = null)
    {
        $this->update([
            'status' => 'rejected',
            'rejected_at' => Carbon::now(),
            'rejection_reason' => $reason,
        ]);
    }

    public function convertToInvoice()
    {
        if ($this->status !== 'approved') {
            throw new \Exception('Solo se pueden convertir cotizaciones aprobadas');
        }

        // Crear factura basada en la cotización
        $invoice = TaxDocument::create([
            'tenant_id' => $this->tenant_id,
            'document_type' => 33, // Factura
            'customer_id' => $this->customer_id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => $this->subtotal,
            'tax' => $this->tax,
            'total' => $this->total,
            'notes' => 'Generada desde cotización ' . $this->quote_number,
            'payment_status' => 'pending',
            'status' => 'draft',
        ]);

        // Copiar items
        foreach ($this->items as $item) {
            $invoice->items()->create([
                'product_id' => $item->product_id,
                'description' => $item->description,
                'product_code' => $item->product_code,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'discount' => $item->discount,
                'subtotal' => $item->subtotal,
            ]);
        }

        // Marcar cotización como convertida
        $this->update([
            'status' => 'converted',
            'converted_at' => Carbon::now(),
            'converted_to_invoice_id' => $invoice->id,
        ]);

        return $invoice;
    }

    public function canBeEdited()
    {
        return in_array($this->status, ['draft']);
    }

    public function canBeSent()
    {
        return in_array($this->status, ['draft', 'sent']);
    }

    public function canBeConverted()
    {
        return $this->status === 'approved';
    }

    public function isExpired()
    {
        return $this->status === 'sent' && $this->expiry_date < Carbon::today();
    }

    // Atributos
    public function getStatusLabelAttribute()
    {
        $labels = [
            'draft' => 'Borrador',
            'sent' => 'Enviada',
            'approved' => 'Aprobada',
            'rejected' => 'Rechazada',
            'converted' => 'Convertida',
            'expired' => 'Expirada',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute()
    {
        $colors = [
            'draft' => 'gray',
            'sent' => 'blue',
            'approved' => 'green',
            'rejected' => 'red',
            'converted' => 'purple',
            'expired' => 'yellow',
        ];

        return $colors[$this->status] ?? 'gray';
    }

    public function getDaysToExpiryAttribute()
    {
        return Carbon::today()->diffInDays($this->expiry_date, false);
    }
}
