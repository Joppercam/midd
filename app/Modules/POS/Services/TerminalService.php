<?php

namespace App\Modules\POS\Services;

use App\Modules\POS\Models\Terminal;
use App\Modules\POS\Models\CashSession;
use App\Modules\POS\Models\Transaction;
use App\Traits\BelongsToTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use ZipArchive;

class TerminalService
{
    use BelongsToTenant;

    /**
     * Obtener lista de terminales
     */
    public function getTerminalsList($filters = [])
    {
        $query = Terminal::query()
            ->with(['currentSession.user', 'assignedUser'])
            ->forCurrentTenant();

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['location'])) {
            $query->where('location', $filters['location']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('code', 'like', "%{$filters['search']}%");
            });
        }

        return $query->orderBy('name')
            ->paginate(20);
    }

    /**
     * Obtener estadísticas de terminales
     */
    public function getTerminalsStatistics()
    {
        $terminals = Terminal::forCurrentTenant()->get();
        
        return [
            'total' => $terminals->count(),
            'active' => $terminals->where('status', 'active')->count(),
            'inactive' => $terminals->where('status', 'inactive')->count(),
            'maintenance' => $terminals->where('status', 'maintenance')->count(),
            'in_use' => $terminals->whereNotNull('current_session_id')->count(),
            'by_location' => $terminals->groupBy('location')->map->count(),
        ];
    }

    /**
     * Obtener ubicaciones disponibles
     */
    public function getAvailableLocations()
    {
        return [
            ['id' => 'store_front', 'name' => 'Tienda Principal'],
            ['id' => 'store_back', 'name' => 'Almacén'],
            ['id' => 'mobile', 'name' => 'Móvil'],
            ['id' => 'warehouse', 'name' => 'Bodega'],
            ['id' => 'office', 'name' => 'Oficina'],
        ];
    }

    /**
     * Obtener impresoras disponibles
     */
    public function getAvailablePrinters()
    {
        // En un entorno real, esto detectaría impresoras del sistema
        return [
            ['id' => 'receipt_1', 'name' => 'Impresora de Recibos 1', 'type' => 'receipt'],
            ['id' => 'receipt_2', 'name' => 'Impresora de Recibos 2', 'type' => 'receipt'],
            ['id' => 'kitchen_1', 'name' => 'Impresora de Cocina', 'type' => 'kitchen'],
            ['id' => 'fiscal_1', 'name' => 'Impresora Fiscal', 'type' => 'fiscal'],
        ];
    }

    /**
     * Obtener plantillas de terminal
     */
    public function getTerminalTemplates()
    {
        return [
            [
                'id' => 'standard',
                'name' => 'Terminal Estándar',
                'settings' => [
                    'auto_print_receipt' => true,
                    'sound_enabled' => true,
                    'theme' => 'light',
                    'timeout' => 900,
                ],
            ],
            [
                'id' => 'restaurant',
                'name' => 'Terminal de Restaurante',
                'settings' => [
                    'auto_print_receipt' => true,
                    'auto_print_kitchen' => true,
                    'sound_enabled' => true,
                    'theme' => 'dark',
                    'timeout' => 1800,
                ],
            ],
            [
                'id' => 'mobile',
                'name' => 'Terminal Móvil',
                'settings' => [
                    'auto_print_receipt' => false,
                    'sound_enabled' => false,
                    'theme' => 'light',
                    'timeout' => 300,
                ],
            ],
        ];
    }

    /**
     * Crear terminal
     */
    public function createTerminal($data)
    {
        DB::beginTransaction();
        try {
            // Generar código único
            $data['code'] = $this->generateTerminalCode();
            $data['tenant_id'] = $this->getCurrentTenantId();
            $data['status'] = 'active';

            // Configuración por defecto
            if (!isset($data['settings'])) {
                $data['settings'] = $this->getDefaultSettings();
            }

            $terminal = Terminal::create($data);

            // Registrar actividad
            $this->logTerminalActivity($terminal->id, 'created', 'Terminal creado');

            DB::commit();

            Log::info('Terminal creado', ['terminal_id' => $terminal->id]);

            return $terminal;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear terminal: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener detalles del terminal
     */
    public function getTerminalDetails($id)
    {
        $terminal = Terminal::with([
            'currentSession.user',
            'assignedUser',
            'cashSessions' => function ($query) {
                $query->latest()->limit(5);
            }
        ])
        ->forCurrentTenant()
        ->findOrFail($id);

        // Agregar estadísticas
        $terminal->stats = $this->calculateTerminalStats($id);

        return $terminal;
    }

    /**
     * Obtener sesiones del terminal
     */
    public function getTerminalSessions($terminalId)
    {
        return CashSession::where('terminal_id', $terminalId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
    }

    /**
     * Obtener actividad del terminal
     */
    public function getTerminalActivity($terminalId, $limit = 20)
    {
        return DB::table('terminal_activities')
            ->where('terminal_id', $terminalId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Obtener rendimiento del terminal
     */
    public function getTerminalPerformance($terminalId, $period = 30)
    {
        $startDate = now()->subDays($period);
        
        $transactions = Transaction::where('terminal_id', $terminalId)
            ->where('created_at', '>=', $startDate)
            ->where('status', 'completed')
            ->get();

        $dailyStats = $transactions->groupBy(function ($item) {
            return $item->created_at->format('Y-m-d');
        })->map(function ($dayTransactions) {
            return [
                'count' => $dayTransactions->count(),
                'total' => $dayTransactions->sum('total'),
                'average' => $dayTransactions->avg('total'),
            ];
        });

        return [
            'daily_stats' => $dailyStats,
            'total_transactions' => $transactions->count(),
            'total_revenue' => $transactions->sum('total'),
            'average_transaction' => $transactions->avg('total'),
            'busiest_hour' => $this->getBusiestHour($transactions),
            'uptime' => $this->calculateUptime($terminalId, $period),
        ];
    }

    /**
     * Obtener terminal
     */
    public function getTerminal($id)
    {
        return Terminal::forCurrentTenant()->findOrFail($id);
    }

    /**
     * Actualizar terminal
     */
    public function updateTerminal($id, $data)
    {
        DB::beginTransaction();
        try {
            $terminal = Terminal::forCurrentTenant()->findOrFail($id);
            
            $terminal->update($data);

            // Registrar actividad
            $this->logTerminalActivity($id, 'updated', 'Terminal actualizado');

            DB::commit();

            Log::info('Terminal actualizado', ['terminal_id' => $id]);

            return $terminal;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar terminal: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Eliminar terminal
     */
    public function deleteTerminal($id)
    {
        DB::beginTransaction();
        try {
            $terminal = Terminal::forCurrentTenant()->findOrFail($id);

            // Verificar que no tiene sesiones activas
            if ($terminal->currentSession) {
                throw new \Exception('No se puede eliminar un terminal con sesión activa');
            }

            // Verificar que no tiene transacciones recientes
            $recentTransactions = Transaction::where('terminal_id', $id)
                ->where('created_at', '>=', now()->subDays(30))
                ->exists();

            if ($recentTransactions) {
                throw new \Exception('No se puede eliminar un terminal con transacciones recientes');
            }

            $terminal->delete();

            DB::commit();

            Log::info('Terminal eliminado', ['terminal_id' => $id]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar terminal: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Activar terminal
     */
    public function activateTerminal($id)
    {
        $terminal = Terminal::forCurrentTenant()->findOrFail($id);
        
        if ($terminal->status === 'active') {
            throw new \Exception('El terminal ya está activo');
        }

        $terminal->update(['status' => 'active']);

        $this->logTerminalActivity($id, 'activated', 'Terminal activado');

        return $terminal;
    }

    /**
     * Desactivar terminal
     */
    public function deactivateTerminal($id)
    {
        $terminal = Terminal::forCurrentTenant()->findOrFail($id);
        
        if ($terminal->currentSession) {
            throw new \Exception('No se puede desactivar un terminal con sesión activa');
        }

        $terminal->update(['status' => 'inactive']);

        $this->logTerminalActivity($id, 'deactivated', 'Terminal desactivado');

        return $terminal;
    }

    /**
     * Asignar terminal a usuario
     */
    public function assignTerminal($terminalId, $userId, $notes = null)
    {
        DB::beginTransaction();
        try {
            $terminal = Terminal::forCurrentTenant()->findOrFail($terminalId);
            
            if ($terminal->assigned_to && $terminal->assigned_to !== $userId) {
                throw new \Exception('El terminal ya está asignado a otro usuario');
            }

            $terminal->update([
                'assigned_to' => $userId,
                'assigned_at' => now(),
            ]);

            $this->logTerminalActivity($terminalId, 'assigned', "Terminal asignado a usuario #$userId", [
                'user_id' => $userId,
                'notes' => $notes,
            ]);

            DB::commit();

            return $terminal;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al asignar terminal: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Liberar terminal
     */
    public function unassignTerminal($terminalId)
    {
        $terminal = Terminal::forCurrentTenant()->findOrFail($terminalId);
        
        $previousUser = $terminal->assigned_to;
        
        $terminal->update([
            'assigned_to' => null,
            'assigned_at' => null,
        ]);

        $this->logTerminalActivity($terminalId, 'unassigned', 'Terminal liberado', [
            'previous_user_id' => $previousUser,
        ]);

        return $terminal;
    }

    /**
     * Probar impresora
     */
    public function testPrinter($terminalId, $printerType)
    {
        $terminal = Terminal::forCurrentTenant()->findOrFail($terminalId);
        
        // Aquí se implementaría la lógica real de prueba de impresora
        $testResult = [
            'success' => true,
            'printer_type' => $printerType,
            'message' => 'Prueba de impresión enviada',
            'timestamp' => now(),
        ];

        $this->logTerminalActivity($terminalId, 'printer_test', "Prueba de impresora $printerType", $testResult);

        return $testResult;
    }

    /**
     * Reiniciar terminal
     */
    public function restartTerminal($terminalId)
    {
        $terminal = Terminal::forCurrentTenant()->findOrFail($terminalId);
        
        // Limpiar cache del terminal
        $this->clearTerminalCache($terminalId);
        
        // Actualizar última actividad
        $terminal->update(['last_activity_at' => now()]);

        $this->logTerminalActivity($terminalId, 'restarted', 'Terminal reiniciado');

        return $terminal;
    }

    /**
     * Obtener estado del terminal
     */
    public function getTerminalStatus($terminalId)
    {
        $terminal = Terminal::forCurrentTenant()->findOrFail($terminalId);
        
        $status = [
            'online' => $this->isTerminalOnline($terminal),
            'status' => $terminal->status,
            'current_session' => $terminal->currentSession ? [
                'id' => $terminal->currentSession->id,
                'user' => $terminal->currentSession->user->name,
                'started_at' => $terminal->currentSession->opened_at,
            ] : null,
            'last_activity' => $terminal->last_activity_at,
            'uptime' => $this->calculateUptime($terminalId, 1),
            'health' => $this->checkTerminalHealth($terminal),
        ];

        return $status;
    }

    /**
     * Actualizar configuración del terminal
     */
    public function updateTerminalSettings($terminalId, $settings)
    {
        $terminal = Terminal::forCurrentTenant()->findOrFail($terminalId);
        
        $currentSettings = $terminal->settings ?? [];
        $newSettings = array_merge($currentSettings, $settings);
        
        $terminal->update(['settings' => $newSettings]);

        $this->logTerminalActivity($terminalId, 'settings_updated', 'Configuración actualizada', $settings);

        // Limpiar cache
        $this->clearTerminalCache($terminalId);

        return $terminal;
    }

    /**
     * Ejecutar diagnósticos
     */
    public function runDiagnostics($terminalId)
    {
        $terminal = Terminal::forCurrentTenant()->findOrFail($terminalId);
        
        $diagnostics = [
            'connectivity' => $this->checkConnectivity($terminal),
            'printers' => $this->checkPrinters($terminal),
            'storage' => $this->checkStorage($terminal),
            'performance' => $this->checkPerformance($terminal),
            'errors' => $this->getRecentErrors($terminalId),
        ];

        $this->logTerminalActivity($terminalId, 'diagnostics', 'Diagnósticos ejecutados', $diagnostics);

        return $diagnostics;
    }

    /**
     * Obtener logs del terminal
     */
    public function getTerminalLogs($terminalId, $limit = 100)
    {
        return DB::table('terminal_logs')
            ->where('terminal_id', $terminalId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Limpiar cache del terminal
     */
    public function clearTerminalCache($terminalId)
    {
        Cache::tags(['terminal', "terminal_{$terminalId}"])->flush();
        
        $this->logTerminalActivity($terminalId, 'cache_cleared', 'Cache limpiado');
        
        Log::info('Cache de terminal limpiado', ['terminal_id' => $terminalId]);
    }

    /**
     * Crear respaldo del terminal
     */
    public function createTerminalBackup($terminalId)
    {
        DB::beginTransaction();
        try {
            $terminal = Terminal::forCurrentTenant()->findOrFail($terminalId);
            
            // Recopilar datos para respaldo
            $backupData = [
                'terminal' => $terminal->toArray(),
                'settings' => $terminal->settings,
                'recent_sessions' => $this->getTerminalSessions($terminalId)->toArray(),
                'activity_log' => $this->getTerminalActivity($terminalId, 100),
                'created_at' => now(),
                'created_by' => Auth::id(),
            ];

            // Crear archivo de respaldo
            $filename = "terminal_backup_{$terminalId}_" . now()->format('Y-m-d_H-i-s') . '.json';
            $path = "backups/terminals/{$filename}";
            
            Storage::put($path, json_encode($backupData, JSON_PRETTY_PRINT));

            // Crear ZIP si es necesario
            $zipPath = $this->createBackupZip($terminalId, $path);

            $backup = [
                'id' => Str::uuid(),
                'terminal_id' => $terminalId,
                'filename' => $filename,
                'path' => $zipPath,
                'size' => Storage::size($zipPath),
                'created_at' => now(),
            ];

            // Registrar respaldo
            DB::table('terminal_backups')->insert($backup);

            $this->logTerminalActivity($terminalId, 'backup_created', 'Respaldo creado', $backup);

            DB::commit();

            return $backup;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear respaldo: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Restaurar respaldo del terminal
     */
    public function restoreTerminalBackup($terminalId, $backupFile)
    {
        DB::beginTransaction();
        try {
            // Validar y extraer archivo
            $tempPath = $backupFile->store('temp');
            $extractPath = storage_path('app/temp/restore_' . Str::random(8));
            
            // Extraer ZIP
            $zip = new ZipArchive;
            if ($zip->open(storage_path('app/' . $tempPath)) === true) {
                $zip->extractTo($extractPath);
                $zip->close();
            }

            // Leer datos del respaldo
            $backupData = json_decode(file_get_contents($extractPath . '/backup.json'), true);

            // Validar que el respaldo corresponde al terminal
            if ($backupData['terminal']['id'] != $terminalId) {
                throw new \Exception('El respaldo no corresponde a este terminal');
            }

            // Restaurar configuración
            $terminal = Terminal::forCurrentTenant()->findOrFail($terminalId);
            $terminal->update(['settings' => $backupData['settings']]);

            $this->logTerminalActivity($terminalId, 'backup_restored', 'Respaldo restaurado');

            // Limpiar archivos temporales
            Storage::delete($tempPath);
            $this->deleteDirectory($extractPath);

            DB::commit();

            return $terminal;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al restaurar respaldo: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Métodos privados de ayuda
     */
    private function generateTerminalCode()
    {
        do {
            $code = 'POS-' . strtoupper(Str::random(6));
        } while (Terminal::where('code', $code)->exists());

        return $code;
    }

    private function getDefaultSettings()
    {
        return [
            'receipt_printer' => null,
            'kitchen_printer' => null,
            'auto_print_receipt' => true,
            'sound_enabled' => true,
            'theme' => 'light',
            'timeout' => 900, // 15 minutos
            'language' => 'es',
            'currency' => 'CLP',
        ];
    }

    private function logTerminalActivity($terminalId, $action, $description, $data = null)
    {
        DB::table('terminal_activities')->insert([
            'terminal_id' => $terminalId,
            'action' => $action,
            'description' => $description,
            'data' => $data ? json_encode($data) : null,
            'user_id' => Auth::id(),
            'created_at' => now(),
            'tenant_id' => $this->getCurrentTenantId(),
        ]);
    }

    private function calculateTerminalStats($terminalId)
    {
        $last30Days = now()->subDays(30);
        
        $transactions = Transaction::where('terminal_id', $terminalId)
            ->where('created_at', '>=', $last30Days)
            ->get();

        return [
            'total_sales' => $transactions->where('type', 'sale')->where('status', 'completed')->sum('total'),
            'transaction_count' => $transactions->count(),
            'average_transaction' => $transactions->avg('total'),
            'sessions_count' => CashSession::where('terminal_id', $terminalId)
                ->where('created_at', '>=', $last30Days)
                ->count(),
        ];
    }

    private function getBusiestHour($transactions)
    {
        $hourlyStats = $transactions->groupBy(function ($item) {
            return $item->created_at->format('H');
        })->map->count();

        if ($hourlyStats->isEmpty()) {
            return null;
        }

        $busiestHour = $hourlyStats->sortDesc()->keys()->first();
        
        return [
            'hour' => $busiestHour,
            'transactions' => $hourlyStats[$busiestHour],
        ];
    }

    private function calculateUptime($terminalId, $days)
    {
        $startDate = now()->subDays($days);
        
        $totalMinutes = $days * 24 * 60;
        $activeMinutes = DB::table('terminal_activities')
            ->where('terminal_id', $terminalId)
            ->where('created_at', '>=', $startDate)
            ->whereIn('action', ['activated', 'activity'])
            ->count() * 5; // Asumiendo actividad cada 5 minutos

        return min(($activeMinutes / $totalMinutes) * 100, 100);
    }

    private function isTerminalOnline($terminal)
    {
        if (!$terminal->last_activity_at) {
            return false;
        }

        // Considerar online si tuvo actividad en los últimos 5 minutos
        return $terminal->last_activity_at->gt(now()->subMinutes(5));
    }

    private function checkTerminalHealth($terminal)
    {
        $issues = [];
        
        // Verificar conectividad
        if (!$this->isTerminalOnline($terminal)) {
            $issues[] = 'offline';
        }

        // Verificar errores recientes
        $recentErrors = $this->getRecentErrors($terminal->id);
        if (count($recentErrors) > 5) {
            $issues[] = 'high_error_rate';
        }

        // Verificar rendimiento
        if ($terminal->last_activity_at && $terminal->last_activity_at->lt(now()->subHours(24))) {
            $issues[] = 'inactive';
        }

        return [
            'status' => empty($issues) ? 'healthy' : 'warning',
            'issues' => $issues,
        ];
    }

    private function checkConnectivity($terminal)
    {
        return [
            'status' => $this->isTerminalOnline($terminal) ? 'connected' : 'disconnected',
            'last_seen' => $terminal->last_activity_at,
            'latency' => rand(10, 50) . 'ms', // Simulado
        ];
    }

    private function checkPrinters($terminal)
    {
        $printers = [];
        
        if (isset($terminal->settings['receipt_printer'])) {
            $printers['receipt'] = [
                'id' => $terminal->settings['receipt_printer'],
                'status' => 'ready', // Simulado
                'paper' => 'ok',
            ];
        }

        if (isset($terminal->settings['kitchen_printer'])) {
            $printers['kitchen'] = [
                'id' => $terminal->settings['kitchen_printer'],
                'status' => 'ready',
                'paper' => 'ok',
            ];
        }

        return $printers;
    }

    private function checkStorage($terminal)
    {
        return [
            'available' => '2.5GB', // Simulado
            'used' => '500MB',
            'percentage' => 20,
        ];
    }

    private function checkPerformance($terminal)
    {
        return [
            'cpu_usage' => rand(10, 40) . '%', // Simulado
            'memory_usage' => rand(30, 60) . '%',
            'response_time' => rand(100, 300) . 'ms',
        ];
    }

    private function getRecentErrors($terminalId)
    {
        return DB::table('terminal_logs')
            ->where('terminal_id', $terminalId)
            ->where('level', 'error')
            ->where('created_at', '>=', now()->subHours(24))
            ->get();
    }

    private function createBackupZip($terminalId, $jsonPath)
    {
        $zipFilename = "terminal_backup_{$terminalId}_" . now()->format('Y-m-d_H-i-s') . '.zip';
        $zipPath = "backups/terminals/{$zipFilename}";
        
        $zip = new ZipArchive;
        $zipFullPath = storage_path('app/' . $zipPath);
        
        if ($zip->open($zipFullPath, ZipArchive::CREATE) === true) {
            $zip->addFile(storage_path('app/' . $jsonPath), 'backup.json');
            $zip->close();
        }

        // Eliminar archivo JSON original
        Storage::delete($jsonPath);

        return $zipPath;
    }

    private function deleteDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        
        rmdir($dir);
    }
}