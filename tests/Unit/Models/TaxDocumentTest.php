<?php

namespace Tests\Unit\Models;

use App\Models\Customer;
use App\Models\TaxDocument;
use App\Models\TaxDocumentItem;
use App\Models\Category;
use Tests\TestCase;

class TaxDocumentTest extends TestCase
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
    }

    /** @test */
    public function it_can_create_a_tax_document()
    {
        $document = TaxDocument::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'type' => 'invoice',
            'document_number' => 'F001-123',
            'issue_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 10000,
            'tax_amount' => 1900,
            'total_amount' => 11900,
            'status' => 'issued',
        ]);

        $this->assertInstanceOf(TaxDocument::class, $document);
        $this->assertEquals('invoice', $document->type);
        $this->assertEquals('F001-123', $document->document_number);
        $this->assertEquals(10000, $document->subtotal);
        $this->assertEquals(1900, $document->tax_amount);
        $this->assertEquals(11900, $document->total_amount);
    }

    /** @test */
    public function it_belongs_to_tenant()
    {
        $document = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $this->assertEquals($this->tenant->id, $document->tenant_id);
        $this->assertInstanceOf(\App\Models\Tenant::class, $document->tenant);
    }

    /** @test */
    public function it_belongs_to_customer()
    {
        $document = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $this->assertEquals($this->customer->id, $document->customer_id);
        $this->assertInstanceOf(Customer::class, $document->customer);
    }

    /** @test */
    public function it_has_many_items()
    {
        $document = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $item1 = TaxDocumentItem::create([
            'tax_document_id' => $document->id,
            'description' => 'Item 1',
            'quantity' => 2,
            'unit_price' => 1000,
            'total_amount' => 2000,
        ]);

        $item2 = TaxDocumentItem::create([
            'tax_document_id' => $document->id,
            'description' => 'Item 2',
            'quantity' => 1,
            'unit_price' => 1500,
            'total_amount' => 1500,
        ]);

        $this->assertCount(2, $document->items);
        $this->assertTrue($document->items->contains($item1));
        $this->assertTrue($document->items->contains($item2));
    }

    /** @test */
    public function it_calculates_tax_rate_correctly()
    {
        $document = TaxDocument::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'type' => 'invoice',
            'document_number' => 'F001-123',
            'issue_date' => now(),
            'subtotal' => 10000,
            'tax_amount' => 1900,
            'total_amount' => 11900,
            'status' => 'issued',
        ]);

        $this->assertEquals(19.0, $document->tax_rate);
    }

    /** @test */
    public function it_calculates_tax_rate_when_subtotal_is_zero()
    {
        $document = TaxDocument::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'type' => 'invoice',
            'document_number' => 'F001-123',
            'issue_date' => now(),
            'subtotal' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'status' => 'issued',
        ]);

        $this->assertEquals(0, $document->tax_rate);
    }

    /** @test */
    public function it_determines_if_document_is_overdue()
    {
        $overdueDocument = TaxDocument::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'type' => 'invoice',
            'document_number' => 'F001-123',
            'issue_date' => now()->subDays(45),
            'due_date' => now()->subDays(15),
            'subtotal' => 10000,
            'tax_amount' => 1900,
            'total_amount' => 11900,
            'status' => 'issued',
        ]);

        $currentDocument = TaxDocument::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'type' => 'invoice',
            'document_number' => 'F001-124',
            'issue_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 10000,
            'tax_amount' => 1900,
            'total_amount' => 11900,
            'status' => 'issued',
        ]);

        $this->assertTrue($overdueDocument->is_overdue);
        $this->assertFalse($currentDocument->is_overdue);
    }

    /** @test */
    public function it_determines_if_document_is_paid()
    {
        $document = TaxDocument::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'type' => 'invoice',
            'document_number' => 'F001-123',
            'issue_date' => now(),
            'subtotal' => 10000,
            'tax_amount' => 1900,
            'total_amount' => 11900,
            'status' => 'paid',
        ]);

        $this->assertTrue($document->is_paid);

        $document->status = 'issued';
        $this->assertFalse($document->is_paid);
    }

    /** @test */
    public function it_gets_status_label_correctly()
    {
        $document = TaxDocument::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'type' => 'invoice',
            'document_number' => 'F001-123',
            'issue_date' => now(),
            'subtotal' => 10000,
            'tax_amount' => 1900,
            'total_amount' => 11900,
            'status' => 'issued',
        ]);

        $this->assertEquals('Emitida', $document->status_label);

        $document->status = 'paid';
        $this->assertEquals('Pagada', $document->status_label);

        $document->status = 'cancelled';
        $this->assertEquals('Anulada', $document->status_label);
    }

    /** @test */
    public function it_gets_type_label_correctly()
    {
        $document = TaxDocument::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'type' => 'invoice',
            'document_number' => 'F001-123',
            'issue_date' => now(),
            'subtotal' => 10000,
            'tax_amount' => 1900,
            'total_amount' => 11900,
            'status' => 'issued',
        ]);

        $this->assertEquals('Factura', $document->type_label);

        $document->type = 'credit_note';
        $this->assertEquals('Nota de Crédito', $document->type_label);
    }

    /** @test */
    public function it_formats_amounts_correctly()
    {
        $document = TaxDocument::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'type' => 'invoice',
            'document_number' => 'F001-123',
            'issue_date' => now(),
            'subtotal' => 10000,
            'tax_amount' => 1900,
            'total_amount' => 11900,
            'status' => 'issued',
        ]);

        $this->assertEquals('10.000', $document->formatted_subtotal);
        $this->assertEquals('1.900', $document->formatted_tax_amount);
        $this->assertEquals('11.900', $document->formatted_total);
    }

    /** @test */
    public function it_scopes_by_status()
    {
        TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => 'issued',
        ]);

        TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => 'paid',
        ]);

        TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => 'cancelled',
        ]);

        $this->assertCount(1, TaxDocument::issued()->get());
        $this->assertCount(1, TaxDocument::paid()->get());
        $this->assertCount(1, TaxDocument::cancelled()->get());
    }

    /** @test */
    public function it_scopes_overdue_documents()
    {
        TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'due_date' => now()->subDays(10),
            'status' => 'issued',
        ]);

        TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'due_date' => now()->addDays(10),
            'status' => 'issued',
        ]);

        $this->assertCount(1, TaxDocument::overdue()->get());
    }
}