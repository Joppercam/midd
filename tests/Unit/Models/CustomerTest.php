<?php

namespace Tests\Unit\Models;

use App\Models\Customer;
use App\Models\TaxDocument;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    /** @test */
    public function it_can_create_a_customer()
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Customer',
            'rut' => '12345678-9',
            'email' => 'customer@test.com',
            'phone' => '+56912345678',
            'address' => 'Test Address 123',
        ]);

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('Test Customer', $customer->name);
        $this->assertEquals('12345678-9', $customer->rut);
        $this->assertEquals('customer@test.com', $customer->email);
    }

    /** @test */
    public function it_belongs_to_tenant()
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertEquals($this->tenant->id, $customer->tenant_id);
        $this->assertInstanceOf(\App\Models\Tenant::class, $customer->tenant);
    }

    /** @test */
    public function it_has_many_tax_documents()
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $document1 = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);

        $document2 = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);

        $this->assertCount(2, $customer->taxDocuments);
        $this->assertTrue($customer->taxDocuments->contains($document1));
        $this->assertTrue($customer->taxDocuments->contains($document2));
    }

    /** @test */
    public function it_formats_rut_correctly()
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Customer',
            'rut' => '123456789',
            'email' => 'customer@test.com',
        ]);

        // Assuming we have a RUT formatter
        $this->assertNotEmpty($customer->formatted_rut);
    }

    /** @test */
    public function it_calculates_total_debt()
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Create unpaid documents
        TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'total_amount' => 10000,
            'status' => 'issued',
        ]);

        TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'total_amount' => 15000,
            'status' => 'issued',
        ]);

        // Create paid document (should not count)
        TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'total_amount' => 5000,
            'status' => 'paid',
        ]);

        $this->assertEquals(25000, $customer->total_debt);
    }

    /** @test */
    public function it_calculates_overdue_debt()
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Create overdue document
        TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'total_amount' => 10000,
            'status' => 'issued',
            'due_date' => now()->subDays(10),
        ]);

        // Create current document
        TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'total_amount' => 15000,
            'status' => 'issued',
            'due_date' => now()->addDays(10),
        ]);

        $this->assertEquals(10000, $customer->overdue_debt);
    }

    /** @test */
    public function it_determines_if_customer_has_debt()
    {
        $customerWithDebt = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $customerWithoutDebt = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customerWithDebt->id,
            'total_amount' => 10000,
            'status' => 'issued',
        ]);

        $this->assertTrue($customerWithDebt->has_debt);
        $this->assertFalse($customerWithoutDebt->has_debt);
    }

    /** @test */
    public function it_scopes_customers_with_debt()
    {
        $customerWithDebt = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $customerWithoutDebt = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customerWithDebt->id,
            'total_amount' => 10000,
            'status' => 'issued',
        ]);

        $customersWithDebt = Customer::withDebt()->get();

        $this->assertCount(1, $customersWithDebt);
        $this->assertTrue($customersWithDebt->contains($customerWithDebt));
        $this->assertFalse($customersWithDebt->contains($customerWithoutDebt));
    }

    /** @test */
    public function it_scopes_customers_with_overdue_debt()
    {
        $customerWithOverdueDebt = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $customerWithCurrentDebt = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Overdue document
        TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customerWithOverdueDebt->id,
            'total_amount' => 10000,
            'status' => 'issued',
            'due_date' => now()->subDays(10),
        ]);

        // Current document
        TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customerWithCurrentDebt->id,
            'total_amount' => 15000,
            'status' => 'issued',
            'due_date' => now()->addDays(10),
        ]);

        $customersWithOverdueDebt = Customer::withOverdueDebt()->get();

        $this->assertCount(1, $customersWithOverdueDebt);
        $this->assertTrue($customersWithOverdueDebt->contains($customerWithOverdueDebt));
        $this->assertFalse($customersWithOverdueDebt->contains($customerWithCurrentDebt));
    }

    /** @test */
    public function it_validates_required_fields()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        Customer::create([
            'tenant_id' => $this->tenant->id,
            // Missing required name and rut
        ]);
    }

    /** @test */
    public function it_searches_customers_by_name()
    {
        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'John Doe',
        ]);

        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Jane Smith',
        ]);

        $results = Customer::search('John')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results->first()->name);
    }

    /** @test */
    public function it_searches_customers_by_rut()
    {
        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Customer',
            'rut' => '12345678-9',
        ]);

        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Another Customer',
            'rut' => '98765432-1',
        ]);

        $results = Customer::search('12345678')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('12345678-9', $results->first()->rut);
    }
}