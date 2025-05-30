<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Configuración de la tienda
        Schema::create('ecommerce_stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain')->nullable();
            $table->string('logo')->nullable();
            $table->string('favicon')->nullable();
            $table->json('contact_info')->nullable(); // email, phone, address
            $table->json('social_links')->nullable();
            $table->text('description')->nullable();
            $table->json('meta_tags')->nullable();
            $table->string('currency', 3)->default('CLP');
            $table->string('language', 2)->default('es');
            $table->enum('type', ['b2c', 'b2b', 'both'])->default('b2c');
            $table->boolean('is_active')->default(true);
            $table->boolean('maintenance_mode')->default(false);
            $table->json('settings')->nullable(); // Configuraciones adicionales
            $table->timestamps();
            
            $table->index(['tenant_id', 'is_active']);
        });

        // Categorías de productos
        Schema::create('ecommerce_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('ecommerce_stores');
            $table->foreignId('parent_id')->nullable()->constrained('ecommerce_categories');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->integer('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('meta_tags')->nullable();
            $table->timestamps();
            
            $table->unique(['store_id', 'slug']);
            $table->index(['store_id', 'parent_id', 'is_active']);
        });

        // Productos del catálogo
        Schema::create('ecommerce_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('ecommerce_stores');
            $table->foreignId('product_id')->constrained('products'); // Producto del inventario
            $table->string('name'); // Puede diferir del nombre interno
            $table->string('slug');
            $table->text('short_description')->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->decimal('compare_price', 12, 2)->nullable(); // Precio antes del descuento
            $table->decimal('cost', 12, 2)->nullable();
            $table->boolean('track_inventory')->default(true);
            $table->integer('stock_quantity')->default(0);
            $table->boolean('allow_backorder')->default(false);
            $table->decimal('weight', 8, 3)->nullable(); // En kg
            $table->json('dimensions')->nullable(); // largo, ancho, alto
            $table->json('attributes')->nullable(); // Atributos adicionales
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('views')->default(0);
            $table->decimal('rating', 3, 2)->default(0);
            $table->integer('rating_count')->default(0);
            $table->json('meta_tags')->nullable();
            $table->timestamps();
            
            $table->unique(['store_id', 'slug']);
            $table->index(['store_id', 'is_active', 'is_featured']);
        });

        // Relación productos-categorías
        Schema::create('ecommerce_product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('ecommerce_products')->onDelete('cascade');
            $table->foreignId('category_id')->constrained('ecommerce_categories')->onDelete('cascade');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            
            $table->unique(['product_id', 'category_id']);
        });

        // Variantes de productos
        Schema::create('ecommerce_product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('ecommerce_products')->onDelete('cascade');
            $table->string('sku')->unique();
            $table->string('name');
            $table->json('options'); // {color: 'Rojo', size: 'L'}
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('compare_price', 12, 2)->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['product_id', 'is_active']);
        });

        // Imágenes de productos
        Schema::create('ecommerce_product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('ecommerce_products')->onDelete('cascade');
            $table->string('url');
            $table->string('alt_text')->nullable();
            $table->integer('position')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            
            $table->index(['product_id', 'position']);
        });

        // Clientes de e-commerce
        Schema::create('ecommerce_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('ecommerce_stores');
            $table->foreignId('user_id')->nullable()->constrained();
            $table->foreignId('customer_id')->nullable()->constrained(); // Cliente del sistema
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone')->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('type', ['b2c', 'b2b'])->default('b2c');
            $table->string('company_name')->nullable();
            $table->string('tax_id')->nullable();
            $table->boolean('accepts_marketing')->default(false);
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->integer('orders_count')->default(0);
            $table->timestamp('last_order_at')->nullable();
            $table->json('tags')->nullable();
            $table->string('remember_token')->nullable();
            $table->timestamps();
            
            $table->index(['store_id', 'email']);
            $table->index(['store_id', 'type']);
        });

        // Direcciones de clientes
        Schema::create('ecommerce_customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('ecommerce_customers')->onDelete('cascade');
            $table->string('type')->default('shipping'); // shipping, billing
            $table->string('first_name');
            $table->string('last_name');
            $table->string('company')->nullable();
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city');
            $table->string('state');
            $table->string('postal_code');
            $table->string('country', 2)->default('CL');
            $table->string('phone')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            
            $table->index(['customer_id', 'type']);
        });

        // Carritos de compra
        Schema::create('ecommerce_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('ecommerce_stores');
            $table->foreignId('customer_id')->nullable()->constrained('ecommerce_customers');
            $table->string('session_id')->nullable();
            $table->string('status')->default('active'); // active, abandoned, converted
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('shipping_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('currency', 3)->default('CLP');
            $table->json('metadata')->nullable();
            $table->timestamp('abandoned_at')->nullable();
            $table->timestamps();
            
            $table->index(['store_id', 'status']);
            $table->index(['customer_id']);
            $table->index(['session_id']);
        });

        // Items del carrito
        Schema::create('ecommerce_cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('ecommerce_carts')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('ecommerce_products');
            $table->foreignId('variant_id')->nullable()->constrained('ecommerce_product_variants');
            $table->integer('quantity');
            $table->decimal('price', 12, 2);
            $table->decimal('total', 12, 2);
            $table->json('custom_options')->nullable();
            $table->timestamps();
            
            $table->index(['cart_id']);
        });

        // Órdenes
        Schema::create('ecommerce_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('ecommerce_stores');
            $table->foreignId('customer_id')->nullable()->constrained('ecommerce_customers');
            $table->string('order_number')->unique();
            $table->enum('status', ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'])->default('pending');
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->enum('fulfillment_status', ['unfulfilled', 'partially_fulfilled', 'fulfilled'])->default('unfulfilled');
            
            // Totales
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('shipping_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('currency', 3)->default('CLP');
            
            // Información del cliente
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();
            $table->json('billing_address');
            $table->json('shipping_address');
            
            // Información de pago
            $table->string('payment_method')->nullable();
            $table->json('payment_details')->nullable();
            $table->timestamp('paid_at')->nullable();
            
            // Información de envío
            $table->string('shipping_method')->nullable();
            $table->string('tracking_number')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            
            // Notas y metadata
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->string('source')->default('web'); // web, api, pos, manual
            
            $table->timestamps();
            
            $table->index(['store_id', 'status']);
            $table->index(['store_id', 'customer_id']);
            $table->index(['order_number']);
        });

        // Items de la orden
        Schema::create('ecommerce_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('ecommerce_orders')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('ecommerce_products');
            $table->foreignId('variant_id')->nullable()->constrained('ecommerce_product_variants');
            $table->string('name');
            $table->string('sku')->nullable();
            $table->integer('quantity');
            $table->decimal('price', 12, 2);
            $table->decimal('total', 12, 2);
            $table->json('product_options')->nullable();
            $table->enum('fulfillment_status', ['pending', 'fulfilled', 'cancelled'])->default('pending');
            $table->timestamps();
            
            $table->index(['order_id']);
        });

        // Cupones de descuento
        Schema::create('ecommerce_coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('ecommerce_stores');
            $table->string('code')->unique();
            $table->string('description')->nullable();
            $table->enum('type', ['percentage', 'fixed', 'free_shipping']);
            $table->decimal('value', 12, 2);
            $table->decimal('minimum_amount', 12, 2)->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_count')->default(0);
            $table->integer('usage_limit_per_customer')->nullable();
            $table->json('applicable_products')->nullable(); // IDs de productos
            $table->json('applicable_categories')->nullable(); // IDs de categorías
            $table->json('customer_eligibility')->nullable(); // all, specific, group
            $table->datetime('valid_from')->nullable();
            $table->datetime('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['store_id', 'code']);
            $table->index(['store_id', 'is_active']);
        });

        // Métodos de envío
        Schema::create('ecommerce_shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('ecommerce_stores');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['flat_rate', 'weight_based', 'price_based', 'free']);
            $table->decimal('rate', 12, 2)->nullable();
            $table->json('rates_table')->nullable(); // Para tarifas basadas en peso/precio
            $table->decimal('minimum_order', 12, 2)->nullable();
            $table->json('zones')->nullable(); // Zonas de envío
            $table->integer('estimated_days_min')->nullable();
            $table->integer('estimated_days_max')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['store_id', 'is_active']);
        });

        // Métodos de pago
        Schema::create('ecommerce_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('ecommerce_stores');
            $table->string('name');
            $table->string('code'); // webpay, mercadopago, transfer, etc.
            $table->text('description')->nullable();
            $table->json('settings')->nullable(); // API keys, etc (encriptado)
            $table->decimal('transaction_fee', 5, 2)->default(0); // Porcentaje
            $table->decimal('fixed_fee', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('position')->default(0);
            $table->timestamps();
            
            $table->index(['store_id', 'is_active']);
        });

        // Reviews de productos
        Schema::create('ecommerce_product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('ecommerce_products');
            $table->foreignId('customer_id')->constrained('ecommerce_customers');
            $table->foreignId('order_id')->nullable()->constrained('ecommerce_orders');
            $table->integer('rating'); // 1-5
            $table->string('title')->nullable();
            $table->text('comment')->nullable();
            $table->json('pros')->nullable();
            $table->json('cons')->nullable();
            $table->boolean('is_verified')->default(false); // Compra verificada
            $table->boolean('is_approved')->default(false);
            $table->integer('helpful_count')->default(0);
            $table->timestamps();
            
            $table->index(['product_id', 'is_approved']);
            $table->index(['customer_id']);
        });

        // Lista de deseos
        Schema::create('ecommerce_wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('ecommerce_customers');
            $table->foreignId('product_id')->constrained('ecommerce_products');
            $table->timestamps();
            
            $table->unique(['customer_id', 'product_id']);
        });

        // Suscripciones al newsletter
        Schema::create('ecommerce_newsletter_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('ecommerce_stores');
            $table->string('email')->unique();
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('confirmed_at')->nullable();
            $table->string('confirmation_token')->nullable();
            $table->timestamps();
            
            $table->index(['store_id', 'is_active']);
        });

        // Banners y sliders
        Schema::create('ecommerce_banners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('ecommerce_stores');
            $table->string('name');
            $table->string('image');
            $table->string('link')->nullable();
            $table->string('position'); // home_slider, sidebar, etc.
            $table->integer('order')->default(0);
            $table->datetime('start_date')->nullable();
            $table->datetime('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['store_id', 'position', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_banners');
        Schema::dropIfExists('ecommerce_newsletter_subscriptions');
        Schema::dropIfExists('ecommerce_wishlists');
        Schema::dropIfExists('ecommerce_product_reviews');
        Schema::dropIfExists('ecommerce_payment_methods');
        Schema::dropIfExists('ecommerce_shipping_methods');
        Schema::dropIfExists('ecommerce_coupons');
        Schema::dropIfExists('ecommerce_order_items');
        Schema::dropIfExists('ecommerce_orders');
        Schema::dropIfExists('ecommerce_cart_items');
        Schema::dropIfExists('ecommerce_carts');
        Schema::dropIfExists('ecommerce_customer_addresses');
        Schema::dropIfExists('ecommerce_customers');
        Schema::dropIfExists('ecommerce_product_images');
        Schema::dropIfExists('ecommerce_product_variants');
        Schema::dropIfExists('ecommerce_product_categories');
        Schema::dropIfExists('ecommerce_products');
        Schema::dropIfExists('ecommerce_categories');
        Schema::dropIfExists('ecommerce_stores');
    }
};