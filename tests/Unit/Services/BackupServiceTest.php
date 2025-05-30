<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\BackupService;
use App\Models\Backup;
use App\Models\BackupSchedule;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use ZipArchive;

class BackupServiceTest extends TestCase
{
    use RefreshDatabase;

    protected BackupService $backupService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->backupService = new BackupService();
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        
        // Mock storage disks
        Storage::fake('local');
        Storage::fake('backups');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_creates_a_backup_for_tenant()
    {
        $backup = $this->backupService->createBackup($this->tenant->id, 'manual', $this->user->id);

        $this->assertInstanceOf(Backup::class, $backup);
        $this->assertEquals($this->tenant->id, $backup->tenant_id);
        $this->assertEquals('manual', $backup->type);
        $this->assertEquals($this->user->id, $backup->user_id);
        $this->assertEquals('completed', $backup->status);
        $this->assertNotNull($backup->completed_at);
        Storage::disk('backups')->assertExists($backup->file_path);
    }

    /** @test */
    public function it_includes_database_data_in_backup()
    {
        // Crear algunos datos de prueba
        $customer = \App\Models\Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $product = \App\Models\Product::factory()->create(['tenant_id' => $this->tenant->id]);
        
        $backup = $this->backupService->createBackup($this->tenant->id, 'manual', $this->user->id);
        
        // Verificar que el archivo existe
        Storage::disk('backups')->assertExists($backup->file_path);
        
        // Verificar metadata
        $this->assertArrayHasKey('database', $backup->metadata);
        $this->assertArrayHasKey('files', $backup->metadata);
        $this->assertArrayHasKey('tables_count', $backup->metadata['database']);
    }

    /** @test */
    public function it_handles_backup_failure_gracefully()
    {
        // Mock del método para forzar una excepción
        $mockService = Mockery::mock(BackupService::class)->makePartial();
        $mockService->shouldReceive('backupDatabase')
            ->andThrow(new \Exception('Database backup failed'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database backup failed');

        $mockService->createBackup($this->tenant->id, 'manual', $this->user->id);
    }

    /** @test */
    public function it_creates_scheduled_backup()
    {
        $schedule = BackupSchedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'frequency' => 'daily',
            'time' => '02:00',
            'is_active' => true,
        ]);

        $backup = $this->backupService->createScheduledBackup($schedule);

        $this->assertInstanceOf(Backup::class, $backup);
        $this->assertEquals('scheduled', $backup->type);
        $this->assertEquals($this->tenant->id, $backup->tenant_id);
        $this->assertNotNull($schedule->fresh()->last_run_at);
    }

    /** @test */
    public function it_restores_database_from_backup()
    {
        // Crear backup primero
        $backup = $this->backupService->createBackup($this->tenant->id, 'manual', $this->user->id);
        
        // Eliminar algunos datos
        DB::table('customers')->where('tenant_id', $this->tenant->id)->delete();
        
        // Restaurar
        $result = $this->backupService->restoreBackup($backup, $this->user->id, ['database' => true]);
        
        $this->assertTrue($result);
        $this->assertDatabaseHas('backup_restore_logs', [
            'backup_id' => $backup->id,
            'restored_by' => $this->user->id,
            'status' => 'completed',
        ]);
    }

    /** @test */
    public function it_validates_backup_integrity()
    {
        $backup = $this->backupService->createBackup($this->tenant->id, 'manual', $this->user->id);
        
        $isValid = $this->backupService->validateBackup($backup);
        
        $this->assertTrue($isValid);
    }

    /** @test */
    public function it_cleans_old_backups_based_on_retention_policy()
    {
        // Crear varios backups con diferentes fechas
        $oldBackups = [];
        for ($i = 1; $i <= 5; $i++) {
            $oldBackups[] = Backup::factory()->create([
                'tenant_id' => $this->tenant->id,
                'created_at' => now()->subDays(40 + $i),
                'type' => 'scheduled',
            ]);
        }
        
        $recentBackup = Backup::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_at' => now()->subDays(5),
            'type' => 'scheduled',
        ]);

        $deletedCount = $this->backupService->cleanupOldBackups($this->tenant->id);

        $this->assertEquals(5, $deletedCount);
        $this->assertDatabaseMissing('backups', ['id' => $oldBackups[0]->id]);
        $this->assertDatabaseHas('backups', ['id' => $recentBackup->id]);
    }

    /** @test */
    public function it_gets_backup_statistics_for_tenant()
    {
        // Crear algunos backups
        Backup::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'completed',
            'size_bytes' => 1024 * 1024, // 1MB
        ]);
        
        Backup::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'failed',
        ]);

        $stats = $this->backupService->getBackupStatistics($this->tenant->id);

        $this->assertEquals(4, $stats['total_backups']);
        $this->assertEquals(3, $stats['successful_backups']);
        $this->assertEquals(1, $stats['failed_backups']);
        $this->assertEquals(3 * 1024 * 1024, $stats['total_size']);
        $this->assertArrayHasKey('last_backup', $stats);
        $this->assertArrayHasKey('average_size', $stats);
    }

    /** @test */
    public function it_creates_backup_with_custom_retention_period()
    {
        $backup = $this->backupService->createBackup(
            $this->tenant->id, 
            'manual', 
            $this->user->id,
            ['retention_days' => 90]
        );

        $this->assertEquals(90, $backup->metadata['retention_days']);
        $this->assertEquals(
            now()->addDays(90)->format('Y-m-d'),
            $backup->expires_at->format('Y-m-d')
        );
    }

    /** @test */
    public function it_excludes_specified_tables_from_backup()
    {
        $options = [
            'exclude_tables' => ['cache', 'sessions', 'jobs'],
        ];

        $backup = $this->backupService->createBackup(
            $this->tenant->id, 
            'manual', 
            $this->user->id,
            $options
        );

        $this->assertArrayHasKey('excluded_tables', $backup->metadata);
        $this->assertEquals($options['exclude_tables'], $backup->metadata['excluded_tables']);
    }

    /** @test */
    public function it_handles_concurrent_backups_for_same_tenant()
    {
        // Simular backup en progreso
        $existingBackup = Backup::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'in_progress',
            'created_at' => now()->subMinutes(5),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Another backup is already in progress');

        $this->backupService->createBackup($this->tenant->id, 'manual', $this->user->id);
    }

    /** @test */
    public function it_sends_notification_on_backup_completion()
    {
        Log::shouldReceive('channel->info')->once();

        $backup = $this->backupService->createBackup($this->tenant->id, 'manual', $this->user->id);

        $this->assertEquals('completed', $backup->status);
    }

    /** @test */
    public function it_supports_incremental_backups()
    {
        // Crear backup completo primero
        $fullBackup = $this->backupService->createBackup(
            $this->tenant->id, 
            'scheduled', 
            null,
            ['backup_mode' => 'full']
        );

        // Crear backup incremental
        $incrementalBackup = $this->backupService->createBackup(
            $this->tenant->id, 
            'scheduled', 
            null,
            ['backup_mode' => 'incremental', 'base_backup_id' => $fullBackup->id]
        );

        $this->assertEquals('incremental', $incrementalBackup->metadata['backup_mode']);
        $this->assertEquals($fullBackup->id, $incrementalBackup->metadata['base_backup_id']);
        $this->assertLessThan($fullBackup->size_bytes, $incrementalBackup->size_bytes);
    }
}