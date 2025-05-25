<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TaxDocument;
use App\Models\TaxDocumentItem;
use App\Models\Tenant;
use App\Models\Customer;
use App\Models\Product;
use Carbon\Carbon;

class TaxDocumentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $customers = Customer::where('tenant_id', $tenant->id)->get();
            $products = Product::where('tenant_id', $tenant->id)->where('is_service', false)->get();

            if ($customers->isEmpty() || $products->isEmpty()) {
                continue;
            }

            // Crear facturas de ejemplo
            $invoices = [
                [
                    'tenant_id' => $tenant->id,
                    'customer_id' => $customers->random()->id,
                    'type' => 'invoice',
                    'number' => sprintf('F-%08d', 1),
                    'sii_track_id' => '1234567890',
                    'status' => 'accepted',
                    'issue_date' => Carbon::now()->subDays(30),
                    'due_date' => Carbon::now()->subDays(15),
                    'subtotal' => 0, // Se calculará después
                    'tax_amount' => 0, // Se calculará después
                    'total' => 0, // Se calculará después
                    'paid_at' => Carbon::now()->subDays(10),
                ],
                [
                    'tenant_id' => $tenant->id,
                    'customer_id' => $customers->random()->id,
                    'type' => 'invoice',
                    'number' => sprintf('F-%08d', 2),
                    'sii_track_id' => '1234567891',
                    'status' => 'accepted',
                    'issue_date' => Carbon::now()->subDays(25),
                    'due_date' => Carbon::now()->addDays(5),
                    'subtotal' => 0,
                    'tax_amount' => 0,
                    'total' => 0,
                    'paid_at' => null,
                ],
                [
                    'tenant_id' => $tenant->id,
                    'customer_id' => $customers->random()->id,
                    'type' => 'receipt',
                    'number' => sprintf('B-%08d', 1),
                    'sii_track_id' => '1234567892',
                    'status' => 'accepted',
                    'issue_date' => Carbon::now()->subDays(15),
                    'due_date' => Carbon::now()->subDays(15),
                    'subtotal' => 0,
                    'tax_amount' => 0,
                    'total' => 0,
                    'paid_at' => Carbon::now()->subDays(15),
                ],
                [
                    'tenant_id' => $tenant->id,
                    'customer_id' => $customers->random()->id,
                    'type' => 'invoice',
                    'number' => sprintf('F-%08d', 3),
                    'sii_track_id' => null,
                    'status' => 'draft',
                    'issue_date' => Carbon::now(),
                    'due_date' => Carbon::now()->addDays(30),
                    'subtotal' => 0,
                    'tax_amount' => 0,
                    'total' => 0,
                    'paid_at' => null,
                ],
            ];

            foreach ($invoices as $invoiceData) {
                $invoice = TaxDocument::create($invoiceData);

                // Agregar items a la factura
                $numItems = rand(1, 4);
                $subtotal = 0;

                for ($i = 0; $i < $numItems; $i++) {
                    $product = $products->random();
                    $quantity = rand(1, 5);
                    $unitPrice = $product->price;
                    
                    $itemTotal = $quantity * $unitPrice;

                    TaxDocumentItem::create([
                        'tax_document_id' => $invoice->id,
                        'product_id' => $product->id,
                        'description' => $product->name,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'total' => $itemTotal,
                    ]);

                    $subtotal += $itemTotal;
                }

                // Calcular IVA (19% en Chile) y actualizar totales
                $taxAmount = $subtotal * 0.19;
                $total = $subtotal + $taxAmount;

                $invoice->update([
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'total' => $total,
                ]);

                // Actualizar stock si la factura está aceptada
                if ($invoice->status === 'accepted') {
                    foreach ($invoice->items as $item) {
                        if ($item->product && !$item->product->is_service) {
                            $item->product->updateStock($item->quantity, 'sale', 'tax_document');
                        }
                    }
                }
            }

            // Crear una nota de crédito
            $originalInvoice = TaxDocument::where('tenant_id', $tenant->id)
                ->where('type', 'invoice')
                ->where('status', 'accepted')
                ->first();

            if ($originalInvoice) {
                $creditNote = TaxDocument::create([
                    'tenant_id' => $tenant->id,
                    'customer_id' => $originalInvoice->customer_id,
                    'type' => 'credit_note',
                    'number' => sprintf('NC-%08d', 1),
                    'sii_track_id' => '1234567893',
                    'status' => 'accepted',
                    'issue_date' => Carbon::now()->subDays(5),
                    'due_date' => Carbon::now()->subDays(5),
                    'subtotal' => 100000,
                    'tax_amount' => 19000,
                    'total' => 119000,
                ]);

                // Agregar item a la nota de crédito
                if ($products->isNotEmpty()) {
                    $product = $products->first();
                    TaxDocumentItem::create([
                        'tax_document_id' => $creditNote->id,
                        'product_id' => $product->id,
                        'description' => $product->name . ' - Devolución',
                        'quantity' => 1,
                        'unit_price' => 100000,
                        'total' => 100000,
                    ]);
                    
                    // Devolver stock
                    $product->updateStock(1, 'return', 'tax_document');
                }
            }
        }

        $this->command->info('Documentos tributarios creados para todos los tenants.');
    }
}
