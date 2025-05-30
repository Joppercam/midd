<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Contactos
        Schema::create('crm_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('company_id')->nullable()->constrained('crm_companies');
            $table->foreignId('owner_id')->constrained('users'); // Usuario responsable
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('position')->nullable();
            $table->string('department')->nullable();
            $table->enum('lead_status', ['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'won', 'lost'])->default('new');
            $table->enum('contact_type', ['lead', 'prospect', 'customer', 'partner', 'vendor'])->default('lead');
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->date('birth_date')->nullable();
            $table->json('social_networks')->nullable(); // LinkedIn, Twitter, etc.
            $table->text('notes')->nullable();
            $table->json('custom_fields')->nullable();
            $table->string('source')->nullable(); // Website, Referral, Cold Call, etc.
            $table->decimal('score', 5, 2)->default(0); // Lead scoring
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['tenant_id', 'lead_status']);
            $table->index(['tenant_id', 'owner_id']);
            $table->index(['tenant_id', 'company_id']);
        });

        // Empresas
        Schema::create('crm_companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('owner_id')->constrained('users');
            $table->string('name');
            $table->string('rut')->nullable();
            $table->string('website')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('industry')->nullable();
            $table->enum('size', ['1-10', '11-50', '51-200', '201-500', '500+'])->nullable();
            $table->decimal('annual_revenue', 15, 2)->nullable();
            $table->integer('employee_count')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->text('description')->nullable();
            $table->json('custom_fields')->nullable();
            $table->enum('status', ['active', 'inactive', 'prospect'])->default('prospect');
            $table->decimal('score', 5, 2)->default(0); // Company scoring
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'owner_id']);
        });

        // Oportunidades/Deals
        Schema::create('crm_opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('contact_id')->constrained('crm_contacts');
            $table->foreignId('company_id')->nullable()->constrained('crm_companies');
            $table->foreignId('owner_id')->constrained('users');
            $table->foreignId('pipeline_id')->constrained('crm_pipelines');
            $table->foreignId('stage_id')->constrained('crm_pipeline_stages');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->decimal('probability', 5, 2)->default(0); // 0-100%
            $table->date('expected_close_date')->nullable();
            $table->date('actual_close_date')->nullable();
            $table->enum('status', ['open', 'won', 'lost'])->default('open');
            $table->string('lost_reason')->nullable();
            $table->string('competitor')->nullable();
            $table->json('products')->nullable(); // Productos/servicios incluidos
            $table->json('custom_fields')->nullable();
            $table->integer('stage_duration')->default(0); // Días en etapa actual
            $table->timestamp('stage_changed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'owner_id']);
            $table->index(['tenant_id', 'pipeline_id', 'stage_id']);
        });

        // Pipelines de venta
        Schema::create('crm_pipelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['tenant_id', 'is_active']);
        });

        // Etapas del pipeline
        Schema::create('crm_pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_id')->constrained('crm_pipelines')->onDelete('cascade');
            $table->string('name');
            $table->integer('order')->default(0);
            $table->decimal('probability', 5, 2)->default(0);
            $table->string('color')->default('#gray');
            $table->boolean('is_won')->default(false);
            $table->boolean('is_lost')->default(false);
            $table->timestamps();
            
            $table->index(['pipeline_id', 'order']);
        });

        // Actividades (llamadas, reuniones, tareas, emails)
        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('user_id')->constrained(); // Usuario asignado
            $table->morphs('related'); // Polimórfico: contact, company, opportunity
            $table->enum('type', ['call', 'meeting', 'task', 'email', 'note']);
            $table->string('subject');
            $table->text('description')->nullable();
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->dateTime('due_date')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->string('outcome')->nullable(); // Resultado de la actividad
            $table->json('attendees')->nullable(); // Para reuniones
            $table->string('location')->nullable();
            $table->integer('duration')->nullable(); // En minutos
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'user_id', 'status']);
            $table->index(['tenant_id', 'type', 'status']);
            $table->index(['related_type', 'related_id']);
        });

        // Campañas de marketing
        Schema::create('crm_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('created_by')->constrained('users');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['email', 'social', 'event', 'webinar', 'other'])->default('email');
            $table->enum('status', ['draft', 'active', 'paused', 'completed', 'cancelled'])->default('draft');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->decimal('actual_cost', 12, 2)->nullable();
            $table->decimal('expected_revenue', 12, 2)->nullable();
            $table->json('target_audience')->nullable(); // Criterios de segmentación
            $table->json('goals')->nullable(); // Objetivos de la campaña
            $table->json('metrics')->nullable(); // Métricas de resultado
            $table->timestamps();
            
            $table->index(['tenant_id', 'status']);
        });

        // Miembros de campaña
        Schema::create('crm_campaign_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('crm_campaigns')->onDelete('cascade');
            $table->foreignId('contact_id')->constrained('crm_contacts');
            $table->enum('status', ['sent', 'opened', 'clicked', 'responded', 'converted'])->default('sent');
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('opened_at')->nullable();
            $table->dateTime('clicked_at')->nullable();
            $table->dateTime('responded_at')->nullable();
            $table->dateTime('converted_at')->nullable();
            $table->decimal('revenue_generated', 12, 2)->nullable();
            $table->timestamps();
            
            $table->unique(['campaign_id', 'contact_id']);
            $table->index(['campaign_id', 'status']);
        });

        // Plantillas de email
        Schema::create('crm_email_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('name');
            $table->string('subject');
            $table->text('body');
            $table->enum('type', ['welcome', 'follow_up', 'proposal', 'thank_you', 'custom'])->default('custom');
            $table->json('variables')->nullable(); // Variables disponibles en la plantilla
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['tenant_id', 'type', 'is_active']);
        });

        // Historial de comunicaciones
        Schema::create('crm_communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->morphs('communicable'); // contact, company, opportunity
            $table->foreignId('user_id')->constrained(); // Usuario que realizó la comunicación
            $table->enum('type', ['email', 'call', 'meeting', 'note', 'sms', 'whatsapp']);
            $table->enum('direction', ['inbound', 'outbound'])->nullable();
            $table->string('subject')->nullable();
            $table->text('content');
            $table->json('metadata')->nullable(); // Duración llamada, asistentes reunión, etc.
            $table->json('attachments')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            
            $table->index(['tenant_id', 'communicable_type', 'communicable_id']);
            $table->index(['tenant_id', 'type', 'occurred_at']);
        });

        // Tags/Etiquetas
        Schema::create('crm_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('name');
            $table->string('color')->default('#gray');
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->unique(['tenant_id', 'name']);
        });

        // Tabla pivote para tags
        Schema::create('crm_taggables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained('crm_tags')->onDelete('cascade');
            $table->morphs('taggable');
            $table->timestamps();
            
            $table->unique(['tag_id', 'taggable_type', 'taggable_id']);
        });

        // Documentos
        Schema::create('crm_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->morphs('documentable'); // contact, company, opportunity
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('name');
            $table->string('file_path');
            $table->string('mime_type');
            $table->integer('size'); // En bytes
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'documentable_type', 'documentable_id']);
        });

        // Lead scoring rules
        Schema::create('crm_scoring_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('name');
            $table->enum('entity_type', ['contact', 'company']);
            $table->json('conditions'); // Condiciones para aplicar la regla
            $table->integer('points'); // Puntos a sumar/restar
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['tenant_id', 'entity_type', 'is_active']);
        });

        // Productos CRM (para oportunidades)
        Schema::create('crm_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->enum('type', ['product', 'service'])->default('product');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['tenant_id', 'is_active']);
        });

        // Configuración CRM por tenant
        Schema::create('crm_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->json('lead_sources'); // Fuentes de leads configuradas
            $table->json('industries'); // Industrias configuradas
            $table->json('deal_lost_reasons'); // Razones de pérdida
            $table->integer('lead_conversion_days')->default(30); // Días para convertir lead
            $table->boolean('auto_lead_assignment')->default(false);
            $table->json('assignment_rules')->nullable();
            $table->boolean('duplicate_check_enabled')->default(true);
            $table->json('duplicate_fields')->nullable(); // Campos para verificar duplicados
            $table->timestamps();
            
            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_settings');
        Schema::dropIfExists('crm_products');
        Schema::dropIfExists('crm_scoring_rules');
        Schema::dropIfExists('crm_documents');
        Schema::dropIfExists('crm_taggables');
        Schema::dropIfExists('crm_tags');
        Schema::dropIfExists('crm_communications');
        Schema::dropIfExists('crm_email_templates');
        Schema::dropIfExists('crm_campaign_members');
        Schema::dropIfExists('crm_campaigns');
        Schema::dropIfExists('crm_activities');
        Schema::dropIfExists('crm_pipeline_stages');
        Schema::dropIfExists('crm_pipelines');
        Schema::dropIfExists('crm_opportunities');
        Schema::dropIfExists('crm_companies');
        Schema::dropIfExists('crm_contacts');
    }
};