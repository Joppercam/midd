<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\InventoryService;
use App\Models\Product;
use App\Models\InventoryMovement;
use App\Models\TaxDocument;
use App\Models\TaxDocumentItem;
use App\Models\PurchaseOrderReceipt;
use App\Models\PurchaseOrderReceiptItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Carbon\Carbon;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected InventoryService $service;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new InventoryService();
        
        // Create tenant and user
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        
        // Create product
        $this->product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'current_stock' => 100,
            'unit_cost' => 1000,
            'manages_inventory' => true
        ]);
        
        // Set tenant context
        app()->instance('currentTenant', $this->tenant);
    }

    /** @test */
    public function it_can_record_sale_movement()
    {
        // Create tax document (invoice)
        $document = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'invoice',
            'status' => 'issued'
        ]);
        
        $item = TaxDocumentItem::factory()->create([
            'tax_document_id' => $document->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit_price' => 2000
        ]);
        
        // Record sale
        $movement = $this->service->recordSale($document);
        
        $this->assertCount(1, $movement);
        $this->assertEquals('sale', $movement[0]->type);
        $this->assertEquals(-5, $movement[0]->quantity);
        $this->assertEquals(95, $movement[0]->stock_after);
        
        // Check product stock was updated
        $this->product->refresh();
        $this->assertEquals(95, $this->product->current_stock);
    }

    /** @test */
    public function it_can_record_purchase_movement()
    {
        // Create purchase receipt
        $receipt = PurchaseOrderReceipt::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'completed'
        ]);
        
        $receiptItem = PurchaseOrderReceiptItem::factory()->create([
            'purchase_order_receipt_id' => $receipt->id,
            'product_id' => $this->product->id,
            'quantity_received' => 20
        ]);
        
        // Record purchase
        $movement = $this->service->recordPurchase($receipt);
        
        $this->assertCount(1, $movement);
        $this->assertEquals('purchase', $movement[0]->type);
        $this->assertEquals(20, $movement[0]->quantity);
        $this->assertEquals(120, $movement[0]->stock_after);
        
        // Check product stock was updated
        $this->product->refresh();
        $this->assertEquals(120, $this->product->current_stock);
    }

    /** @test */
    public function it_can_record_manual_adjustment()
    {
        // Record adjustment
        $movement = $this->service->recordAdjustment(
            $this->product->id,
            -10,
            'Damaged items',
            'damage'
        );
        
        $this->assertInstanceOf(InventoryMovement::class, $movement);
        $this->assertEquals('adjustment', $movement->type);
        $this->assertEquals(-10, $movement->quantity);
        $this->assertEquals(90, $movement->stock_after);
        $this->assertEquals('Damaged items', $movement->notes);
        $this->assertEquals('damage', $movement->adjustment_reason);
        
        // Check product stock was updated
        $this->product->refresh();
        $this->assertEquals(90, $this->product->current_stock);
    }

    /** @test */
    public function it_can_record_return_movement()
    {
        // Create original sale document
        $document = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'invoice',
            'status' => 'issued'
        ]);
        
        // Record return
        $movement = $this->service->recordReturn(
            $this->product->id,
            3,
            'return',
            $document->id,
            'Customer return - defective'
        );
        
        $this->assertInstanceOf(InventoryMovement::class, $movement);
        $this->assertEquals('return', $movement->type);
        $this->assertEquals(3, $movement->quantity);
        $this->assertEquals(103, $movement->stock_after);
        $this->assertEquals($document->id, $movement->reference_id);
        
        // Check product stock was updated
        $this->product->refresh();
        $this->assertEquals(103, $this->product->current_stock);
    }

    /** @test */
    public function it_can_check_stock_availability()
    {
        // Check available stock
        $this->assertTrue($this->service->checkAvailability($this->product->id, 50));
        $this->assertTrue($this->service->checkAvailability($this->product->id, 100));
        $this->assertFalse($this->service->checkAvailability($this->product->id, 101));
        
        // Product that doesn't manage inventory
        $nonInventoryProduct = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'manages_inventory' => false
        ]);
        
        $this->assertTrue($this->service->checkAvailability($nonInventoryProduct->id, 1000));
    }

    /** @test */
    public function it_can_get_stock_levels()
    {
        // Create additional products
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_stock' => 5,
            'minimum_stock' => 10,
            'manages_inventory' => true
        ]);
        
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_stock' => 0,
            'manages_inventory' => true
        ]);
        
        $levels = $this->service->getStockLevels();
        
        $this->assertArrayHasKey('total_products', $levels);
        $this->assertArrayHasKey('low_stock_count', $levels);
        $this->assertArrayHasKey('out_of_stock_count', $levels);
        $this->assertArrayHasKey('total_value', $levels);
        
        $this->assertEquals(3, $levels['total_products']);
        $this->assertEquals(1, $levels['low_stock_count']);
        $this->assertEquals(1, $levels['out_of_stock_count']);
    }

    /** @test */
    public function it_can_get_product_movement_history()
    {
        // Create various movements
        InventoryMovement::factory()->create([
            'product_id' => $this->product->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'purchase',
            'quantity' => 50,
            'created_at' => now()->subDays(5)
        ]);
        
        InventoryMovement::factory()->create([
            'product_id' => $this->product->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'sale',
            'quantity' => -20,
            'created_at' => now()->subDays(3)
        ]);
        
        InventoryMovement::factory()->create([
            'product_id' => $this->product->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'adjustment',
            'quantity' => -5,
            'created_at' => now()->subDay()
        ]);
        
        $history = $this->service->getMovementHistory($this->product->id, 30);
        
        $this->assertCount(3, $history);
        $this->assertEquals('adjustment', $history[0]->type); // Most recent first
        $this->assertEquals('sale', $history[1]->type);
        $this->assertEquals('purchase', $history[2]->type);
    }

    /** @test */
    public function it_can_calculate_inventory_valuation()
    {
        // Create products with different costs
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_stock' => 50,
            'unit_cost' => 2000,
            'manages_inventory' => true
        ]);
        
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_stock' => 30,
            'unit_cost' => 5000,
            'manages_inventory' => true
        ]);
        
        $valuation = $this->service->calculateValuation();
        
        $this->assertArrayHasKey('total_value', $valuation);
        $this->assertArrayHasKey('by_category', $valuation);
        $this->assertArrayHasKey('top_valued_products', $valuation);
        
        // 100*1000 + 50*2000 + 30*5000 = 350000
        $this->assertEquals(350000, $valuation['total_value']);
    }

    /** @test */
    public function it_can_update_costs_from_purchase()
    {
        // Create purchase with new cost
        $receipt = PurchaseOrderReceipt::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);
        
        $receiptItem = PurchaseOrderReceiptItem::factory()->create([
            'purchase_order_receipt_id' => $receipt->id,
            'product_id' => $this->product->id,
            'quantity_received' => 50,
            'unit_cost' => 1200 // New cost
        ]);
        
        // Update costs
        $this->service->updateCostsFromPurchase($receipt);
        
        // Check weighted average cost calculation
        // (100 * 1000 + 50 * 1200) / 150 = 1066.67
        $this->product->refresh();
        $this->assertEquals(1067, round($this->product->unit_cost));
    }

    /** @test */
    public function it_prevents_negative_stock_for_managed_inventory()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient stock');
        
        // Try to sell more than available
        $document = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'invoice'
        ]);
        
        TaxDocumentItem::factory()->create([
            'tax_document_id' => $document->id,
            'product_id' => $this->product->id,
            'quantity' => 150 // More than current stock
        ]);
        
        $this->service->recordSale($document);
    }

    /** @test */
    public function it_can_generate_stock_alert_report()
    {
        // Create products with various stock levels
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Low Stock Product',
            'current_stock' => 5,
            'minimum_stock' => 20,
            'manages_inventory' => true
        ]);
        
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Out of Stock Product',
            'current_stock' => 0,
            'minimum_stock' => 10,
            'manages_inventory' => true
        ]);
        
        $alerts = $this->service->getStockAlerts();
        
        $this->assertArrayHasKey('critical', $alerts);
        $this->assertArrayHasKey('low', $alerts);
        $this->assertArrayHasKey('reorder', $alerts);
        
        $this->assertCount(1, $alerts['critical']); // Out of stock
        $this->assertCount(1, $alerts['low']); // Below minimum
    }

    /** @test */
    public function it_can_perform_bulk_adjustment()
    {
        // Create multiple products
        $product2 = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_stock' => 50,
            'manages_inventory' => true
        ]);
        
        $adjustments = [
            ['product_id' => $this->product->id, 'quantity' => -10],
            ['product_id' => $product2->id, 'quantity' => 20]
        ];
        
        // Perform bulk adjustment
        $results = $this->service->bulkAdjustment($adjustments, 'Physical count adjustment');
        
        $this->assertCount(2, $results);
        
        // Check stocks were updated
        $this->product->refresh();
        $product2->refresh();
        $this->assertEquals(90, $this->product->current_stock);
        $this->assertEquals(70, $product2->current_stock);
    }

    /** @test */
    public function it_rolls_back_on_bulk_adjustment_failure()
    {
        // Create product with low stock
        $product2 = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_stock' => 5,
            'manages_inventory' => true
        ]);
        
        $adjustments = [
            ['product_id' => $this->product->id, 'quantity' => -10],
            ['product_id' => $product2->id, 'quantity' => -10] // This will fail
        ];
        
        try {
            $this->service->bulkAdjustment($adjustments, 'Test adjustment');
        } catch (\Exception $e) {
            // Expected exception
        }
        
        // Check no changes were made
        $this->product->refresh();
        $product2->refresh();
        $this->assertEquals(100, $this->product->current_stock); // Unchanged
        $this->assertEquals(5, $product2->current_stock); // Unchanged
    }

    /** @test */
    public function it_respects_tenant_isolation()
    {
        // Create another tenant with products
        $otherTenant = Tenant::factory()->create();
        $otherProduct = Product::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_stock' => 200,
            'manages_inventory' => true
        ]);
        
        // Try to check availability for other tenant's product
        $this->assertFalse($this->service->checkAvailability($otherProduct->id, 10));
        
        // Get stock levels should only show current tenant's products
        $levels = $this->service->getStockLevels();
        $this->assertEquals(1, $levels['total_products']); // Only current tenant's product
    }

    /** @test */
    public function it_can_track_inventory_turnover()
    {
        // Create movements over time
        $startDate = now()->subDays(30);
        
        // Initial stock movement
        InventoryMovement::factory()->create([
            'product_id' => $this->product->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'adjustment',
            'quantity' => 100,
            'stock_after' => 100,
            'created_at' => $startDate
        ]);
        
        // Sales movements
        for ($i = 0; $i < 10; $i++) {
            InventoryMovement::factory()->create([
                'product_id' => $this->product->id,
                'tenant_id' => $this->tenant->id,
                'type' => 'sale',
                'quantity' => -10,
                'created_at' => $startDate->copy()->addDays($i * 3)
            ]);
        }
        
        $turnover = $this->service->calculateTurnoverRate($this->product->id, 30);
        
        $this->assertArrayHasKey('turnover_rate', $turnover);
        $this->assertArrayHasKey('days_inventory', $turnover);
        $this->assertArrayHasKey('average_stock', $turnover);
        $this->assertArrayHasKey('total_sold', $turnover);
        
        $this->assertEquals(100, $turnover['total_sold']);
    }
}