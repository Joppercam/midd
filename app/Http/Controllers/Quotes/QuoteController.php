<?php

namespace App\Http\Controllers\Quotes;

use App\Http\Controllers\Controller;
use App\Models\Quote;
use App\Models\Customer;
use App\Models\Product;
use App\Traits\ChecksPermissions;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class QuoteController extends Controller
{
    use ChecksPermissions;

    public function index(Request $request)
    {
        $this->checkPermission('quotes.view');

        $tenantId = auth()->user()->tenant_id;

        $quotes = Quote::with(['customer', 'user'])
            ->where('tenant_id', $tenantId)
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('quote_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('rut', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($request->date_from, function ($query, $date) {
                $query->where('issue_date', '>=', $date);
            })
            ->when($request->date_to, function ($query, $date) {
                $query->where('issue_date', '<=', $date);
            })
            ->latest('issue_date')
            ->paginate(15)
            ->withQueryString();

        // Estadísticas filtradas por tenant
        $stats = [
            'total' => Quote::where('tenant_id', $tenantId)->count(),
            'draft' => Quote::where('tenant_id', $tenantId)->where('status', 'draft')->count(),
            'sent' => Quote::where('tenant_id', $tenantId)->where('status', 'sent')->count(),
            'approved' => Quote::where('tenant_id', $tenantId)->where('status', 'approved')->count(),
            'expired' => 0, // Temporalmente 0 hasta verificar si existe el scope 'expired'
        ];

        return Inertia::render('Quotes/Index', [
            'quotes' => $quotes,
            'filters' => $request->only(['search', 'status', 'date_from', 'date_to']),
            'stats' => $stats,
        ]);
    }

    public function create()
    {
        $this->checkPermission('quotes.create');

        return Inertia::render('Quotes/Create', [
            'customers' => Customer::where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'rut', 'email']),
            'products' => Product::where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'price', 'track_inventory', 'current_stock']),
            'defaultTerms' => $this->getDefaultTerms(),
        ]);
    }

    public function store(Request $request)
    {
        $this->checkPermission('quotes.create');

        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'issue_date' => 'required|date',
            'validity_days' => 'required|integer|min:1|max:365',
            'payment_conditions' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'required|numeric|min:0|max:100',
        ]);

        // Calcular totales
        $subtotal = 0;
        foreach ($validated['items'] as $item) {
            $itemSubtotal = $item['quantity'] * $item['unit_price'];
            $itemSubtotal -= $itemSubtotal * ($item['discount'] / 100);
            $subtotal += $itemSubtotal;
        }
        $tax = $subtotal * 0.19; // IVA 19%
        $total = $subtotal + $tax;

        // Crear cotización
        $quote = Quote::create([
            'customer_id' => $validated['customer_id'],
            'issue_date' => $validated['issue_date'],
            'expiry_date' => Carbon::parse($validated['issue_date'])->addDays($validated['validity_days']),
            'validity_days' => $validated['validity_days'],
            'payment_conditions' => $validated['payment_conditions'],
            'notes' => $validated['notes'],
            'terms' => $validated['terms'],
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
            'status' => 'draft',
        ]);

        // Crear items
        foreach ($validated['items'] as $index => $item) {
            $itemSubtotal = $item['quantity'] * $item['unit_price'];
            $itemSubtotal -= $itemSubtotal * ($item['discount'] / 100);

            $quote->items()->create([
                'product_id' => $item['product_id'] ?? null,
                'description' => $item['description'],
                'product_code' => isset($item['product_id']) ? Product::find($item['product_id'])->sku : null,
                'quantity' => $item['quantity'],
                'unit' => $item['unit'] ?? 'unidad',
                'unit_price' => $item['unit_price'],
                'discount' => $item['discount'],
                'subtotal' => $itemSubtotal,
                'position' => $index + 1,
            ]);
        }

        return redirect()->route('quotes.show', $quote)
            ->with('success', 'Cotización creada exitosamente.');
    }

    public function show(Quote $quote)
    {
        $this->checkPermission('quotes.view');

        $quote->load(['customer', 'user', 'items.product', 'invoice']);

        return Inertia::render('Quotes/Show', [
            'quote' => $quote,
        ]);
    }

    public function edit(Quote $quote)
    {
        $this->checkPermission('quotes.edit');

        if (!$quote->canBeEdited()) {
            return redirect()->route('quotes.show', $quote)
                ->with('error', 'Esta cotización no puede ser editada.');
        }

        $quote->load(['customer', 'items.product']);

        return Inertia::render('Quotes/Edit', [
            'quote' => $quote,
            'customers' => Customer::where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'rut', 'email']),
            'products' => Product::where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'price', 'track_inventory', 'current_stock']),
        ]);
    }

    public function update(Request $request, Quote $quote)
    {
        $this->checkPermission('quotes.edit');

        if (!$quote->canBeEdited()) {
            return response()->json(['message' => 'Esta cotización no puede ser editada.'], 403);
        }

        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'issue_date' => 'required|date',
            'validity_days' => 'required|integer|min:1|max:365',
            'payment_conditions' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'required|numeric|min:0|max:100',
        ]);

        // Calcular totales
        $subtotal = 0;
        foreach ($validated['items'] as $item) {
            $itemSubtotal = $item['quantity'] * $item['unit_price'];
            $itemSubtotal -= $itemSubtotal * ($item['discount'] / 100);
            $subtotal += $itemSubtotal;
        }
        $tax = $subtotal * 0.19; // IVA 19%
        $total = $subtotal + $tax;

        // Actualizar cotización
        $quote->update([
            'customer_id' => $validated['customer_id'],
            'issue_date' => $validated['issue_date'],
            'expiry_date' => Carbon::parse($validated['issue_date'])->addDays($validated['validity_days']),
            'validity_days' => $validated['validity_days'],
            'payment_conditions' => $validated['payment_conditions'],
            'notes' => $validated['notes'],
            'terms' => $validated['terms'],
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
        ]);

        // Eliminar items anteriores y crear nuevos
        $quote->items()->delete();
        
        foreach ($validated['items'] as $index => $item) {
            $itemSubtotal = $item['quantity'] * $item['unit_price'];
            $itemSubtotal -= $itemSubtotal * ($item['discount'] / 100);

            $quote->items()->create([
                'product_id' => $item['product_id'] ?? null,
                'description' => $item['description'],
                'product_code' => isset($item['product_id']) ? Product::find($item['product_id'])->sku : null,
                'quantity' => $item['quantity'],
                'unit' => $item['unit'] ?? 'unidad',
                'unit_price' => $item['unit_price'],
                'discount' => $item['discount'],
                'subtotal' => $itemSubtotal,
                'position' => $index + 1,
            ]);
        }

        return redirect()->route('quotes.show', $quote)
            ->with('success', 'Cotización actualizada exitosamente.');
    }

    public function destroy(Quote $quote)
    {
        $this->checkPermission('quotes.delete');

        if (!$quote->canBeEdited()) {
            return response()->json(['message' => 'Esta cotización no puede ser eliminada.'], 403);
        }

        $quote->delete();

        return redirect()->route('quotes.index')
            ->with('success', 'Cotización eliminada exitosamente.');
    }

    // Acciones adicionales
    public function send(Quote $quote)
    {
        $this->checkPermission('quotes.send');

        if (!$quote->canBeSent()) {
            return response()->json(['message' => 'Esta cotización no puede ser enviada.'], 403);
        }

        // TODO: Implementar lógica de envío por email
        $quote->markAsSent();

        return response()->json(['message' => 'Cotización enviada exitosamente.']);
    }

    public function approve(Request $request, Quote $quote)
    {
        $this->checkPermission('quotes.approve');

        if ($quote->status !== 'sent') {
            return response()->json(['message' => 'Solo se pueden aprobar cotizaciones enviadas.'], 403);
        }

        $quote->markAsApproved();

        return response()->json(['message' => 'Cotización aprobada exitosamente.']);
    }

    public function reject(Request $request, Quote $quote)
    {
        $this->checkPermission('quotes.approve');

        if ($quote->status !== 'sent') {
            return response()->json(['message' => 'Solo se pueden rechazar cotizaciones enviadas.'], 403);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        $quote->markAsRejected($validated['reason'] ?? null);

        return response()->json(['message' => 'Cotización rechazada.']);
    }

    public function convert(Quote $quote)
    {
        $this->checkPermission('quotes.convert');

        if (!$quote->canBeConverted()) {
            return response()->json(['message' => 'Esta cotización no puede ser convertida a factura.'], 403);
        }

        try {
            $invoice = $quote->convertToInvoice();
            
            return response()->json([
                'message' => 'Cotización convertida a factura exitosamente.',
                'invoice_id' => $invoice->id,
                'redirect' => route('invoices.show', $invoice),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function download(Quote $quote)
    {
        $this->checkPermission('quotes.view');

        $quote->load(['customer', 'items.product', 'tenant']);

        $pdf = PDF::loadView('quotes.pdf', compact('quote'));
        
        return $pdf->download('cotizacion-' . $quote->quote_number . '.pdf');
    }

    private function getDefaultTerms()
    {
        return "1. La presente cotización tiene una validez según lo indicado en el documento.\n" .
               "2. Los precios incluyen IVA.\n" .
               "3. Los precios están sujetos a cambios sin previo aviso.\n" .
               "4. El tiempo de entrega está sujeto a disponibilidad de stock.\n" .
               "5. Forma de pago según lo acordado con el cliente.";
    }
}