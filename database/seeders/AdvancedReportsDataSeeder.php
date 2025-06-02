<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Product;
use Carbon\Carbon;

class AdvancedReportsDataSeeder extends Seeder
{
    public function run()
    {
        // Get existing tenant
        $tenant = Tenant::first();
        if (!$tenant) {
            echo "No tenant found. Run ReportTestDataSeeder first.\n";
            return;
        }

        // Create suppliers
        $suppliers = [
            [
                'rut' => '96543210-7',
                'name' => 'Distribuidora Tech Ltda.',
                'email' => 'ventas@distribuidoratech.cl',
                'phone' => '+56987654321',
                'address' => 'Av. Tecnología 1234, Santiago'
            ],
            [
                'rut' => '95432109-8', 
                'name' => 'Proveedora Nacional SA',
                'email' => 'compras@proveedoranacional.cl',
                'phone' => '+56976543210',
                'address' => 'Calle Comercio 567, Valparaíso'
            ],
        ];

        foreach ($suppliers as $supplierData) {
            Supplier::firstOrCreate([
                'tenant_id' => $tenant->id,
                'rut' => $supplierData['rut']
            ], $supplierData);
        }

        $supplier1 = Supplier::where('tenant_id', $tenant->id)->where('rut', '96543210-7')->first();
        $supplier2 = Supplier::where('tenant_id', $tenant->id)->where('rut', '95432109-8')->first();

        // Create expenses for May 2025
        $mayExpenses = [
            [
                'number' => 'EXP-2025-001',
                'description' => 'Arriendo oficina comercial',
                'category' => 'Arriendos',
                'issue_date' => '2025-05-01',
                'net_amount' => 714285.71,
                'tax_amount' => 135714.29,
                'total_amount' => 850000,
                'status' => 'paid',
                'payment_method' => 'bank_transfer'
            ],
            [
                'number' => 'EXP-2025-002',
                'description' => 'Servicios básicos (luz, agua, gas)',
                'category' => 'Servicios Básicos',
                'issue_date' => '2025-05-15',
                'net_amount' => 105042.02,
                'tax_amount' => 19957.98,
                'total_amount' => 125000,
                'status' => 'paid',
                'payment_method' => 'debit_card'
            ],
            [
                'number' => 'EXP-2025-003',
                'description' => 'Internet y telefonía empresarial',
                'category' => 'Telecomunicaciones',
                'issue_date' => '2025-05-20',
                'net_amount' => 71428.57,
                'tax_amount' => 13571.43,
                'total_amount' => 85000,
                'status' => 'paid',
                'payment_method' => 'bank_transfer'
            ],
            [
                'number' => 'EXP-2025-004',
                'description' => 'Combustible vehículos reparto',
                'category' => 'Transporte',
                'issue_date' => '2025-05-25',
                'net_amount' => 151260.50,
                'tax_amount' => 28739.50,
                'total_amount' => 180000,
                'status' => 'paid',
                'payment_method' => 'credit_card'
            ],
        ];

        // Create expenses for June 2025
        $juneExpenses = [
            [
                'number' => 'EXP-2025-005',
                'description' => 'Arriendo oficina comercial',
                'category' => 'Arriendos',
                'issue_date' => '2025-06-01',
                'net_amount' => 714285.71,
                'tax_amount' => 135714.29,
                'total_amount' => 850000,
                'status' => 'paid',
                'payment_method' => 'bank_transfer'
            ],
            [
                'number' => 'EXP-2025-006',
                'description' => 'Servicios básicos (luz, agua, gas)',
                'category' => 'Servicios Básicos',
                'issue_date' => '2025-06-15',
                'net_amount' => 113445.38,
                'tax_amount' => 21554.62,
                'total_amount' => 135000,
                'status' => 'paid',
                'payment_method' => 'debit_card'
            ],
            [
                'number' => 'EXP-2025-007',
                'description' => 'Publicidad digital y marketing',
                'category' => 'Marketing',
                'issue_date' => '2025-06-10',
                'net_amount' => 210084.03,
                'tax_amount' => 39915.97,
                'total_amount' => 250000,
                'status' => 'paid',
                'payment_method' => 'credit_card'
            ],
            [
                'number' => 'EXP-2025-008',
                'description' => 'Mantenimiento equipos',
                'category' => 'Mantenimiento',
                'issue_date' => '2025-06-20',
                'net_amount' => 79831.93,
                'tax_amount' => 15168.07,
                'total_amount' => 95000,
                'status' => 'paid',
                'payment_method' => 'cash'
            ],
        ];

        $allExpenses = array_merge($mayExpenses, $juneExpenses);

        foreach ($allExpenses as $expenseData) {
            $expenseData['tenant_id'] = $tenant->id;
            $expenseData['balance'] = $expenseData['total_amount'];
            
            Expense::firstOrCreate([
                'tenant_id' => $tenant->id,
                'number' => $expenseData['number']
            ], $expenseData);
        }

        // Create purchase orders
        $products = Product::where('tenant_id', $tenant->id)->get();
        
        $purchaseOrders = [
            [
                'order_number' => 'PO-2025-001',
                'supplier_id' => $supplier1->id,
                'order_date' => '2025-05-10',
                'status' => 'completed',
                'items' => [
                    ['product' => $products->where('sku', 'LAPTOP001')->first(), 'quantity' => 5, 'unit_cost' => 650000],
                    ['product' => $products->where('sku', 'PHONE001')->first(), 'quantity' => 10, 'unit_cost' => 350000],
                ]
            ],
            [
                'order_number' => 'PO-2025-002',
                'supplier_id' => $supplier2->id,
                'order_date' => '2025-06-05',
                'status' => 'completed',
                'items' => [
                    ['product' => $products->where('sku', 'SHIRT001')->first(), 'quantity' => 50, 'unit_cost' => 20000],
                ]
            ],
        ];

        foreach ($purchaseOrders as $poData) {
            $subtotal = collect($poData['items'])->sum(function ($item) {
                return $item['quantity'] * $item['unit_cost'];
            });
            $taxAmount = $subtotal * 0.19;
            $total = $subtotal + $taxAmount;

            $purchaseOrder = PurchaseOrder::firstOrCreate([
                'tenant_id' => $tenant->id,
                'order_number' => $poData['order_number']
            ], [
                'supplier_id' => $poData['supplier_id'],
                'order_date' => $poData['order_date'],
                'status' => $poData['status'],
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'notes' => 'Orden de compra para reposición de inventario'
            ]);

            // Create purchase order items
            foreach ($poData['items'] as $item) {
                $subtotal = $item['quantity'] * $item['unit_cost'];
                $taxAmount = $subtotal * 0.19;
                $total = $subtotal + $taxAmount;
                
                PurchaseOrderItem::firstOrCreate([
                    'purchase_order_id' => $purchaseOrder->id,
                    'product_id' => $item['product']->id
                ], [
                    'description' => $item['product']->name,
                    'sku' => $item['product']->sku,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_cost'],
                    'subtotal' => $subtotal,
                    'tax_rate' => 19.00,
                    'tax_amount' => $taxAmount,
                    'total' => $total
                ]);
            }
        }

        // Get customers for payments
        $customers = \App\Models\Customer::where('tenant_id', $tenant->id)->get();
        $customer1 = $customers->where('rut', '76543210-5')->first();
        $customer2 = $customers->where('rut', '87654321-6')->first();
        $customer3 = $customers->where('rut', '98765432-7')->first();

        // Create payments (cash flow inflows)
        $payments = [
            [
                'number' => 'PAY-2025-001',
                'customer_id' => $customer1->id,
                'payment_date' => '2025-05-20',
                'amount' => 850000,
                'payment_method' => 'bank_transfer',
                'status' => 'confirmed',
                'reference' => 'Pago factura 1001',
                'description' => 'Pago cliente por laptop'
            ],
            [
                'number' => 'PAY-2025-002',
                'customer_id' => $customer2->id,
                'payment_date' => '2025-05-25',
                'amount' => 450000,
                'payment_method' => 'credit_card',
                'status' => 'confirmed',
                'reference' => 'Pago factura 1002',
                'description' => 'Pago cliente por smartphone'
            ],
            [
                'number' => 'PAY-2025-003',
                'customer_id' => $customer1->id,
                'payment_date' => '2025-06-10',
                'amount' => 1700000,
                'payment_method' => 'bank_transfer',
                'status' => 'confirmed',
                'reference' => 'Pago factura 1004',
                'description' => 'Pago cliente por 2 laptops'
            ],
            [
                'number' => 'PAY-2025-004',
                'customer_id' => $customer2->id,
                'payment_date' => '2025-06-15',
                'amount' => 900000,
                'payment_method' => 'cash',
                'status' => 'confirmed',
                'reference' => 'Pago factura 1005',
                'description' => 'Pago en efectivo por smartphones'
            ],
        ];

        foreach ($payments as $paymentData) {
            $paymentData['tenant_id'] = $tenant->id;
            
            Payment::firstOrCreate([
                'tenant_id' => $tenant->id,
                'number' => $paymentData['number']
            ], $paymentData);
        }

        echo "Advanced reports test data created successfully!\n";
        echo "Created:\n";
        echo "- " . count($allExpenses) . " expense records\n";
        echo "- " . count($purchaseOrders) . " purchase orders\n";
        echo "- " . count($payments) . " payment records\n";
        echo "- 2 suppliers\n";
    }
}