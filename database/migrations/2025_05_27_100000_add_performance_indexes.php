<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Índices para tax_documents (facturas)
        Schema::table('tax_documents', function (Blueprint $table) {
            $table->index(['tenant_id', 'status'], 'idx_tax_documents_tenant_status');
            $table->index(['tenant_id', 'date'], 'idx_tax_documents_tenant_date');
            $table->index(['tenant_id', 'type', 'date'], 'idx_tax_documents_tenant_type_date');
            $table->index(['customer_id', 'status'], 'idx_tax_documents_customer_status');
            $table->index(['tenant_id', 'payment_status'], 'idx_tax_documents_tenant_payment_status');
            $table->index(['tenant_id', 'due_date'], 'idx_tax_documents_tenant_due_date');
        });

        // Índices para payments (pagos)
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['tenant_id', 'payment_date'], 'idx_payments_tenant_date');
            $table->index(['customer_id', 'status'], 'idx_payments_customer_status');
            $table->index(['tenant_id', 'payment_method'], 'idx_payments_tenant_method');
            $table->index(['tenant_id', 'status'], 'idx_payments_tenant_status');
        });

        // Índices para expenses (gastos)
        Schema::table('expenses', function (Blueprint $table) {
            $table->index(['tenant_id', 'date'], 'idx_expenses_tenant_date');
            $table->index(['supplier_id', 'status'], 'idx_expenses_supplier_status');
            $table->index(['tenant_id', 'category'], 'idx_expenses_tenant_category');
            $table->index(['tenant_id', 'status'], 'idx_expenses_tenant_status');
            $table->index(['tenant_id', 'payment_status'], 'idx_expenses_tenant_payment_status');
        });

        // Índices para products (productos)
        Schema::table('products', function (Blueprint $table) {
            $table->index(['tenant_id', 'manages_inventory'], 'idx_products_tenant_inventory');
            $table->index(['tenant_id', 'is_active'], 'idx_products_tenant_active');
            $table->index(['category_id'], 'idx_products_category');
            $table->index(['sku'], 'idx_products_sku');
            $table->index(['tenant_id', 'current_stock'], 'idx_products_tenant_stock');
        });

        // Índices para customers (clientes)
        Schema::table('customers', function (Blueprint $table) {
            $table->index(['tenant_id', 'is_active'], 'idx_customers_tenant_active');
            $table->index(['rut'], 'idx_customers_rut');
            $table->index(['email'], 'idx_customers_email');
            $table->index(['tenant_id', 'created_at'], 'idx_customers_tenant_created');
        });

        // Índices para suppliers (proveedores)
        Schema::table('suppliers', function (Blueprint $table) {
            $table->index(['tenant_id', 'is_active'], 'idx_suppliers_tenant_active');
            $table->index(['rut'], 'idx_suppliers_rut');
            $table->index(['email'], 'idx_suppliers_email');
        });

        // Índices para inventory_movements (movimientos de inventario)
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at'], 'idx_inventory_movements_tenant_date');
            $table->index(['product_id', 'type'], 'idx_inventory_movements_product_type');
            $table->index(['tenant_id', 'type'], 'idx_inventory_movements_tenant_type');
            $table->index(['reference_type', 'reference_id'], 'idx_inventory_movements_reference');
        });

        // Índices para bank_transactions (transacciones bancarias)
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->index(['bank_account_id', 'date'], 'idx_bank_transactions_account_date');
            $table->index(['bank_account_id', 'status'], 'idx_bank_transactions_account_status');
            $table->index(['date', 'type'], 'idx_bank_transactions_date_type');
            $table->index(['reconciliation_id'], 'idx_bank_transactions_reconciliation');
        });

        // Índices para audit_logs (logs de auditoría)
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at'], 'idx_audit_logs_tenant_date');
            $table->index(['user_id', 'created_at'], 'idx_audit_logs_user_date');
            $table->index(['auditable_type', 'auditable_id'], 'idx_audit_logs_auditable');
            $table->index(['event'], 'idx_audit_logs_event');
            $table->index(['tenant_id', 'event'], 'idx_audit_logs_tenant_event');
        });

        // Índices para activities (actividades)
        Schema::table('activities', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at'], 'idx_activities_tenant_date');
            $table->index(['subject_type', 'subject_id'], 'idx_activities_subject');
            $table->index(['causer_type', 'causer_id'], 'idx_activities_causer');
            $table->index(['log_name'], 'idx_activities_log_name');
        });

        // Índices para webhooks y webhook_calls
        Schema::table('webhooks', function (Blueprint $table) {
            $table->index(['tenant_id', 'is_active'], 'idx_webhooks_tenant_active');
        });

        Schema::table('webhook_calls', function (Blueprint $table) {
            $table->index(['webhook_id', 'created_at'], 'idx_webhook_calls_webhook_date');
            $table->index(['status'], 'idx_webhook_calls_status');
        });

        // Índices para API logs
        Schema::table('api_logs', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at'], 'idx_api_logs_tenant_date');
            $table->index(['method', 'endpoint'], 'idx_api_logs_method_endpoint');
            $table->index(['status_code'], 'idx_api_logs_status');
            $table->index(['response_time'], 'idx_api_logs_response_time');
        });

        // Índices para backups
        Schema::table('backups', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at'], 'idx_backups_tenant_date');
            $table->index(['status'], 'idx_backups_status');
            $table->index(['backup_type'], 'idx_backups_type');
        });

        // Índices para sales y purchase books
        Schema::table('sales_books', function (Blueprint $table) {
            $table->index(['tenant_id', 'year', 'month'], 'idx_sales_books_tenant_period');
            $table->index(['status'], 'idx_sales_books_status');
        });

        Schema::table('purchase_books', function (Blueprint $table) {
            $table->index(['tenant_id', 'year', 'month'], 'idx_purchase_books_tenant_period');
            $table->index(['status'], 'idx_purchase_books_status');
        });

        // Índices para tax document items
        Schema::table('tax_document_items', function (Blueprint $table) {
            $table->index(['tax_document_id'], 'idx_tax_document_items_document');
            $table->index(['product_id'], 'idx_tax_document_items_product');
        });

        // Índices para payment allocations
        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->index(['payment_id'], 'idx_payment_allocations_payment');
            $table->index(['tax_document_id'], 'idx_payment_allocations_document');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Tax documents
        Schema::table('tax_documents', function (Blueprint $table) {
            $table->dropIndex('idx_tax_documents_tenant_status');
            $table->dropIndex('idx_tax_documents_tenant_date');
            $table->dropIndex('idx_tax_documents_tenant_type_date');
            $table->dropIndex('idx_tax_documents_customer_status');
            $table->dropIndex('idx_tax_documents_tenant_payment_status');
            $table->dropIndex('idx_tax_documents_tenant_due_date');
        });

        // Payments
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('idx_payments_tenant_date');
            $table->dropIndex('idx_payments_customer_status');
            $table->dropIndex('idx_payments_tenant_method');
            $table->dropIndex('idx_payments_tenant_status');
        });

        // Expenses
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex('idx_expenses_tenant_date');
            $table->dropIndex('idx_expenses_supplier_status');
            $table->dropIndex('idx_expenses_tenant_category');
            $table->dropIndex('idx_expenses_tenant_status');
            $table->dropIndex('idx_expenses_tenant_payment_status');
        });

        // Products
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_tenant_inventory');
            $table->dropIndex('idx_products_tenant_active');
            $table->dropIndex('idx_products_category');
            $table->dropIndex('idx_products_sku');
            $table->dropIndex('idx_products_tenant_stock');
        });

        // Customers
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('idx_customers_tenant_active');
            $table->dropIndex('idx_customers_rut');
            $table->dropIndex('idx_customers_email');
            $table->dropIndex('idx_customers_tenant_created');
        });

        // Suppliers
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex('idx_suppliers_tenant_active');
            $table->dropIndex('idx_suppliers_rut');
            $table->dropIndex('idx_suppliers_email');
        });

        // Inventory movements
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropIndex('idx_inventory_movements_tenant_date');
            $table->dropIndex('idx_inventory_movements_product_type');
            $table->dropIndex('idx_inventory_movements_tenant_type');
            $table->dropIndex('idx_inventory_movements_reference');
        });

        // Bank transactions
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_bank_transactions_account_date');
            $table->dropIndex('idx_bank_transactions_account_status');
            $table->dropIndex('idx_bank_transactions_date_type');
            $table->dropIndex('idx_bank_transactions_reconciliation');
        });

        // Audit logs
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_audit_logs_tenant_date');
            $table->dropIndex('idx_audit_logs_user_date');
            $table->dropIndex('idx_audit_logs_auditable');
            $table->dropIndex('idx_audit_logs_event');
            $table->dropIndex('idx_audit_logs_tenant_event');
        });

        // Activities
        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex('idx_activities_tenant_date');
            $table->dropIndex('idx_activities_subject');
            $table->dropIndex('idx_activities_causer');
            $table->dropIndex('idx_activities_log_name');
        });

        // Webhooks
        Schema::table('webhooks', function (Blueprint $table) {
            $table->dropIndex('idx_webhooks_tenant_active');
        });

        Schema::table('webhook_calls', function (Blueprint $table) {
            $table->dropIndex('idx_webhook_calls_webhook_date');
            $table->dropIndex('idx_webhook_calls_status');
        });

        // API logs
        Schema::table('api_logs', function (Blueprint $table) {
            $table->dropIndex('idx_api_logs_tenant_date');
            $table->dropIndex('idx_api_logs_method_endpoint');
            $table->dropIndex('idx_api_logs_status');
            $table->dropIndex('idx_api_logs_response_time');
        });

        // Backups
        Schema::table('backups', function (Blueprint $table) {
            $table->dropIndex('idx_backups_tenant_date');
            $table->dropIndex('idx_backups_status');
            $table->dropIndex('idx_backups_type');
        });

        // Books
        Schema::table('sales_books', function (Blueprint $table) {
            $table->dropIndex('idx_sales_books_tenant_period');
            $table->dropIndex('idx_sales_books_status');
        });

        Schema::table('purchase_books', function (Blueprint $table) {
            $table->dropIndex('idx_purchase_books_tenant_period');
            $table->dropIndex('idx_purchase_books_status');
        });

        // Items and allocations
        Schema::table('tax_document_items', function (Blueprint $table) {
            $table->dropIndex('idx_tax_document_items_document');
            $table->dropIndex('idx_tax_document_items_product');
        });

        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->dropIndex('idx_payment_allocations_payment');
            $table->dropIndex('idx_payment_allocations_document');
        });
    }
};