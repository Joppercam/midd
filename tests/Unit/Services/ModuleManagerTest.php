<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\ModuleManager;
use App\Models\SystemModule;
use App\Models\TenantModule;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ModuleManagerTest extends TestCase
{
    use RefreshDatabase;

    private ModuleManager $moduleManager;
    private SystemModule $coreModule;
    private SystemModule $invoicingModule;
    private SystemModule $crmModule;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->moduleManager = new ModuleManager();
        
        // Crear módulos del sistema para testing
        $this->coreModule = SystemModule::create([
            'code' => 'core',
            'name' => 'Core System',
            'description' => 'Core functionality',
            'version' => '1.0.0',
            'category' => 'core',
            'base_price' => 0,
            'is_core' => true,
            'is_active' => true,
            'dependencies' => [],
            'sort_order' => 1
        ]);

        $this->invoicingModule = SystemModule::create([
            'code' => 'invoicing',
            'name' => 'Invoicing',
            'description' => 'Invoice management',
            'version' => '1.0.0',
            'category' => 'finance',
            'base_price' => 15000,
            'is_core' => false,
            'is_active' => true,
            'dependencies' => ['core'],
            'sort_order' => 2
        ]);

        $this->crmModule = SystemModule::create([
            'code' => 'crm',
            'name' => 'CRM',
            'description' => 'Customer relationship management',
            'version' => '1.0.0',
            'category' => 'sales',
            'base_price' => 20000,
            'is_core' => false,
            'is_active' => true,
            'dependencies' => ['core', 'invoicing'],
            'sort_order' => 3
        ]);
    }

    /** @test */
    public function it_can_get_available_modules()
    {
        $availableModules = $this->moduleManager->getAvailableModules();

        $this->assertCount(3, $availableModules);
        $this->assertTrue($availableModules->contains('code', 'core'));
        $this->assertTrue($availableModules->contains('code', 'invoicing'));
        $this->assertTrue($availableModules->contains('code', 'crm'));
    }

    /** @test */
    public function it_can_check_if_tenant_has_access_to_module()
    {
        // Habilitar módulo core para el tenant
        TenantModule::create([
            'tenant_id' => $this->tenant->id,
            'module_id' => $this->coreModule->id,
            'is_enabled' => true,
            'enabled_at' => now()
        ]);

        $this->assertTrue($this->moduleManager->hasAccess($this->tenant, 'core'));
        $this->assertFalse($this->moduleManager->hasAccess($this->tenant, 'invoicing'));
    }

    /** @test */
    public function it_can_get_tenant_modules()
    {
        // Habilitar algunos módulos
        TenantModule::create([
            'tenant_id' => $this->tenant->id,
            'module_id' => $this->coreModule->id,
            'is_enabled' => true,
            'enabled_at' => now()
        ]);

        TenantModule::create([
            'tenant_id' => $this->tenant->id,
            'module_id' => $this->invoicingModule->id,
            'is_enabled' => true,
            'enabled_at' => now()
        ]);

        $tenantModules = $this->moduleManager->getTenantModules($this->tenant);

        $this->assertCount(2, $tenantModules);
        $moduleIds = $tenantModules->pluck('module_id')->toArray();
        $this->assertContains($this->coreModule->id, $moduleIds);
        $this->assertContains($this->invoicingModule->id, $moduleIds);
    }

    /** @test */
    public function it_can_enable_module_for_tenant()
    {
        // Primero habilitar core (dependencia)
        TenantModule::create([
            'tenant_id' => $this->tenant->id,
            'module_id' => $this->coreModule->id,
            'is_enabled' => true,
            'enabled_at' => now()
        ]);

        $tenantModule = $this->moduleManager->enableModule($this->tenant, $this->invoicingModule);

        $this->assertInstanceOf(TenantModule::class, $tenantModule);
        $this->assertEquals($this->tenant->id, $tenantModule->tenant_id);
        $this->assertEquals($this->invoicingModule->id, $tenantModule->module_id);
        $this->assertTrue($tenantModule->is_enabled);
    }

    /** @test */
    public function it_validates_dependencies_when_enabling_module()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('El módulo requiere: core');

        // Intentar habilitar invoicing sin core
        $this->moduleManager->enableModule($this->tenant, $this->invoicingModule);
    }

    /** @test */
    public function it_can_disable_module_for_tenant()
    {
        // Habilitar módulo primero
        $tenantModule = TenantModule::create([
            'tenant_id' => $this->tenant->id,
            'module_id' => $this->invoicingModule->id,
            'is_enabled' => true,
            'enabled_at' => now()
        ]);

        $this->moduleManager->disableModule($this->tenant, $this->invoicingModule);

        $tenantModule->refresh();
        $this->assertFalse($tenantModule->is_enabled);
        $this->assertNotNull($tenantModule->disabled_at);
    }

    /** @test */
    public function it_prevents_disabling_core_modules()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No se puede deshabilitar un módulo core');

        TenantModule::create([
            'tenant_id' => $this->tenant->id,
            'module_id' => $this->coreModule->id,
            'is_enabled' => true,
            'enabled_at' => now()
        ]);

        $this->moduleManager->disableModule($this->tenant, $this->coreModule);
    }

    /** @test */
    public function it_can_get_modules_by_category()
    {
        $availableModules = $this->moduleManager->getAvailableModules();
        
        $financeModules = $availableModules->where('category', 'finance');
        $salesModules = $availableModules->where('category', 'sales');
        $coreModules = $availableModules->where('category', 'core');

        $this->assertCount(1, $financeModules);
        $this->assertCount(1, $salesModules);
        $this->assertCount(1, $coreModules);
        $this->assertEquals('invoicing', $financeModules->first()->code);
        $this->assertEquals('crm', $salesModules->first()->code);
        $this->assertEquals('core', $coreModules->first()->code);
    }

    /** @test */
    public function it_respects_module_expiration()
    {
        // Crear módulo expirado
        TenantModule::create([
            'tenant_id' => $this->tenant->id,
            'module_id' => $this->invoicingModule->id,
            'is_enabled' => true,
            'enabled_at' => now()->subDays(30),
            'expires_at' => now()->subDay() // Expirado ayer
        ]);

        $tenantModules = $this->moduleManager->getTenantModules($this->tenant);
        
        // No debería incluir módulos expirados
        $this->assertCount(0, $tenantModules);
    }

    /** @test */
    public function it_includes_modules_on_trial()
    {
        // Crear módulo en prueba
        TenantModule::create([
            'tenant_id' => $this->tenant->id,
            'module_id' => $this->invoicingModule->id,
            'is_enabled' => true,
            'enabled_at' => now(),
            'expires_at' => now()->addDays(7) // Expira en una semana
        ]);

        $tenantModules = $this->moduleManager->getTenantModules($this->tenant);
        
        // Debería incluir módulos en prueba
        $this->assertCount(1, $tenantModules);
        $this->assertTrue($tenantModules->first()->isOnTrial());
    }

    /** @test */
    public function it_handles_module_with_custom_pricing()
    {
        $tenantModule = TenantModule::create([
            'tenant_id' => $this->tenant->id,
            'module_id' => $this->invoicingModule->id,
            'is_enabled' => true,
            'enabled_at' => now(),
            'custom_price' => 12000 // Precio personalizado
        ]);

        $this->assertEquals(12000, $tenantModule->getEffectivePrice());
        
        // Sin precio personalizado debería usar el base
        $tenantModule->update(['custom_price' => null]);
        $this->assertEquals(15000, $tenantModule->getEffectivePrice());
    }

    /** @test */
    public function it_tracks_module_usage_statistics()
    {
        $tenantModule = TenantModule::create([
            'tenant_id' => $this->tenant->id,
            'module_id' => $this->invoicingModule->id,
            'is_enabled' => true,
            'enabled_at' => now()
        ]);

        // Incrementar estadísticas de uso
        $tenantModule->incrementUsage('invoices_created', 5);
        $tenantModule->incrementUsage('api_calls', 100);

        $this->assertEquals(5, $tenantModule->getUsageStat('invoices_created'));
        $this->assertEquals(100, $tenantModule->getUsageStat('api_calls'));
        $this->assertEquals(0, $tenantModule->getUsageStat('nonexistent_stat'));
    }

    /** @test */
    public function it_manages_module_settings()
    {
        $tenantModule = TenantModule::create([
            'tenant_id' => $this->tenant->id,
            'module_id' => $this->invoicingModule->id,
            'is_enabled' => true,
            'enabled_at' => now()
        ]);

        // Establecer configuraciones
        $tenantModule->setSetting('auto_numbering', true);
        $tenantModule->setSetting('email_notifications', false);
        $tenantModule->setSetting('theme.color', 'blue');

        $this->assertTrue($tenantModule->getSetting('auto_numbering'));
        $this->assertFalse($tenantModule->getSetting('email_notifications'));
        $this->assertEquals('blue', $tenantModule->getSetting('theme.color'));
        $this->assertNull($tenantModule->getSetting('nonexistent_setting'));
        $this->assertEquals('default', $tenantModule->getSetting('nonexistent_setting', 'default'));
    }
}