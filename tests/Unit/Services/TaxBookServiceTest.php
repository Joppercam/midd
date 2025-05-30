<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\TaxBooks\TaxBookService;
use App\Models\TaxDocument;
use App\Models\Expense;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\SalesBook;
use App\Models\PurchaseBook;
use App\Models\SalesBookEntry;
use App\Models\PurchaseBookEntry;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class TaxBookServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TaxBookService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new TaxBookService();
        
        // Create tenant and user
        $this->tenant = Tenant::factory()->create([
            'rut' => '76123456-7',
            'name' => 'Test Company'
        ]);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        
        // Set tenant context
        app()->instance('currentTenant', $this->tenant);
    }

    /** @test */
    public function it_can_generate_sales_book()
    {
        // Create customers
        $customer1 = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'rut' => '12345678-9',
            'name' => 'Customer 1'
        ]);
        
        $customer2 = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'rut' => '98765432-1',
            'name' => 'Customer 2'
        ]);
        
        // Create tax documents for the month
        TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'invoice',
            'number' => 101,
            'customer_id' => $customer1->id,
            'date' => Carbon::create(2025, 1, 15),
            'subtotal' => 100000,
            'tax_amount' => 19000,
            'total_amount' => 119000,
            'status' => 'issued'
        ]);
        
        TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'invoice',
            'number' => 102,
            'customer_id' => $customer2->id,
            'date' => Carbon::create(2025, 1, 20),
            'subtotal' => 200000,
            'tax_amount' => 38000,
            'total_amount' => 238000,
            'status' => 'issued'
        ]);
        
        // Generate sales book
        $salesBook = $this->service->generateSalesBook(2025, 1);
        
        $this->assertInstanceOf(SalesBook::class, $salesBook);
        $this->assertEquals(2025, $salesBook->year);
        $this->assertEquals(1, $salesBook->month);
        $this->assertEquals(300000, $salesBook->total_sales);
        $this->assertEquals(57000, $salesBook->total_tax);
        $this->assertEquals(357000, $salesBook->total_amount);
        
        // Check entries were created
        $entries = $salesBook->entries;
        $this->assertCount(2, $entries);
        
        // Verify entry details
        $entry1 = $entries->where('document_number', 101)->first();
        $this->assertEquals('12345678-9', $entry1->customer_rut);
        $this->assertEquals('Customer 1', $entry1->customer_name);
        $this->assertEquals(100000, $entry1->net_amount);
        $this->assertEquals(19000, $entry1->tax_amount);
    }

    /** @test */
    public function it_can_generate_purchase_book()
    {
        // Create suppliers
        $supplier1 = Supplier::factory()->create([
            'tenant_id' => $this->tenant->id,
            'rut' => '87654321-0',
            'name' => 'Supplier 1'
        ]);
        
        $supplier2 = Supplier::factory()->create([
            'tenant_id' => $this->tenant->id,
            'rut' => '11223344-5',
            'name' => 'Supplier 2'
        ]);
        
        // Create expenses for the month
        Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $supplier1->id,
            'document_type' => 'invoice',
            'document_number' => '501',
            'date' => Carbon::create(2025, 1, 10),
            'net_amount' => 50000,
            'tax_amount' => 9500,
            'total_amount' => 59500,
            'status' => 'approved'
        ]);
        
        Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $supplier2->id,
            'document_type' => 'invoice',
            'document_number' => '502',
            'date' => Carbon::create(2025, 1, 25),
            'net_amount' => 80000,
            'tax_amount' => 15200,
            'total_amount' => 95200,
            'status' => 'approved'
        ]);
        
        // Generate purchase book
        $purchaseBook = $this->service->generatePurchaseBook(2025, 1);
        
        $this->assertInstanceOf(PurchaseBook::class, $purchaseBook);
        $this->assertEquals(2025, $purchaseBook->year);
        $this->assertEquals(1, $purchaseBook->month);
        $this->assertEquals(130000, $purchaseBook->total_purchases);
        $this->assertEquals(24700, $purchaseBook->total_tax);
        $this->assertEquals(154700, $purchaseBook->total_amount);
        
        // Check entries were created
        $entries = $purchaseBook->entries;
        $this->assertCount(2, $entries);
        
        // Verify entry details
        $entry1 = $entries->where('document_number', '501')->first();
        $this->assertEquals('87654321-0', $entry1->supplier_rut);
        $this->assertEquals('Supplier 1', $entry1->supplier_name);
        $this->assertEquals(50000, $entry1->net_amount);
        $this->assertEquals(9500, $entry1->tax_amount);
    }

    /** @test */
    public function it_includes_credit_notes_in_sales_book()
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'rut' => '12345678-9'
        ]);
        
        // Create invoice
        TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'invoice',
            'number' => 100,
            'customer_id' => $customer->id,
            'date' => Carbon::create(2025, 1, 10),
            'subtotal' => 100000,
            'tax_amount' => 19000,
            'total_amount' => 119000,
            'status' => 'issued'
        ]);
        
        // Create credit note
        TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'credit_note',
            'number' => 50,
            'customer_id' => $customer->id,
            'date' => Carbon::create(2025, 1, 15),
            'subtotal' => -20000,
            'tax_amount' => -3800,
            'total_amount' => -23800,
            'status' => 'issued'
        ]);
        
        $salesBook = $this->service->generateSalesBook(2025, 1);
        
        // Net sales should be reduced by credit note
        $this->assertEquals(80000, $salesBook->total_sales);
        $this->assertEquals(15200, $salesBook->total_tax);
        $this->assertEquals(95200, $salesBook->total_amount);
        
        // Both documents should be in entries
        $this->assertCount(2, $salesBook->entries);
    }

    /** @test */
    public function it_excludes_draft_and_cancelled_documents()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        
        // Create various document statuses
        TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'invoice',
            'customer_id' => $customer->id,
            'date' => Carbon::create(2025, 1, 10),
            'total_amount' => 100000,
            'status' => 'issued'
        ]);
        
        TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'invoice',
            'customer_id' => $customer->id,
            'date' => Carbon::create(2025, 1, 11),
            'total_amount' => 50000,
            'status' => 'draft'
        ]);
        
        TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'invoice',
            'customer_id' => $customer->id,
            'date' => Carbon::create(2025, 1, 12),
            'total_amount' => 75000,
            'status' => 'cancelled'
        ]);
        
        $salesBook = $this->service->generateSalesBook(2025, 1);
        
        // Only issued document should be included
        $this->assertCount(1, $salesBook->entries);
        $this->assertEquals(84034, $salesBook->total_sales); // 100000 / 1.19
    }

    /** @test */
    public function it_respects_tenant_isolation_in_tax_books()
    {
        // Create documents for another tenant
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        
        TaxDocument::factory()->create([
            'tenant_id' => $otherTenant->id,
            'type' => 'invoice',
            'customer_id' => $otherCustomer->id,
            'date' => Carbon::create(2025, 1, 10),
            'total_amount' => 500000,
            'status' => 'issued'
        ]);
        
        // Create document for current tenant
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'invoice',
            'customer_id' => $customer->id,
            'date' => Carbon::create(2025, 1, 10),
            'total_amount' => 100000,
            'status' => 'issued'
        ]);
        
        $salesBook = $this->service->generateSalesBook(2025, 1);
        
        // Should only include current tenant's document
        $this->assertCount(1, $salesBook->entries);
        $this->assertEquals(84034, $salesBook->total_sales);
    }

    /** @test */
    public function it_can_get_summary_for_period()
    {
        // Create documents across multiple months
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        
        // January
        TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'invoice',
            'customer_id' => $customer->id,
            'date' => Carbon::create(2025, 1, 15),
            'subtotal' => 100000,
            'tax_amount' => 19000,
            'total_amount' => 119000,
            'status' => 'issued'
        ]);
        
        // February
        TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'invoice',
            'customer_id' => $customer->id,
            'date' => Carbon::create(2025, 2, 15),
            'subtotal' => 200000,
            'tax_amount' => 38000,
            'total_amount' => 238000,
            'status' => 'issued'
        ]);
        
        // March
        TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'invoice',
            'customer_id' => $customer->id,
            'date' => Carbon::create(2025, 3, 15),
            'subtotal' => 150000,
            'tax_amount' => 28500,
            'total_amount' => 178500,
            'status' => 'issued'
        ]);
        
        $summary = $this->service->getSummaryForPeriod(
            Carbon::create(2025, 1, 1),
            Carbon::create(2025, 3, 31)
        );
        
        $this->assertArrayHasKey('sales', $summary);
        $this->assertArrayHasKey('purchases', $summary);
        $this->assertArrayHasKey('net_tax', $summary);
        
        $this->assertEquals(450000, $summary['sales']['total_net']);
        $this->assertEquals(85500, $summary['sales']['total_tax']);
        $this->assertEquals(535500, $summary['sales']['total_amount']);
    }

    /** @test */
    public function it_handles_exempt_sales_correctly()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        
        // Create exempt invoice
        TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'exempt_invoice',
            'number' => 1,
            'customer_id' => $customer->id,
            'date' => Carbon::create(2025, 1, 10),
            'subtotal' => 50000,
            'tax_amount' => 0,
            'total_amount' => 50000,
            'status' => 'issued'
        ]);
        
        $salesBook = $this->service->generateSalesBook(2025, 1);
        
        $this->assertEquals(50000, $salesBook->total_sales);
        $this->assertEquals(0, $salesBook->total_tax);
        $this->assertEquals(50000, $salesBook->total_exempt);
    }

    /** @test */
    public function it_can_export_to_sii_format()
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'rut' => '12345678-9',
            'name' => 'Test Customer'
        ]);
        
        TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'invoice',
            'number' => 100,
            'customer_id' => $customer->id,
            'date' => Carbon::create(2025, 1, 15),
            'subtotal' => 100000,
            'tax_amount' => 19000,
            'total_amount' => 119000,
            'status' => 'issued'
        ]);
        
        $salesBook = $this->service->generateSalesBook(2025, 1);
        $siiData = $this->service->exportToSIIFormat($salesBook);
        
        $this->assertArrayHasKey('RutEmisorLibro', $siiData);
        $this->assertArrayHasKey('PeriodoTributario', $siiData);
        $this->assertArrayHasKey('TipoOperacion', $siiData);
        $this->assertArrayHasKey('TipoLibro', $siiData);
        $this->assertArrayHasKey('ResumenPeriodo', $siiData);
        
        $this->assertEquals('76123456-7', $siiData['RutEmisorLibro']);
        $this->assertEquals('2025-01', $siiData['PeriodoTributario']);
        $this->assertEquals('VENTA', $siiData['TipoOperacion']);
    }

    /** @test */
    public function it_prevents_duplicate_book_generation()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        
        TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'invoice',
            'customer_id' => $customer->id,
            'date' => Carbon::create(2025, 1, 15),
            'total_amount' => 100000,
            'status' => 'issued'
        ]);
        
        // Generate first book
        $salesBook1 = $this->service->generateSalesBook(2025, 1);
        
        // Try to generate again
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Sales book for this period already exists');
        
        $this->service->generateSalesBook(2025, 1);
    }

    /** @test */
    public function it_can_regenerate_book_when_forced()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        
        TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'invoice',
            'customer_id' => $customer->id,
            'date' => Carbon::create(2025, 1, 15),
            'total_amount' => 100000,
            'status' => 'issued'
        ]);
        
        // Generate first book
        $salesBook1 = $this->service->generateSalesBook(2025, 1);
        
        // Add another document
        TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'invoice',
            'customer_id' => $customer->id,
            'date' => Carbon::create(2025, 1, 20),
            'total_amount' => 50000,
            'status' => 'issued'
        ]);
        
        // Regenerate with force
        $salesBook2 = $this->service->generateSalesBook(2025, 1, true);
        
        $this->assertNotEquals($salesBook1->id, $salesBook2->id);
        $this->assertCount(2, $salesBook2->entries);
    }

    /** @test */
    public function it_calculates_tax_credit_utilization()
    {
        // Create supplier and purchases
        $supplier = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);
        
        Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $supplier->id,
            'date' => Carbon::create(2025, 1, 10),
            'net_amount' => 200000,
            'tax_amount' => 38000,
            'total_amount' => 238000,
            'tax_credit_usable' => true,
            'status' => 'approved'
        ]);
        
        Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $supplier->id,
            'date' => Carbon::create(2025, 1, 15),
            'net_amount' => 100000,
            'tax_amount' => 19000,
            'total_amount' => 119000,
            'tax_credit_usable' => false, // Not usable
            'status' => 'approved'
        ]);
        
        $purchaseBook = $this->service->generatePurchaseBook(2025, 1);
        
        $this->assertEquals(300000, $purchaseBook->total_purchases);
        $this->assertEquals(57000, $purchaseBook->total_tax);
        $this->assertEquals(38000, $purchaseBook->usable_tax_credit); // Only first expense
    }

    /** @test */
    public function it_validates_document_integrity()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        
        // Create document with mismatched totals
        $document = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'invoice',
            'customer_id' => $customer->id,
            'date' => Carbon::create(2025, 1, 15),
            'subtotal' => 100000,
            'tax_amount' => 19000,
            'total_amount' => 120000, // Should be 119000
            'status' => 'issued'
        ]);
        
        $salesBook = $this->service->generateSalesBook(2025, 1);
        
        // Check that validation warnings are included
        $this->assertArrayHasKey('warnings', $salesBook->metadata);
        $this->assertContains(
            "Document {$document->number} has mismatched totals",
            $salesBook->metadata['warnings']
        );
    }
}