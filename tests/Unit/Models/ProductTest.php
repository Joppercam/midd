<?php

namespace Tests\Unit\Models;

use App\Models\Product;
use App\Models\Category;
use App\Models\InventoryMovement;
use Tests\TestCase;

class ProductTest extends TestCase
{
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Category',
            'type' => 'product',
        ]);
    }

    /** @test */
    public function it_can_create_a_product()
    {
        $product = Product::create([
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

        $this->assertInstanceOf(Product::class, $product);
        $this->assertEquals('Test Product', $product->name);
        $this->assertEquals('TEST-001', $product->sku);
        $this->assertEquals(1000, $product->purchase_price);
        $this->assertEquals(1500, $product->sale_price);
        $this->assertEquals(100, $product->stock_quantity);
    }

    /** @test */
    public function it_belongs_to_tenant()
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $this->category->id,
        ]);

        $this->assertEquals($this->tenant->id, $product->tenant_id);
        $this->assertInstanceOf(\App\Models\Tenant::class, $product->tenant);
    }

    /** @test */
    public function it_belongs_to_category()
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $this->category->id,
        ]);

        $this->assertEquals($this->category->id, $product->category_id);
        $this->assertInstanceOf(Category::class, $product->category);
    }

    /** @test */
    public function it_has_many_inventory_movements()
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $this->category->id,
        ]);

        $movement1 = InventoryMovement::create([
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 50,
            'unit_cost' => 1000,
            'total_cost' => 50000,
            'reference_type' => 'manual',
            'notes' => 'Initial stock',
        ]);

        $movement2 = InventoryMovement::create([
            'product_id' => $product->id,
            'type' => 'sale',
            'quantity' => -10,
            'unit_cost' => 1000,
            'total_cost' => -10000,
            'reference_type' => 'manual',
            'notes' => 'Sale',
        ]);

        $this->assertCount(2, $product->inventoryMovements);
        $this->assertTrue($product->inventoryMovements->contains($movement1));
        $this->assertTrue($product->inventoryMovements->contains($movement2));
    }

    /** @test */
    public function it_calculates_profit_margin()
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $this->category->id,
            'name' => 'Test Product',
            'purchase_price' => 1000,
            'sale_price' => 1500,
            'stock_quantity' => 100,
            'type' => 'product',
        ]);

        $this->assertEquals(50.0, $product->profit_margin);
    }

    /** @test */
    public function it_calculates_profit_margin_when_purchase_price_is_zero()
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $this->category->id,
            'name' => 'Test Product',
            'purchase_price' => 0,
            'sale_price' => 1500,
            'stock_quantity' => 100,
            'type' => 'product',
        ]);

        $this->assertEquals(0, $product->profit_margin);
    }

    /** @test */
    public function it_determines_if_stock_is_low()
    {
        $lowStockProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $this->category->id,
            'name' => 'Low Stock Product',
            'stock_quantity' => 5,
            'min_stock_level' => 10,
            'type' => 'product',
        ]);

        $normalStockProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $this->category->id,
            'name' => 'Normal Stock Product',
            'stock_quantity' => 50,
            'min_stock_level' => 10,
            'type' => 'product',
        ]);

        $this->assertTrue($lowStockProduct->is_low_stock);
        $this->assertFalse($normalStockProduct->is_low_stock);
    }

    /** @test */
    public function it_determines_if_product_is_out_of_stock()
    {
        $outOfStockProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $this->category->id,
            'name' => 'Out of Stock Product',
            'stock_quantity' => 0,
            'type' => 'product',
        ]);

        $inStockProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $this->category->id,
            'name' => 'In Stock Product',
            'stock_quantity' => 10,
            'type' => 'product',
        ]);

        $this->assertTrue($outOfStockProduct->is_out_of_stock);
        $this->assertFalse($inStockProduct->is_out_of_stock);
    }

    /** @test */
    public function it_determines_if_product_is_service()
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $this->category->id,
            'name' => 'Physical Product',
            'type' => 'product',
            'stock_quantity' => 10,
        ]);

        $service = Product::create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $this->category->id,
            'name' => 'Service Product',
            'type' => 'service',
            'stock_quantity' => 0,
        ]);

        $this->assertFalse($product->is_service);
        $this->assertTrue($service->is_service);
    }

    /** @test */
    public function it_gets_type_label_correctly()
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $this->category->id,
            'name' => 'Test Product',
            'type' => 'product',
        ]);

        $service = Product::create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $this->category->id,
            'name' => 'Test Service',
            'type' => 'service',
        ]);

        $this->assertEquals('Producto', $product->type_label);
        $this->assertEquals('Servicio', $service->type_label);
    }

    /** @test */
    public function it_formats_prices_correctly()
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $this->category->id,
            'name' => 'Test Product',
            'purchase_price' => 1000,
            'sale_price' => 1500,
        ]);

        $this->assertEquals('1.000', $product->formatted_purchase_price);
        $this->assertEquals('1.500', $product->formatted_sale_price);
    }

    /** @test */
    public function it_scopes_active_products()
    {
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $this->category->id,
            'is_active' => false,
        ]);

        $this->assertCount(1, Product::active()->get());
    }

    /** @test */
    public function it_scopes_products_with_low_stock()
    {
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $this->category->id,
            'stock_quantity' => 5,
            'min_stock_level' => 10,
            'type' => 'product',
        ]);

        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $this->category->id,
            'stock_quantity' => 50,
            'min_stock_level' => 10,
            'type' => 'product',
        ]);

        $this->assertCount(1, Product::lowStock()->get());
    }

    /** @test */
    public function it_scopes_out_of_stock_products()
    {
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $this->category->id,
            'stock_quantity' => 0,
            'type' => 'product',
        ]);

        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $this->category->id,
            'stock_quantity' => 10,
            'type' => 'product',
        ]);

        $this->assertCount(1, Product::outOfStock()->get());
    }

    /** @test */
    public function it_searches_products_by_name()
    {
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $this->category->id,
            'name' => 'Laptop Computer',
        ]);

        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $this->category->id,
            'name' => 'Desktop Computer',
        ]);

        $results = Product::search('Laptop')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Laptop Computer', $results->first()->name);
    }

    /** @test */
    public function it_searches_products_by_sku()
    {
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $this->category->id,
            'name' => 'Test Product',
            'sku' => 'LAPTOP-001',
        ]);

        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $this->category->id,
            'name' => 'Another Product',
            'sku' => 'DESKTOP-001',
        ]);

        $results = Product::search('LAPTOP')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('LAPTOP-001', $results->first()->sku);
    }
}