<?php

namespace Tests\Feature\Controllers;

use App\Models\Customer;
use App\Models\TaxDocument;
use App\Models\SalesBook;
use App\Models\PurchaseBook;
use App\Models\Supplier;
use App\Models\Expense;
use App\Models\Category;
use Tests\TestCase;
use Inertia\Testing\AssertableInertia as Assert;

class TaxBookControllerTest extends TestCase
{
    private Customer $customer;
    private Supplier $supplier;
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

        $this->supplier = Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Supplier',
            'rut' => '98765432-1',
            'email' => 'supplier@test.com',
        ]);

        $this->category = Category::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Category',
            'type' => 'expense',
        ]);

        // Create permissions
        $this->createPermission('tax_books.view');
        $this->createPermission('tax_books.generate');
        $this->createPermission('tax_books.finalize');
        $this->createPermission('tax_books.export');
    }

    /** @test */
    public function it_can_display_tax_books_index()
    {
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo('tax_books.view');

        $response = $this->actingAs($user)
            ->get(route('tax-books.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => 
            $page->component('TaxBooks/Index')
                ->has('taxSummary')
                ->has('currentYear')
                ->has('currentMonth')
        );
    }

    /** @test */
    public function it_denies_access_without_permission()
    {
        $user = $this->createUserWithRole('viewer');

        $response = $this->actingAs($user)
            ->get(route('tax-books.index'));

        $response->assertStatus(403);
    }

    /** @test */
    public function it_can_generate_sales_book()
    {
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo(['tax_books.view', 'tax_books.generate']);

        // Create some tax documents for the current month
        TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'issue_date' => now(),
            'total_amount' => 11900,
            'tax_amount' => 1900,
            'subtotal' => 10000,
            'status' => 'issued',
        ]);

        $response = $this->actingAs($user)
            ->post(route('tax-books.sales.generate'), [
                'year' => now()->year,
                'month' => now()->month,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('sales_books', [
            'tenant_id' => $this->tenant->id,
            'year' => now()->year,
            'month' => now()->month,
            'status' => 'draft',
        ]);
    }

    /** @test */
    public function it_can_generate_purchase_book()
    {
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo(['tax_books.view', 'tax_books.generate']);

        // Create some expenses for the current month
        Expense::create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->supplier->id,
            'category_id' => $this->category->id,
            'description' => 'Test Expense',
            'amount' => 11900,
            'expense_date' => now(),
            'document_number' => 'F001-123',
            'document_type' => 'invoice',
        ]);

        $response = $this->actingAs($user)
            ->post(route('tax-books.purchase.generate'), [
                'year' => now()->year,
                'month' => now()->month,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('purchase_books', [
            'tenant_id' => $this->tenant->id,
            'year' => now()->year,
            'month' => now()->month,
            'status' => 'draft',
        ]);
    }

    /** @test */
    public function it_validates_required_fields_when_generating()
    {
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo(['tax_books.view', 'tax_books.generate']);

        $response = $this->actingAs($user)
            ->post(route('tax-books.sales.generate'), []);

        $response->assertSessionHasErrors(['year', 'month']);
    }

    /** @test */
    public function it_can_display_sales_book_details()
    {
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo('tax_books.view');

        $salesBook = SalesBook::create([
            'tenant_id' => $this->tenant->id,
            'year' => now()->year,
            'month' => now()->month,
            'status' => 'draft',
            'total_documents' => 1,
            'total_net' => 10000,
            'total_tax' => 1900,
            'total_amount' => 11900,
            'generated_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('tax-books.sales.show', $salesBook));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => 
            $page->component('TaxBooks/SalesShow')
                ->where('book.id', $salesBook->id)
                ->has('book.entries')
        );
    }

    /** @test */
    public function it_can_display_purchase_book_details()
    {
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo('tax_books.view');

        $purchaseBook = PurchaseBook::create([
            'tenant_id' => $this->tenant->id,
            'year' => now()->year,
            'month' => now()->month,
            'status' => 'draft',
            'total_documents' => 1,
            'total_net' => 10000,
            'total_tax' => 1900,
            'total_amount' => 11900,
            'generated_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('tax-books.purchase.show', $purchaseBook));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => 
            $page->component('TaxBooks/PurchaseShow')
                ->where('book.id', $purchaseBook->id)
                ->has('book.entries')
        );
    }

    /** @test */
    public function it_can_finalize_sales_book()
    {
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo(['tax_books.view', 'tax_books.finalize']);

        $salesBook = SalesBook::create([
            'tenant_id' => $this->tenant->id,
            'year' => now()->year,
            'month' => now()->month,
            'status' => 'draft',
            'total_documents' => 1,
            'generated_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->post(route('tax-books.sales.finalize', $salesBook));

        $response->assertRedirect();
        $this->assertDatabaseHas('sales_books', [
            'id' => $salesBook->id,
            'status' => 'final',
        ]);
    }

    /** @test */
    public function it_can_finalize_purchase_book()
    {
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo(['tax_books.view', 'tax_books.finalize']);

        $purchaseBook = PurchaseBook::create([
            'tenant_id' => $this->tenant->id,
            'year' => now()->year,
            'month' => now()->month,
            'status' => 'draft',
            'total_documents' => 1,
            'generated_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->post(route('tax-books.purchase.finalize', $purchaseBook));

        $response->assertRedirect();
        $this->assertDatabaseHas('purchase_books', [
            'id' => $purchaseBook->id,
            'status' => 'final',
        ]);
    }

    /** @test */
    public function it_cannot_finalize_already_final_book()
    {
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo(['tax_books.view', 'tax_books.finalize']);

        $salesBook = SalesBook::create([
            'tenant_id' => $this->tenant->id,
            'year' => now()->year,
            'month' => now()->month,
            'status' => 'final', // Already final
            'total_documents' => 1,
            'generated_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->post(route('tax-books.sales.finalize', $salesBook));

        $response->assertSessionHasErrors();
    }

    /** @test */
    public function it_can_export_sales_book_to_excel()
    {
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo(['tax_books.view', 'tax_books.export']);

        $salesBook = SalesBook::create([
            'tenant_id' => $this->tenant->id,
            'year' => now()->year,
            'month' => now()->month,
            'status' => 'final',
            'total_documents' => 1,
            'generated_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('tax-books.sales.export.excel', $salesBook));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /** @test */
    public function it_can_export_sales_book_to_pdf()
    {
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo(['tax_books.view', 'tax_books.export']);

        $salesBook = SalesBook::create([
            'tenant_id' => $this->tenant->id,
            'year' => now()->year,
            'month' => now()->month,
            'status' => 'final',
            'total_documents' => 1,
            'generated_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('tax-books.sales.export.pdf', $salesBook));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    /** @test */
    public function it_can_export_purchase_book_to_excel()
    {
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo(['tax_books.view', 'tax_books.export']);

        $purchaseBook = PurchaseBook::create([
            'tenant_id' => $this->tenant->id,
            'year' => now()->year,
            'month' => now()->month,
            'status' => 'final',
            'total_documents' => 1,
            'generated_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('tax-books.purchase.export.excel', $purchaseBook));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /** @test */
    public function it_filters_by_period()
    {
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo('tax_books.view');

        $response = $this->actingAs($user)
            ->get(route('tax-books.index', [
                'year' => 2024,
                'month' => 6,
            ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => 
            $page->where('currentYear', 2024)
                ->where('currentMonth', 6)
        );
    }

    /** @test */
    public function it_only_shows_books_from_current_tenant()
    {
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo('tax_books.view');

        // Create another tenant
        $anotherTenant = \App\Models\Tenant::create([
            'name' => 'Another Company',
            'rut' => '87654321-0',
            'email' => 'another@company.com',
        ]);

        // Book from current tenant
        $mySalesBook = SalesBook::create([
            'tenant_id' => $this->tenant->id,
            'year' => now()->year,
            'month' => now()->month,
            'status' => 'draft',
        ]);

        // Book from another tenant (should not be accessible)
        $otherSalesBook = SalesBook::create([
            'tenant_id' => $anotherTenant->id,
            'year' => now()->year,
            'month' => now()->month,
            'status' => 'draft',
        ]);

        // Should be able to access own book
        $response = $this->actingAs($user)
            ->get(route('tax-books.sales.show', $mySalesBook));
        $response->assertOk();

        // Should not be able to access other tenant's book
        $response = $this->actingAs($user)
            ->get(route('tax-books.sales.show', $otherSalesBook));
        $response->assertStatus(403);
    }

    /** @test */
    public function it_calculates_tax_summary_correctly()
    {
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo('tax_books.view');

        // Create sales book with tax
        SalesBook::create([
            'tenant_id' => $this->tenant->id,
            'year' => now()->year,
            'month' => now()->month,
            'total_tax' => 1900, // Sales tax (debit)
            'status' => 'draft',
        ]);

        // Create purchase book with tax
        PurchaseBook::create([
            'tenant_id' => $this->tenant->id,
            'year' => now()->year,
            'month' => now()->month,
            'total_tax' => 950, // Purchase tax (credit)
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user)
            ->get(route('tax-books.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => 
            $page->where('taxSummary.sales_tax', 1900)
                ->where('taxSummary.purchase_tax', 950)
                ->where('taxSummary.balance', 950) // 1900 - 950 = 950 to pay
                ->where('taxSummary.to_pay', 950)
                ->where('taxSummary.credit', 0)
        );
    }
}