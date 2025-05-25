<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\TaxDocument;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = TaxDocument::with(['customer', 'items'])
            ->where('tenant_id', auth()->user()->tenant_id)
            ->orderBy('created_at', 'desc');

        // Filtros
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%")
                        ->orWhere('rut', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('issue_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('issue_date', '<=', $request->to_date);
        }

        $invoices = $query->paginate(15)->withQueryString();

        return Inertia::render('Billing/Invoices/Index', [
            'invoices' => $invoices,
            'filters' => $request->only(['search', 'status', 'type', 'from_date', 'to_date']),
            'stats' => [
                'total_draft' => TaxDocument::where('tenant_id', auth()->user()->tenant_id)
                    ->where('status', 'draft')->count(),
                'total_sent' => TaxDocument::where('tenant_id', auth()->user()->tenant_id)
                    ->where('status', 'sent')->count(),
                'total_accepted' => TaxDocument::where('tenant_id', auth()->user()->tenant_id)
                    ->where('status', 'accepted')->count(),
                'total_overdue' => TaxDocument::where('tenant_id', auth()->user()->tenant_id)
                    ->where('status', 'accepted')
                    ->whereNull('paid_at')
                    ->where('due_date', '<', now())
                    ->count(),
            ],
        ]);
    }

    public function create()
    {
        $customers = Customer::where('tenant_id', auth()->user()->tenant_id)
            ->orderBy('name')
            ->get();

        $products = Product::where('tenant_id', auth()->user()->tenant_id)
            ->where('stock_quantity', '>', 0)
            ->orWhere('is_service', true)
            ->orderBy('name')
            ->get();

        return Inertia::render('Billing/Invoices/Create', [
            'customers' => $customers,
            'products' => $products,
            'documentTypes' => TaxDocument::TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'type' => 'required|in:invoice,receipt,credit_note,debit_note',
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:issue_date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.description' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Calcular totales
            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $subtotal += $item['quantity'] * $item['unit_price'];
            }
            
            $taxAmount = $subtotal * 0.19; // IVA 19%
            $total = $subtotal + $taxAmount;

            // Generar número de documento
            $lastNumber = TaxDocument::where('tenant_id', auth()->user()->tenant_id)
                ->where('type', $validated['type'])
                ->max(DB::raw("CAST(SUBSTRING(number, 3) AS UNSIGNED)")) ?? 0;
            
            $prefix = match($validated['type']) {
                'invoice' => 'F-',
                'receipt' => 'B-',
                'credit_note' => 'NC-',
                'debit_note' => 'ND-',
                default => 'D-',
            };
            
            $number = $prefix . sprintf('%08d', $lastNumber + 1);

            // Crear documento
            $document = TaxDocument::create([
                'tenant_id' => auth()->user()->tenant_id,
                'customer_id' => $validated['customer_id'],
                'type' => $validated['type'],
                'number' => $number,
                'status' => 'draft',
                'issue_date' => $validated['issue_date'],
                'due_date' => $validated['due_date'],
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
            ]);

            // Crear items
            foreach ($validated['items'] as $item) {
                $document->items()->create([
                    'product_id' => $item['product_id'],
                    'description' => $item['description'] ?? Product::find($item['product_id'])->name,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total' => $item['quantity'] * $item['unit_price'],
                ]);
            }

            DB::commit();

            return redirect()->route('invoices.show', $document)
                ->with('success', 'Documento creado exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al crear el documento: ' . $e->getMessage());
        }
    }

    public function show(TaxDocument $invoice)
    {
        // Verificar que pertenece al tenant actual
        if ($invoice->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $invoice->load(['customer', 'items.product']);

        return Inertia::render('Billing/Invoices/Show', [
            'invoice' => $invoice,
        ]);
    }

    public function edit(TaxDocument $invoice)
    {
        // Solo se pueden editar documentos en borrador
        if ($invoice->status !== 'draft') {
            return redirect()->route('invoices.show', $invoice)
                ->with('error', 'Solo se pueden editar documentos en borrador.');
        }

        if ($invoice->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $invoice->load(['customer', 'items.product']);

        $customers = Customer::where('tenant_id', auth()->user()->tenant_id)
            ->orderBy('name')
            ->get();

        $products = Product::where('tenant_id', auth()->user()->tenant_id)
            ->where('stock_quantity', '>', 0)
            ->orWhere('is_service', true)
            ->orderBy('name')
            ->get();

        return Inertia::render('Billing/Invoices/Edit', [
            'invoice' => $invoice,
            'customers' => $customers,
            'products' => $products,
            'documentTypes' => TaxDocument::TYPES,
        ]);
    }

    public function update(Request $request, TaxDocument $invoice)
    {
        if ($invoice->status !== 'draft') {
            return back()->with('error', 'Solo se pueden editar documentos en borrador.');
        }

        if ($invoice->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:issue_date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.description' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Calcular totales
            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $subtotal += $item['quantity'] * $item['unit_price'];
            }
            
            $taxAmount = $subtotal * 0.19;
            $total = $subtotal + $taxAmount;

            // Actualizar documento
            $invoice->update([
                'customer_id' => $validated['customer_id'],
                'issue_date' => $validated['issue_date'],
                'due_date' => $validated['due_date'],
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
            ]);

            // Eliminar items antiguos
            $invoice->items()->delete();

            // Crear nuevos items
            foreach ($validated['items'] as $item) {
                $invoice->items()->create([
                    'product_id' => $item['product_id'],
                    'description' => $item['description'] ?? Product::find($item['product_id'])->name,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total' => $item['quantity'] * $item['unit_price'],
                ]);
            }

            DB::commit();

            return redirect()->route('invoices.show', $invoice)
                ->with('success', 'Documento actualizado exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al actualizar el documento: ' . $e->getMessage());
        }
    }

    public function destroy(TaxDocument $invoice)
    {
        if ($invoice->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        if ($invoice->status !== 'draft') {
            return back()->with('error', 'Solo se pueden eliminar documentos en borrador.');
        }

        $invoice->delete();

        return redirect()->route('invoices.index')
            ->with('success', 'Documento eliminado exitosamente.');
    }

    public function send(TaxDocument $invoice)
    {
        if ($invoice->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        if ($invoice->status !== 'draft') {
            return back()->with('error', 'Solo se pueden enviar documentos en borrador.');
        }

        // TODO: Implementar envío al SII
        // Por ahora solo cambiar estado
        $invoice->update([
            'status' => 'sent',
            'sii_track_id' => 'DEMO-' . uniqid(),
        ]);

        return back()->with('success', 'Documento enviado al SII (simulado).');
    }

    public function download(TaxDocument $invoice)
    {
        if ($invoice->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $invoice->load(['customer', 'items.product', 'tenant']);

        $pdf = Pdf::loadView('invoices.pdf', compact('invoice'));
        
        $filename = $invoice->formatted_number . '_' . $invoice->customer->name . '.pdf';
        $filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $filename);
        
        return $pdf->download($filename);
    }
}