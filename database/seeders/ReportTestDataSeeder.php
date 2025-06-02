<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\TaxDocument;
use App\Models\TaxDocumentItem;
use Carbon\Carbon;

class ReportTestDataSeeder extends Seeder
{
    public function run()
    {
        // Get or create tenant
        $tenant = Tenant::first();
        if (!$tenant) {
            $tenant = Tenant::create([
                'id' => '01HJXAMPLE123456789',
                'name' => 'Empresa Demo',
                'email' => 'demo@empresa.cl',
                'rut' => '12345678-9',
                'address' => 'Av. Principal 123',
                'phone' => '+56912345678',
                'website' => 'https://empresa.cl',
                'business_sector' => 'Comercio',
                'is_demo' => true,
                'is_active' => true,
                'trial_ends_at' => now()->addDays(30),
            ]);
        }

        // Create categories
        $categories = [
            ['name' => 'Electrónicos', 'description' => 'Productos electrónicos'],
            ['name' => 'Ropa', 'description' => 'Ropa y accesorios'],
            ['name' => 'Hogar', 'description' => 'Productos para el hogar'],
        ];

        foreach ($categories as $categoryData) {
            Category::firstOrCreate([
                'tenant_id' => $tenant->id,
                'name' => $categoryData['name']
            ], [
                'slug' => strtolower(str_replace(' ', '-', $categoryData['name'])),
                'description' => $categoryData['description']
            ]);
        }

        // Create customers
        $customers = [
            ['rut' => '76543210-5', 'name' => 'Cliente Demo 1', 'email' => 'cliente1@demo.cl'],
            ['rut' => '87654321-6', 'name' => 'Cliente Demo 2', 'email' => 'cliente2@demo.cl'],
            ['rut' => '98765432-7', 'name' => 'Cliente Demo 3', 'email' => 'cliente3@demo.cl'],
        ];

        foreach ($customers as $customerData) {
            Customer::firstOrCreate([
                'tenant_id' => $tenant->id,
                'rut' => $customerData['rut']
            ], [
                'name' => $customerData['name'],
                'email' => $customerData['email'],
                'phone' => '+56987654321',
                'address' => 'Calle Demo 456'
            ]);
        }

        // Create products
        $category1 = Category::where('tenant_id', $tenant->id)->where('name', 'Electrónicos')->first();
        $category2 = Category::where('tenant_id', $tenant->id)->where('name', 'Ropa')->first();

        $products = [
            [
                'sku' => 'LAPTOP001',
                'name' => 'Laptop Dell',
                'description' => 'Laptop Dell Inspiron 15',
                'category_id' => $category1->id,
                'price' => 850000,
                'cost' => 650000,
                'stock_quantity' => 15,
                'minimum_stock' => 5,
                'is_service' => false
            ],
            [
                'sku' => 'PHONE001',
                'name' => 'Smartphone Samsung',
                'description' => 'Samsung Galaxy A54',
                'category_id' => $category1->id,
                'price' => 450000,
                'cost' => 350000,
                'stock_quantity' => 8,
                'minimum_stock' => 3,
                'is_service' => false
            ],
            [
                'sku' => 'SHIRT001',
                'name' => 'Camisa Polo',
                'description' => 'Camisa polo algodón',
                'category_id' => $category2->id,
                'price' => 35000,
                'cost' => 20000,
                'stock_quantity' => 25,
                'minimum_stock' => 10,
                'is_service' => false
            ],
        ];

        foreach ($products as $productData) {
            Product::firstOrCreate([
                'tenant_id' => $tenant->id,
                'sku' => $productData['sku']
            ], $productData);
        }

        // Create tax documents for different periods
        $customer1 = Customer::where('tenant_id', $tenant->id)->where('rut', '76543210-5')->first();
        $customer2 = Customer::where('tenant_id', $tenant->id)->where('rut', '87654321-6')->first();
        $customer3 = Customer::where('tenant_id', $tenant->id)->where('rut', '98765432-7')->first();

        $product1 = Product::where('tenant_id', $tenant->id)->where('sku', 'LAPTOP001')->first();
        $product2 = Product::where('tenant_id', $tenant->id)->where('sku', 'PHONE001')->first();
        $product3 = Product::where('tenant_id', $tenant->id)->where('sku', 'SHIRT001')->first();

        // May 2025 documents
        $documents = [
            [
                'folio' => 1001,
                'number' => '1001',
                'customer_id' => $customer1->id,
                'type' => 'invoice',
                'issue_date' => '2025-05-15',
                'due_date' => '2025-06-15',
                'status' => 'issued',
                'subtotal' => 714285.71,
                'tax_amount' => 135714.29,
                'total' => 850000,
                'products' => [['product' => $product1, 'quantity' => 1, 'price' => 850000]]
            ],
            [
                'folio' => 1002,
                'number' => '1002',
                'customer_id' => $customer2->id,
                'type' => 'invoice',
                'issue_date' => '2025-05-20',
                'due_date' => '2025-06-20',
                'status' => 'issued',
                'subtotal' => 378151.26,
                'tax_amount' => 71848.74,
                'total' => 450000,
                'products' => [['product' => $product2, 'quantity' => 1, 'price' => 450000]]
            ],
            [
                'folio' => 1003,
                'number' => '1003',
                'customer_id' => $customer3->id,
                'type' => 'invoice',
                'issue_date' => '2025-05-25',
                'due_date' => '2025-06-25',
                'status' => 'issued',
                'subtotal' => 58823.53,
                'tax_amount' => 11176.47,
                'total' => 70000,
                'products' => [['product' => $product3, 'quantity' => 2, 'price' => 35000]]
            ],
        ];

        // June 2025 documents
        $juneDocuments = [
            [
                'folio' => 1004,
                'number' => '1004',
                'customer_id' => $customer1->id,
                'type' => 'invoice',
                'issue_date' => '2025-06-05',
                'due_date' => '2025-07-05',
                'status' => 'issued',
                'subtotal' => 1428571.43,
                'tax_amount' => 271428.57,
                'total' => 1700000,
                'products' => [['product' => $product1, 'quantity' => 2, 'price' => 850000]]
            ],
            [
                'folio' => 1005,
                'number' => '1005',
                'customer_id' => $customer2->id,
                'type' => 'invoice',
                'issue_date' => '2025-06-10',
                'due_date' => '2025-07-10',
                'status' => 'issued',
                'subtotal' => 756302.52,
                'tax_amount' => 143697.48,
                'total' => 900000,
                'products' => [['product' => $product2, 'quantity' => 2, 'price' => 450000]]
            ],
            [
                'folio' => 1006,
                'number' => '1006',
                'customer_id' => $customer3->id,
                'type' => 'invoice',
                'issue_date' => '2025-06-15',
                'due_date' => '2025-07-15',
                'status' => 'issued',
                'subtotal' => 147058.82,
                'tax_amount' => 27941.18,
                'total' => 175000,
                'products' => [['product' => $product3, 'quantity' => 5, 'price' => 35000]]
            ],
        ];

        $allDocuments = array_merge($documents, $juneDocuments);

        foreach ($allDocuments as $docData) {
            $taxDoc = TaxDocument::firstOrCreate([
                'tenant_id' => $tenant->id,
                'folio' => $docData['folio']
            ], [
                'customer_id' => $docData['customer_id'],
                'type' => $docData['type'],
                'number' => $docData['number'],
                'issue_date' => $docData['issue_date'],
                'due_date' => $docData['due_date'],
                'status' => $docData['status'],
                'subtotal' => $docData['subtotal'],
                'tax_amount' => $docData['tax_amount'],
                'total' => $docData['total'],
                'payment_status' => 'pending'
            ]);

            // Create document items
            foreach ($docData['products'] as $item) {
                $subtotal = $item['quantity'] * $item['price'];
                $tax_amount = $subtotal * 0.19;
                $total = $subtotal + $tax_amount;
                
                TaxDocumentItem::firstOrCreate([
                    'tax_document_id' => $taxDoc->id,
                    'product_id' => $item['product']->id
                ], [
                    'description' => $item['product']->name,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['price'],
                    'subtotal' => $subtotal,
                    'tax_rate' => 19.00,
                    'tax_amount' => $tax_amount,
                    'total' => $total
                ]);
            }
        }

        echo "Report test data created successfully!\n";
        echo "Created documents from May-June 2025 for testing reports.\n";
    }
}