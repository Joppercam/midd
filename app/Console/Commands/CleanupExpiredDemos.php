<?php

namespace App\Console\Commands;

use App\Services\DemoService;
use Illuminate\Console\Command;

class CleanupExpiredDemos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demo:cleanup {--force : Force cleanup without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup expired demo sessions and reset demo environment';

    protected $demoService;

    public function __construct(DemoService $demoService)
    {
        parent::__construct();
        $this->demoService = $demoService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧹 Iniciando limpieza de demos expirados...');

        if (!$this->option('force')) {
            if (!$this->confirm('¿Estás seguro de que quieres limpiar todos los demos expirados?')) {
                $this->info('Operación cancelada.');
                return 0;
            }
        }

        try {
            // Limpiar demos expirados
            $this->demoService->cleanupExpiredDemos();
            
            $this->info('✅ Limpieza completada exitosamente.');
            $this->info('📊 Se han eliminado las sesiones de demo expiradas.');
            $this->info('🔄 El ambiente de demo ha sido resetado con datos frescos.');

            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Error durante la limpieza: ' . $e->getMessage());
            return 1;
        }
    }
}
