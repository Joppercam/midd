<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\TaxDocument;
use App\Services\SII\SIIService;
use Tests\TestCase;
use Mockery;

class SIIServiceTest extends TestCase
{
    private SIIService $service;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SIIService::class);

        $this->customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Customer',
            'rut' => '12345678-9',
            'email' => 'customer@test.com',
        ]);
    }

    /** @test */
    public function it_can_determine_document_type_code()
    {
        $invoice = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'type' => 'invoice',
        ]);

        $creditNote = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'type' => 'credit_note',
        ]);

        $debitNote = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'type' => 'debit_note',
        ]);

        $receipt = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'type' => 'receipt',
        ]);

        // Test document type code mapping
        $this->assertEquals('33', $this->service->getDocumentTypeCode($invoice));
        $this->assertEquals('61', $this->service->getDocumentTypeCode($creditNote));
        $this->assertEquals('56', $this->service->getDocumentTypeCode($debitNote));
        $this->assertEquals('39', $this->service->getDocumentTypeCode($receipt));
    }

    /** @test */
    public function it_validates_tenant_has_certificate()
    {
        // Test with tenant that has no certificate
        $result = $this->service->validateTenantConfiguration($this->tenant);
        
        $this->assertFalse($result['is_valid']);
        $this->assertContains('Certificado digital no configurado', $result['errors']);
    }

    /** @test */
    public function it_validates_document_before_sending()
    {
        $document = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'type' => 'invoice',
            'status' => 'draft',
        ]);

        $result = $this->service->validateDocumentForSending($document);
        
        $this->assertFalse($result['is_valid']);
    }

    /** @test */
    public function it_can_generate_document_xml()
    {
        $document = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'type' => 'invoice',
            'document_number' => 'F001-123',
            'issue_date' => now(),
            'subtotal' => 10000,
            'tax_amount' => 1900,
            'total_amount' => 11900,
        ]);

        $xml = $this->service->generateDocumentXML($document);
        
        $this->assertIsString($xml);
        $this->assertStringContains('<?xml', $xml);
        $this->assertStringContains('DTE', $xml);
        $this->assertStringContains('F001-123', $xml);
    }

    /** @test */
    public function it_builds_correct_document_structure()
    {
        $document = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'type' => 'invoice',
            'document_number' => 'F001-123',
            'issue_date' => now(),
            'subtotal' => 10000,
            'tax_amount' => 1900,
            'total_amount' => 11900,
        ]);

        $dteData = $this->service->buildDTEData($document);
        
        $this->assertIsArray($dteData);
        $this->assertArrayHasKey('Encabezado', $dteData);
        $this->assertArrayHasKey('Detalle', $dteData);
        $this->assertArrayHasKey('Totales', $dteData);
        
        // Check header
        $this->assertEquals('33', $dteData['Encabezado']['IdDoc']['TipoDTE']);
        $this->assertEquals('F001-123', $dteData['Encabezado']['IdDoc']['Folio']);
        
        // Check totals
        $this->assertEquals(11900, $dteData['Totales']['MntTotal']);
        $this->assertEquals(10000, $dteData['Totales']['MntNeto']);
        $this->assertEquals(1900, $dteData['Totales']['IVA']);
    }

    /** @test */
    public function it_formats_amounts_correctly()
    {
        $document = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 10000.50,
            'tax_amount' => 1900.10,
            'total_amount' => 11900.60,
        ]);

        $dteData = $this->service->buildDTEData($document);
        
        // Amounts should be integers (SII requirement)
        $this->assertEquals(11901, $dteData['Totales']['MntTotal']);
        $this->assertEquals(10001, $dteData['Totales']['MntNeto']);
        $this->assertEquals(1900, $dteData['Totales']['IVA']);
    }

    /** @test */
    public function it_handles_different_customer_types()
    {
        // Customer with RUT
        $customerWithRut = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Customer with RUT',
            'rut' => '12345678-9',
        ]);

        $document = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customerWithRut->id,
        ]);

        $dteData = $this->service->buildDTEData($document);
        
        $this->assertEquals('12345678-9', $dteData['Encabezado']['Receptor']['RUTRecep']);
        $this->assertEquals('Customer with RUT', $dteData['Encabezado']['Receptor']['RznSocRecep']);
    }

    /** @test */
    public function it_generates_tracking_number()
    {
        $trackingNumber = $this->service->generateTrackingNumber();
        
        $this->assertIsString($trackingNumber);
        $this->assertEquals(32, strlen($trackingNumber)); // Should be a 32-character hash
    }

    /** @test */
    public function it_validates_rut_format()
    {
        $this->assertTrue($this->service->validateRut('12345678-9'));
        $this->assertTrue($this->service->validateRut('123456789'));
        $this->assertFalse($this->service->validateRut('invalid-rut'));
        $this->assertFalse($this->service->validateRut(''));
    }

    /** @test */
    public function it_formats_date_for_sii()
    {
        $date = now()->setDate(2025, 5, 26);
        $formatted = $this->service->formatDateForSII($date);
        
        $this->assertEquals('2025-05-26', $formatted);
    }

    /** @test */
    public function it_can_mock_sending_document()
    {
        // Mock the HTTP client for testing
        $document = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'type' => 'invoice',
            'status' => 'issued',
        ]);

        // Configure tenant with certificate (mock)
        $this->tenant->update([
            'certificate_uploaded_at' => now(),
            'sii_resolution_number' => '123',
            'sii_resolution_date' => now(),
        ]);

        // This would normally interact with SII, but we're testing the service logic
        $result = $this->service->sendDocument($document);
        
        // In a real test environment, this might return a mocked response
        $this->assertIsArray($result);
    }
}