<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // POS Terminals
        Schema::create('pos_terminals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('identifier')->unique(); // Hardware ID or code
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->json('settings')->nullable(); // Terminal specific settings
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['tenant_id', 'is_active']);
            $table->index(['identifier']);
        });

        // Cash Registers/Sessions
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('terminal_id');
            $table->uuid('user_id'); // Cashier
            $table->string('session_number')->unique();
            $table->decimal('opening_balance', 12, 2)->default(0);
            $table->decimal('closing_balance', 12, 2)->nullable();
            $table->decimal('expected_balance', 12, 2)->nullable();
            $table->decimal('difference', 12, 2)->nullable();
            $table->json('opening_cash_count')->nullable(); // Breakdown by denomination
            $table->json('closing_cash_count')->nullable();
            $table->enum('status', ['open', 'closed', 'suspended'])->default('open');
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->text('opening_notes')->nullable();
            $table->text('closing_notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('terminal_id')->references('id')->on('pos_terminals')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['tenant_id', 'status']);
            $table->index(['terminal_id', 'status']);
            $table->index(['user_id']);
        });

        // POS Transactions
        Schema::create('pos_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('terminal_id');
            $table->uuid('session_id');
            $table->uuid('user_id'); // Cashier
            $table->uuid('customer_id')->nullable();
            $table->string('transaction_number')->unique();
            $table->enum('type', ['sale', 'return', 'exchange', 'void'])->default('sale');
            $table->enum('status', ['pending', 'completed', 'cancelled', 'refunded'])->default('pending');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_amount', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('change_given', 12, 2)->default(0);
            $table->json('payment_methods')->nullable(); // Cash, card, etc.
            $table->json('customer_data')->nullable(); // Customer info snapshot
            $table->text('notes')->nullable();
            $table->uuid('related_transaction_id')->nullable(); // For returns/exchanges
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('terminal_id')->references('id')->on('pos_terminals')->onDelete('cascade');
            $table->foreign('session_id')->references('id')->on('cash_sessions')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
            $table->foreign('related_transaction_id')->references('id')->on('pos_transactions')->onDelete('set null');
            $table->index(['tenant_id', 'status']);
            $table->index(['session_id']);
            $table->index(['transaction_number']);
            $table->index(['type', 'status']);
        });

        // Transaction Items
        Schema::create('pos_transaction_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('transaction_id');
            $table->uuid('product_id');
            $table->string('product_name'); // Snapshot
            $table->string('product_sku')->nullable();
            $table->decimal('quantity', 8, 2); // Support fractional quantities
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->json('modifiers')->nullable(); // Product variations, extras
            $table->timestamps();

            $table->foreign('transaction_id')->references('id')->on('pos_transactions')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->index(['transaction_id']);
        });

        // Payment Transactions
        Schema::create('pos_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('transaction_id');
            $table->enum('method', ['cash', 'card_credit', 'card_debit', 'transfer', 'check', 'gift_card', 'store_credit']);
            $table->decimal('amount', 12, 2);
            $table->string('reference')->nullable(); // Card transaction ID, check number, etc.
            $table->json('details')->nullable(); // Card type, last 4 digits, etc.
            $table->enum('status', ['pending', 'approved', 'declined', 'cancelled'])->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('transaction_id')->references('id')->on('pos_transactions')->onDelete('cascade');
            $table->index(['transaction_id']);
            $table->index(['method', 'status']);
        });

        // Cash Movements (for tracking cash in/out during session)
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('session_id');
            $table->uuid('user_id');
            $table->enum('type', ['in', 'out']);
            $table->enum('reason', ['sale', 'return', 'cash_drop', 'cash_pickup', 'expense', 'correction', 'tip']);
            $table->decimal('amount', 12, 2);
            $table->text('description');
            $table->string('reference')->nullable(); // Transaction number, receipt, etc.
            $table->timestamps();

            $table->foreign('session_id')->references('id')->on('cash_sessions')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['session_id', 'type']);
        });

        // Product Quick Access (favorites/shortcuts)
        Schema::create('pos_product_shortcuts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('terminal_id')->nullable(); // null = global shortcut
            $table->uuid('product_id');
            $table->integer('position')->default(0);
            $table->string('button_color')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('terminal_id')->references('id')->on('pos_terminals')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->index(['tenant_id', 'terminal_id']);
            $table->index(['position']);
        });

        // Discounts/Promotions
        Schema::create('pos_discounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['percentage', 'fixed_amount', 'buy_x_get_y']);
            $table->decimal('value', 12, 2);
            $table->decimal('minimum_amount', 12, 2)->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->json('applicable_products')->nullable(); // Product IDs or categories
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['tenant_id', 'is_active']);
        });

        // Receipt Templates
        Schema::create('receipt_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->text('header_text')->nullable();
            $table->text('footer_text')->nullable();
            $table->json('layout_settings')->nullable(); // Font size, margins, etc.
            $table->boolean('show_logo')->default(true);
            $table->boolean('show_barcode')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['tenant_id', 'is_default']);
        });

        // Inventory Adjustments from POS
        Schema::create('pos_inventory_adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('terminal_id');
            $table->uuid('user_id');
            $table->uuid('product_id');
            $table->decimal('quantity_before', 8, 2);
            $table->decimal('quantity_after', 8, 2);
            $table->decimal('adjustment', 8, 2);
            $table->enum('reason', ['sale', 'return', 'damage', 'theft', 'correction', 'recount']);
            $table->string('reference')->nullable(); // Transaction number, etc.
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('terminal_id')->references('id')->on('pos_terminals')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->index(['tenant_id', 'product_id']);
            $table->index(['reason']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_inventory_adjustments');
        Schema::dropIfExists('receipt_templates');
        Schema::dropIfExists('pos_discounts');
        Schema::dropIfExists('pos_product_shortcuts');
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('pos_payments');
        Schema::dropIfExists('pos_transaction_items');
        Schema::dropIfExists('pos_transactions');
        Schema::dropIfExists('cash_sessions');
        Schema::dropIfExists('pos_terminals');
    }
};