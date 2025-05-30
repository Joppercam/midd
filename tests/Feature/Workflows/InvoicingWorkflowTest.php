<?php

namespace Tests\Feature\Workflows;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Category;
use App\Models\TaxDocument;
use App\Models\Payment;
use App\Models\SalesBook;
use Tests\TestCase;
use Inertia\Testing\AssertableInertia as Assert;

class InvoicingWorkflowTest extends TestCase
{
    private Customer $customer;
    private Product $product;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Category',
            'type' => 'product',
        ]);

        $this->customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Customer',
            'rut' => '12345678-9',
            'email' => 'customer@test.com',
            'phone' => '+56912345678',
            'address' => 'Test Address 123',
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $this->category->id,
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'description' => 'A test product',
            'purchase_price' => 1000,
            'sale_price' => 1500,
            'stock_quantity' => 100,
            'min_stock_level' => 10,
            'type' => 'product',
            'is_active' => true,
        ]);

        // Create permissions
        $permissions = [
            'customers.view', 'customers.create',
            'products.view', 'products.create',
            'invoices.view', 'invoices.create', 'invoices.send', 'invoices.download',
            'payments.view', 'payments.create',
            'tax_books.view', 'tax_books.generate',
        ];

        foreach ($permissions as $permission) {
            $this->createPermission($permission);
        }
    }

    /** @test */
    public function complete_invoicing_workflow()
    {
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo([
            'customers.view', 'customers.create',
            'products.view', 'products.create',
            'invoices.view', 'invoices.create', 'invoices.send', 'invoices.download',
            'payments.view', 'payments.create',
            'tax_books.view', 'tax_books.generate',
        ]);

        // Step 1: Create a customer
        $response = $this->actingAs($user)
            ->post(route('customers.store'), [
                'name' => 'New Customer',
                'rut' => '98765432-1',
                'email' => 'new@customer.com',
                'phone' => '+56987654321',
                'address' => 'New Address 456',
            ]);

        $response->assertRedirect();
        $newCustomer = Customer::where('rut', '98765432-1')->first();
        $this->assertNotNull($newCustomer);

        // Step 2: Create a product
        $response = $this->actingAs($user)
            ->post(route('products.store'), [
                'category_id' => $this->category->id,
                'name' => 'New Product',
                'sku' => 'NEW-001',
                'description' => 'A new product',
                'purchase_price' => 800,
                'sale_price' => 1200,
                'stock_quantity' => 50,
                'min_stock_level' => 5,
                'type' => 'product',
                'is_active' => true,
            ]);

        $response->assertRedirect();
        $newProduct = Product::where('sku', 'NEW-001')->first();
        $this->assertNotNull($newProduct);

        // Step 3: Create an invoice
        $invoiceData = [
            'customer_id' => $newCustomer->id,
            'type' => 'invoice',
            'issue_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'items' => [
                [
                    'product_id' => $newProduct->id,
                    'description' => 'New Product',
                    'quantity' => 5,
                    'unit_price' => 1200,
                    'total_amount' => 6000,
                ]
            ],
            'subtotal' => 6000,
            'tax_amount' => 1140,
            'total_amount' => 7140,
        ];

        $response = $this->actingAs($user)
            ->post(route('invoices.store'), $invoiceData);

        $response->assertRedirect();
        $invoice = TaxDocument::where('customer_id', $newCustomer->id)->first();
        $this->assertNotNull($invoice);
        $this->assertEquals('draft', $invoice->status);
        $this->assertEquals(7140, $invoice->total_amount);

        // Step 4: Send the invoice (issue it)
        $response = $this->actingAs($user)
            ->post(route('invoices.send', $invoice));

        $response->assertRedirect();
        $invoice->refresh();
        $this->assertEquals('issued', $invoice->status);

        // Step 5: Check that product stock was reduced
        $newProduct->refresh();
        $this->assertEquals(45, $newProduct->stock_quantity); // 50 - 5 = 45

        // Step 6: Download the invoice PDF
        $response = $this->actingAs($user)
            ->get(route('invoices.download', $invoice));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        // Step 7: Register a payment for the invoice
        $paymentData = [
            'customer_id' => $newCustomer->id,
            'amount' => 7140,
            'payment_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
            'reference' => 'TRF-123456',
            'notes' => 'Payment for invoice',
            'allocations' => [
                [
                    'document_id' => $invoice->id,
                    'amount' => 7140,
                ]
            ],
        ];

        $response = $this->actingAs($user)
            ->post(route('payments.store'), $paymentData);

        $response->assertRedirect();
        
        $payment = Payment::where('customer_id', $newCustomer->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals(7140, $payment->amount);

        // Check that invoice status changed to paid
        $invoice->refresh();
        $this->assertEquals('paid', $invoice->status);

        // Step 8: Generate sales book for the month
        $response = $this->actingAs($user)
            ->post(route('tax-books.sales.generate'), [
                'year' => now()->year,
                'month' => now()->month,
            ]);

        $response->assertRedirect();
        
        $salesBook = SalesBook::where('tenant_id', $this->tenant->id)
            ->where('year', now()->year)
            ->where('month', now()->month)
            ->first();

        $this->assertNotNull($salesBook);
        $this->assertEquals(1, $salesBook->total_documents);
        $this->assertEquals(6000, $salesBook->total_net);
        $this->assertEquals(1140, $salesBook->total_tax);
        $this->assertEquals(7140, $salesBook->total_amount);

        // Step 9: Check sales book contains our invoice
        $this->assertCount(1, $salesBook->entries);
        $entry = $salesBook->entries->first();
        $this->assertEquals($invoice->id, $entry->tax_document_id);
        $this->assertEquals($newCustomer->name, $entry->customer_name);
        $this->assertEquals($invoice->document_number, $entry->document_number);
    }

    /** @test */
    public function customer_statement_workflow()
    {
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo([
            'customers.view',
            'invoices.view', 'invoices.create', 'invoices.send',
            'payments.view', 'payments.create',
        ]);

        // Create multiple invoices for the customer
        $invoice1 = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'type' => 'invoice',
            'total_amount' => 10000,
            'status' => 'issued',
            'issue_date' => now()->subDays(10),
            'due_date' => now()->addDays(20),
        ]);

        $invoice2 = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'type' => 'invoice',
            'total_amount' => 15000,
            'status' => 'issued',
            'issue_date' => now()->subDays(5),
            'due_date' => now()->addDays(25),
        ]);

        // Pay the first invoice partially
        Payment::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'amount' => 5000,
            'payment_date' => now(),
            'payment_method' => 'cash',
            'reference' => 'CASH-001',
        ]);

        // Allocate payment to first invoice
        \App\Models\PaymentAllocation::create([
            'payment_id' => Payment::first()->id,
            'tax_document_id' => $invoice1->id,
            'amount' => 5000,
        ]);

        // Update invoice status
        $invoice1->update(['status' => 'partial']);

        // View customer statement
        $response = $this->actingAs($user)
            ->get(route('customers.statement', $this->customer));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => 
            $page->component('Customers/Statement')
                ->where('customer.id', $this->customer->id)
                ->has('documents', 2)
                ->has('payments', 1)
                ->where('summary.total_debt', 20000) // Total invoiced
                ->where('summary.total_paid', 5000)  // Total paid
                ->where('summary.balance', 15000)    // Outstanding balance
        );

        // Check that the statement shows correct balances
        $response->assertInertia(fn (Assert $page) => 
            $page->where('documents.0.balance', 5000)  // First invoice balance after payment
                ->where('documents.1.balance', 15000)   // Second invoice full amount
        );
    }

    /** @test */
    public function product_inventory_workflow()
    {
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo([
            'products.view', 'products.manage_inventory',
            'invoices.view', 'invoices.create', 'invoices.send',
        ]);

        // Initial stock check
        $this->assertEquals(100, $this->product->stock_quantity);

        // Create and send an invoice that uses the product
        $invoice = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'type' => 'invoice',
            'status' => 'draft',
        ]);

        $invoice->items()->create([
            'product_id' => $this->product->id,
            'description' => $this->product->name,
            'quantity' => 25,
            'unit_price' => $this->product->sale_price,
            'total_amount' => 25 * $this->product->sale_price,
        ]);

        // Send the invoice (should reduce stock)
        $response = $this->actingAs($user)
            ->post(route('invoices.send', $invoice));

        $response->assertRedirect();

        // Check stock was reduced
        $this->product->refresh();
        $this->assertEquals(75, $this->product->stock_quantity); // 100 - 25 = 75

        // Check inventory movement was created
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->product->id,
            'type' => 'sale',
            'quantity' => -25,
            'reference_type' => 'tax_document',
            'reference_id' => $invoice->id,
        ]);

        // Manual stock adjustment
        $response = $this->actingAs($user)
            ->post(route('products.update-stock', $this->product), [
                'adjustment_type' => 'add',
                'quantity' => 50,
                'notes' => 'Stock replenishment',
            ]);

        $response->assertRedirect();

        // Check stock was increased
        $this->product->refresh();
        $this->assertEquals(125, $this->product->stock_quantity); // 75 + 50 = 125

        // Check another inventory movement was created
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->product->id,
            'type' => 'adjustment',
            'quantity' => 50,
            'reference_type' => 'manual',
            'notes' => 'Stock replenishment',
        ]);
    }

    /** @test */
    public function multi_tenant_isolation_workflow()
    {
        // Create another tenant
        $anotherTenant = \App\Models\Tenant::create([
            'name' => 'Another Company',
            'rut' => '87654321-0',
            'email' => 'another@company.com',
        ]);

        $anotherUser = \App\Models\User::create([
            'name' => 'Another User',
            'email' => 'another@user.com',
            'password' => bcrypt('password'),
            'tenant_id' => $anotherTenant->id,
            'email_verified_at' => now(),
        ]);

        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo(['customers.view', 'invoices.view']);

        // Create data for both tenants
        $myInvoice = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $anotherCustomer = Customer::create([
            'tenant_id' => $anotherTenant->id,
            'name' => 'Another Customer',
            'rut' => '11111111-1',
            'email' => 'another@customer.com',
        ]);

        $anotherInvoice = TaxDocument::factory()->create([
            'tenant_id' => $anotherTenant->id,
            'customer_id' => $anotherCustomer->id,
        ]);

        // User from first tenant should only see their data
        $response = $this->actingAs($user)
            ->get(route('invoices.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => 
            $page->has('invoices.data', 1)
                ->where('invoices.data.0.id', $myInvoice->id)
        );

        // User should not be able to access other tenant's invoice
        $response = $this->actingAs($user)
            ->get(route('invoices.show', $anotherInvoice));

        $response->assertStatus(403);

        // User should not be able to access other tenant's customer
        $response = $this->actingAs($user)
            ->get(route('customers.show', $anotherCustomer));

        $response->assertStatus(403);
    }
}