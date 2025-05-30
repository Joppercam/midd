<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Terminales/Sucursales POS
        Schema::create('pos_terminals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->json('settings')->nullable(); // Configuraciones específicas del terminal
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['tenant_id', 'is_active']);
        });

        // Cajas registradoras
        Schema::create('pos_cash_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terminal_id')->constrained('pos_terminals');
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['terminal_id', 'is_active']);
        });

        // Sesiones de caja
        Schema::create('pos_cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_register_id')->constrained('pos_cash_registers');
            $table->foreignId('opened_by')->constrained('users');
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->decimal('opening_balance', 12, 2);
            $table->decimal('closing_balance', 12, 2)->nullable();
            $table->decimal('expected_balance', 12, 2)->nullable();
            $table->decimal('difference', 12, 2)->nullable();
            $table->integer('transaction_count')->default(0);
            $table->json('denominations_open')->nullable(); // Desglose de billetes/monedas al abrir
            $table->json('denominations_close')->nullable(); // Desglose al cerrar
            $table->text('opening_notes')->nullable();
            $table->text('closing_notes')->nullable();
            $table->enum('status', ['open', 'closed', 'suspended'])->default('open');
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            
            $table->index(['cash_register_id', 'status']);
            $table->index(['opened_by']);
        });

        // Transacciones/Ventas POS
        Schema::create('pos_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('terminal_id')->constrained('pos_terminals');
            $table->foreignId('cash_session_id')->constrained('pos_cash_sessions');
            $table->foreignId('user_id')->constrained(); // Cajero
            $table->foreignId('customer_id')->nullable()->constrained();
            $table->string('transaction_number')->unique();
            $table->enum('type', ['sale', 'refund', 'exchange']);
            $table->enum('status', ['completed', 'voided', 'partial_refund']);
            
            // Totales
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->decimal('amount_paid', 12, 2);
            $table->decimal('change_amount', 12, 2)->default(0);
            
            // Referencias
            $table->foreignId('invoice_id')->nullable()->constrained('tax_documents');
            $table->foreignId('original_transaction_id')->nullable()->constrained('pos_transactions'); // Para devoluciones
            
            // Información adicional
            $table->json('discount_details')->nullable(); // Detalles de descuentos aplicados
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_synced')->default(true); // Para ventas offline
            $table->timestamp('synced_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['tenant_id', 'terminal_id', 'created_at']);
            $table->index(['tenant_id', 'transaction_number']);
            $table->index(['cash_session_id']);
            $table->index(['customer_id']);
            $table->index(['is_synced', 'synced_at']);
        });

        // Items de la transacción
        Schema::create('pos_transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('pos_transactions')->onDelete('cascade');
            $table->foreignId('product_id')->constrained();
            $table->string('product_name'); // Guardar el nombre en el momento de la venta
            $table->string('product_sku');
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->json('modifiers')->nullable(); // Modificadores o variantes
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['transaction_id']);
            $table->index(['product_id']);
        });

        // Pagos de la transacción
        Schema::create('pos_transaction_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('pos_transactions')->onDelete('cascade');
            $table->foreignId('payment_method_id')->constrained('pos_payment_methods');
            $table->decimal('amount', 12, 2);
            $table->string('reference')->nullable(); // Número de tarjeta, cheque, etc.
            $table->json('details')->nullable(); // Detalles adicionales del pago
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('completed');
            $table->timestamps();
            
            $table->index(['transaction_id']);
            $table->index(['payment_method_id']);
        });

        // Métodos de pago POS
        Schema::create('pos_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('name');
            $table->string('code')->unique(); // cash, card, transfer, etc.
            $table->string('icon')->nullable();
            $table->boolean('opens_cash_drawer')->default(false);
            $table->boolean('requires_reference')->default(false);
            $table->boolean('allows_change')->default(false); // Solo efectivo permite vuelto
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('position')->default(0);
            $table->timestamps();
            
            $table->index(['tenant_id', 'is_active']);
        });

        // Movimientos de caja (entradas/salidas de dinero)
        Schema::create('pos_cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_session_id')->constrained('pos_cash_sessions');
            $table->foreignId('user_id')->constrained();
            $table->enum('type', ['in', 'out']);
            $table->decimal('amount', 12, 2);
            $table->string('reason');
            $table->text('description')->nullable();
            $table->string('reference')->nullable();
            $table->timestamps();
            
            $table->index(['cash_session_id', 'type']);
        });

        // Descuentos disponibles
        Schema::create('pos_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('name');
            $table->string('code')->unique();
            $table->enum('type', ['percentage', 'fixed']);
            $table->decimal('value', 8, 2);
            $table->decimal('minimum_amount', 12, 2)->nullable();
            $table->decimal('maximum_discount', 12, 2)->nullable();
            $table->json('applicable_products')->nullable(); // null = todos
            $table->json('applicable_categories')->nullable();
            $table->boolean('requires_authorization')->default(false);
            $table->boolean('is_active')->default(true);
            $table->datetime('valid_from')->nullable();
            $table->datetime('valid_until')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'is_active']);
            $table->index(['code']);
        });

        // Clientes frecuentes/Programa de lealtad
        Schema::create('pos_loyalty_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->string('card_number')->unique();
            $table->integer('points')->default(0);
            $table->decimal('lifetime_value', 12, 2)->default(0);
            $table->integer('visit_count')->default(0);
            $table->enum('tier', ['bronze', 'silver', 'gold', 'platinum'])->default('bronze');
            $table->date('last_visit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['tenant_id', 'card_number']);
            $table->index(['customer_id']);
        });

        // Movimientos de puntos
        Schema::create('pos_loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loyalty_card_id')->constrained('pos_loyalty_cards');
            $table->foreignId('transaction_id')->nullable()->constrained('pos_transactions');
            $table->enum('type', ['earned', 'redeemed', 'adjusted', 'expired']);
            $table->integer('points');
            $table->integer('balance_after');
            $table->string('description');
            $table->timestamps();
            
            $table->index(['loyalty_card_id', 'type']);
        });

        // Configuración de impresoras
        Schema::create('pos_printers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terminal_id')->constrained('pos_terminals');
            $table->string('name');
            $table->enum('type', ['receipt', 'kitchen', 'label']);
            $table->string('connection_type'); // network, usb, bluetooth
            $table->json('connection_settings'); // IP, puerto, etc.
            $table->integer('paper_width')->default(80); // mm
            $table->boolean('auto_cut')->default(true);
            $table->boolean('open_drawer')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['terminal_id', 'type', 'is_active']);
        });

        // Productos favoritos/Acceso rápido
        Schema::create('pos_quick_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terminal_id')->constrained('pos_terminals');
            $table->foreignId('product_id')->constrained();
            $table->foreignId('category_id')->nullable()->constrained('categories');
            $table->string('button_color')->default('#3B82F6');
            $table->string('text_color')->default('#FFFFFF');
            $table->integer('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['terminal_id', 'product_id']);
            $table->index(['terminal_id', 'category_id', 'is_active']);
        });

        // Turnos de trabajo
        Schema::create('pos_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terminal_id')->constrained('pos_terminals');
            $table->foreignId('user_id')->constrained();
            $table->datetime('start_time');
            $table->datetime('end_time')->nullable();
            $table->datetime('scheduled_end')->nullable();
            $table->integer('break_minutes')->default(0);
            $table->decimal('sales_total', 12, 2)->default(0);
            $table->integer('transaction_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['terminal_id', 'user_id']);
            $table->index(['start_time', 'end_time']);
        });

        // Configuración offline
        Schema::create('pos_offline_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terminal_id')->constrained('pos_terminals');
            $table->string('entity_type'); // transaction, cash_movement, etc.
            $table->json('data');
            $table->integer('attempts')->default(0);
            $table->timestamp('last_attempt')->nullable();
            $table->text('error_message')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->timestamps();
            
            $table->index(['terminal_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_offline_queue');
        Schema::dropIfExists('pos_shifts');
        Schema::dropIfExists('pos_quick_products');
        Schema::dropIfExists('pos_printers');
        Schema::dropIfExists('pos_loyalty_transactions');
        Schema::dropIfExists('pos_loyalty_cards');
        Schema::dropIfExists('pos_discounts');
        Schema::dropIfExists('pos_cash_movements');
        Schema::dropIfExists('pos_payment_methods');
        Schema::dropIfExists('pos_transaction_payments');
        Schema::dropIfExists('pos_transaction_items');
        Schema::dropIfExists('pos_transactions');
        Schema::dropIfExists('pos_cash_sessions');
        Schema::dropIfExists('pos_cash_registers');
        Schema::dropIfExists('pos_terminals');
    }
};