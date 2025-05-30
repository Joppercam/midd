<?php

namespace Tests\Feature\Controllers;

use App\Models\Customer;
use App\Models\TaxDocument;
use App\Models\Category;
use Tests\TestCase;
use Inertia\Testing\AssertableInertia as Assert;

class InvoiceControllerTest extends TestCase
{
    private Customer $customer;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Customer',
            'rut' => '12345678-9',
            'email' => 'customer@test.com',
        ]);

        $this->category = Category::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Category',
            'type' => 'income',
        ]);

        // Create permissions
        $this->createPermission('invoices.view');
        $this->createPermission('invoices.create');
        $this->createPermission('invoices.edit');
        $this->createPermission('invoices.delete');
    }

    /** @test */
    public function it_can_display_invoices_index()
    {
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo('invoices.view');

        $invoice = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'type' => 'invoice',
        ]);

        $response = $this->actingAs($user)
            ->get(route('invoices.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => 
            $page->component('Billing/Invoices/Index')
                ->has('invoices.data', 1)
                ->where('invoices.data.0.id', $invoice->id)
        );
    }

    /** @test */
    public function it_denies_access_without_permission()
    {
        $user = $this->createUserWithRole('viewer');

        $response = $this->actingAs($user)
            ->get(route('invoices.index'));

        $response->assertStatus(403);
    }

    /** @test */
    public function it_can_display_create_form()
    {
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo(['invoices.view', 'invoices.create']);

        $response = $this->actingAs($user)
            ->get(route('invoices.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => 
            $page->component('Billing/Invoices/Create')
                ->has('customers')
                ->has('categories')
        );
    }

    /** @test */
    public function it_can_store_a_new_invoice()
    {
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo(['invoices.view', 'invoices.create']);

        $invoiceData = [
            'customer_id' => $this->customer->id,
            'type' => 'invoice',
            'issue_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'items' => [
                [
                    'description' => 'Test Item',
                    'quantity' => 2,
                    'unit_price' => 1000,
                    'total_amount' => 2000,
                ]
            ],
            'subtotal' => 2000,
            'tax_amount' => 380,
            'total_amount' => 2380,
        ];

        $response = $this->actingAs($user)
            ->post(route('invoices.store'), $invoiceData);

        $response->assertRedirect();
        $this->assertDatabaseHas('tax_documents', [
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'type' => 'invoice',
            'subtotal' => 2000,
            'tax_amount' => 380,
            'total_amount' => 2380,
        ]);
    }

    /** @test */
    public function it_validates_required_fields_when_storing()
    {
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo(['invoices.view', 'invoices.create']);

        $response = $this->actingAs($user)
            ->post(route('invoices.store'), []);

        $response->assertSessionHasErrors([
            'customer_id',
            'type',
            'issue_date',
            'items',
        ]);
    }

    /** @test */
    public function it_can_display_invoice_details()
    {
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo('invoices.view');

        $invoice = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'type' => 'invoice',
        ]);

        $response = $this->actingAs($user)
            ->get(route('invoices.show', $invoice));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => 
            $page->component('Billing/Invoices/Show')
                ->where('invoice.id', $invoice->id)
                ->has('invoice.customer')
                ->has('invoice.items')
        );
    }

    /** @test */
    public function it_can_display_edit_form()
    {
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo(['invoices.view', 'invoices.edit']);

        $invoice = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'type' => 'invoice',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user)
            ->get(route('invoices.edit', $invoice));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => 
            $page->component('Billing/Invoices/Edit')
                ->where('invoice.id', $invoice->id)
                ->has('customers')
                ->has('categories')
        );
    }

    /** @test */
    public function it_can_update_invoice()
    {
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo(['invoices.view', 'invoices.edit']);

        $invoice = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'type' => 'invoice',
            'status' => 'draft',
            'subtotal' => 1000,
        ]);

        $updateData = [
            'customer_id' => $this->customer->id,
            'type' => 'invoice',
            'issue_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'items' => [
                [
                    'description' => 'Updated Item',
                    'quantity' => 3,
                    'unit_price' => 1500,
                    'total_amount' => 4500,
                ]
            ],
            'subtotal' => 4500,
            'tax_amount' => 855,
            'total_amount' => 5355,
        ];

        $response = $this->actingAs($user)
            ->put(route('invoices.update', $invoice), $updateData);

        $response->assertRedirect();
        $this->assertDatabaseHas('tax_documents', [
            'id' => $invoice->id,
            'subtotal' => 4500,
            'tax_amount' => 855,
            'total_amount' => 5355,
        ]);
    }

    /** @test */
    public function it_cannot_update_issued_invoice()
    {
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo(['invoices.view', 'invoices.edit']);

        $invoice = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'type' => 'invoice',
            'status' => 'issued', // Cannot edit issued invoices
        ]);

        $response = $this->actingAs($user)
            ->put(route('invoices.update', $invoice), [
                'subtotal' => 2000,
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function it_can_delete_invoice()
    {
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo(['invoices.view', 'invoices.delete']);

        $invoice = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'type' => 'invoice',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user)
            ->delete(route('invoices.destroy', $invoice));

        $response->assertRedirect();
        $this->assertSoftDeleted('tax_documents', [
            'id' => $invoice->id,
        ]);
    }

    /** @test */
    public function it_cannot_delete_issued_invoice()
    {
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo(['invoices.view', 'invoices.delete']);

        $invoice = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'type' => 'invoice',
            'status' => 'issued',
        ]);

        $response = $this->actingAs($user)
            ->delete(route('invoices.destroy', $invoice));

        $response->assertStatus(403);
        $this->assertDatabaseHas('tax_documents', [
            'id' => $invoice->id,
        ]);
    }

    /** @test */
    public function it_can_send_invoice()
    {
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo(['invoices.view', 'invoices.send']);

        $invoice = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'type' => 'invoice',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user)
            ->post(route('invoices.send', $invoice));

        $response->assertRedirect();
        $this->assertDatabaseHas('tax_documents', [
            'id' => $invoice->id,
            'status' => 'issued',
        ]);
    }

    /** @test */
    public function it_can_download_invoice_pdf()
    {
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo(['invoices.view', 'invoices.download']);

        $invoice = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'type' => 'invoice',
        ]);

        $response = $this->actingAs($user)
            ->get(route('invoices.download', $invoice));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    /** @test */
    public function it_filters_invoices_by_status()
    {
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo('invoices.view');

        $draftInvoice = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => 'draft',
        ]);

        $issuedInvoice = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => 'issued',
        ]);

        $response = $this->actingAs($user)
            ->get(route('invoices.index', ['status' => 'draft']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => 
            $page->has('invoices.data', 1)
                ->where('invoices.data.0.id', $draftInvoice->id)
        );
    }

    /** @test */
    public function it_searches_invoices_by_document_number()
    {
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo('invoices.view');

        $invoice = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'document_number' => 'F001-123',
        ]);

        TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'document_number' => 'F001-456',
        ]);

        $response = $this->actingAs($user)
            ->get(route('invoices.index', ['search' => 'F001-123']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => 
            $page->has('invoices.data', 1)
                ->where('invoices.data.0.id', $invoice->id)
        );
    }

    /** @test */
    public function it_only_shows_invoices_from_current_tenant()
    {
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo('invoices.view');

        // Create another tenant
        $anotherTenant = \App\Models\Tenant::create([
            'name' => 'Another Company',
            'rut' => '87654321-0',
            'email' => 'another@company.com',
        ]);

        $anotherCustomer = Customer::create([
            'tenant_id' => $anotherTenant->id,
            'name' => 'Another Customer',
            'rut' => '11111111-1',
            'email' => 'another@customer.com',
        ]);

        // Invoice from current tenant
        $myInvoice = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        // Invoice from another tenant (should not be visible)
        TaxDocument::factory()->create([
            'tenant_id' => $anotherTenant->id,
            'customer_id' => $anotherCustomer->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('invoices.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => 
            $page->has('invoices.data', 1)
                ->where('invoices.data.0.id', $myInvoice->id)
        );
    }
}