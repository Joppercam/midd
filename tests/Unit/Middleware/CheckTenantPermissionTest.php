<?php

namespace Tests\Unit\Middleware;

use Tests\TestCase;
use App\Http\Middleware\CheckTenantPermission;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class CheckTenantPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected CheckTenantPermission $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->middleware = new CheckTenantPermission();
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        
        // Configurar permisos
        $this->setupPermissions();
    }

    protected function setupPermissions()
    {
        Permission::create(['name' => 'view-invoices', 'guard_name' => 'web']);
        Permission::create(['name' => 'create-invoices', 'guard_name' => 'web']);
        Permission::create(['name' => 'admin-access', 'guard_name' => 'web']);
        
        $viewerRole = Role::create(['name' => 'viewer', 'guard_name' => 'web']);
        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        
        $viewerRole->givePermissionTo('view-invoices');
        $adminRole->givePermissionTo(['view-invoices', 'create-invoices', 'admin-access']);
    }

    /** @test */
    public function it_allows_access_when_user_has_permission()
    {
        $this->actingAs($this->user);
        
        // Configurar el contexto del tenant
        setPermissionsTeamId($this->tenant->id);
        
        // Asignar rol con permiso
        $this->user->assignRole('admin');
        
        $request = Request::create('/invoices', 'GET');
        
        $response = $this->middleware->handle($request, function ($req) {
            return new Response('Success');
        }, 'view-invoices');
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Success', $response->getContent());
    }

    /** @test */
    public function it_denies_access_when_user_lacks_permission()
    {
        $this->actingAs($this->user);
        
        // Configurar el contexto del tenant
        setPermissionsTeamId($this->tenant->id);
        
        // Asignar rol sin el permiso necesario
        $this->user->assignRole('viewer');
        
        $request = Request::create('/invoices/create', 'POST');
        
        $response = $this->middleware->handle($request, function ($req) {
            return new Response('Success');
        }, 'create-invoices');
        
        $this->assertEquals(403, $response->getStatusCode());
    }

    /** @test */
    public function it_redirects_to_login_when_user_not_authenticated()
    {
        $request = Request::create('/invoices', 'GET');
        
        $response = $this->middleware->handle($request, function ($req) {
            return new Response('Success');
        }, 'view-invoices');
        
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('login', $response->headers->get('Location'));
    }

    /** @test */
    public function it_handles_multiple_permissions_with_or_logic()
    {
        $this->actingAs($this->user);
        setPermissionsTeamId($this->tenant->id);
        
        // Usuario con solo uno de los permisos
        $this->user->givePermissionTo('view-invoices');
        
        $request = Request::create('/invoices', 'GET');
        
        $response = $this->middleware->handle($request, function ($req) {
            return new Response('Success');
        }, 'view-invoices|create-invoices');
        
        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_handles_multiple_permissions_with_and_logic()
    {
        $this->actingAs($this->user);
        setPermissionsTeamId($this->tenant->id);
        
        // Usuario con solo uno de los permisos requeridos
        $this->user->givePermissionTo('view-invoices');
        
        $request = Request::create('/admin/invoices', 'GET');
        
        $response = $this->middleware->handle($request, function ($req) {
            return new Response('Success');
        }, 'view-invoices&admin-access');
        
        $this->assertEquals(403, $response->getStatusCode());
    }

    /** @test */
    public function it_sets_correct_tenant_context()
    {
        $this->actingAs($this->user);
        
        $request = Request::create('/invoices', 'GET');
        
        $this->middleware->handle($request, function ($req) {
            // Verificar que el team_id está configurado correctamente
            $this->assertEquals($this->tenant->id, getPermissionsTeamId());
            return new Response('Success');
        }, 'view-invoices');
    }

    /** @test */
    public function it_handles_ajax_requests_with_json_response()
    {
        $this->actingAs($this->user);
        setPermissionsTeamId($this->tenant->id);
        
        $request = Request::create('/invoices/create', 'POST');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        
        $response = $this->middleware->handle($request, function ($req) {
            return new Response('Success');
        }, 'create-invoices');
        
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertJson($response->getContent());
        
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('No tienes permisos para realizar esta acción.', $data['message']);
    }

    /** @test */
    public function it_allows_super_admin_to_bypass_permissions()
    {
        $this->actingAs($this->user);
        setPermissionsTeamId($this->tenant->id);
        
        // Crear y asignar rol de super-admin
        $superAdminRole = Role::create(['name' => 'super-admin', 'guard_name' => 'web']);
        $this->user->assignRole('super-admin');
        
        $request = Request::create('/invoices/create', 'POST');
        
        $response = $this->middleware->handle($request, function ($req) {
            return new Response('Success');
        }, 'any-permission-that-does-not-exist');
        
        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_handles_permission_check_for_different_tenant()
    {
        $this->actingAs($this->user);
        
        // Crear otro tenant
        $otherTenant = Tenant::factory()->create();
        
        // Intentar configurar permisos para otro tenant
        setPermissionsTeamId($otherTenant->id);
        
        // El usuario tiene permisos en su tenant, pero no en el otro
        $this->user->assignRole('admin');
        
        $request = Request::create('/invoices', 'GET');
        
        // Debería denegar acceso porque el contexto del tenant es diferente
        $response = $this->middleware->handle($request, function ($req) {
            return new Response('Success');
        }, 'view-invoices');
        
        $this->assertEquals(403, $response->getStatusCode());
    }

    /** @test */
    public function it_caches_permission_checks_for_performance()
    {
        $this->actingAs($this->user);
        setPermissionsTeamId($this->tenant->id);
        $this->user->assignRole('admin');
        
        $request = Request::create('/invoices', 'GET');
        
        // Primera llamada
        $startTime = microtime(true);
        $this->middleware->handle($request, function ($req) {
            return new Response('Success');
        }, 'view-invoices');
        $firstCallTime = microtime(true) - $startTime;
        
        // Segunda llamada (debería usar caché)
        $startTime = microtime(true);
        $this->middleware->handle($request, function ($req) {
            return new Response('Success');
        }, 'view-invoices');
        $secondCallTime = microtime(true) - $startTime;
        
        // La segunda llamada debería ser más rápida
        $this->assertLessThan($firstCallTime, $secondCallTime);
    }
}