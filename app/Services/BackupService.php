<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\BackupSchedule;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use ZipArchive;
use Carbon\Carbon;

class BackupService
{
    protected array $defaultExcludedTables = [
        'migrations',
        'password_resets',
        'password_reset_tokens',
        'failed_jobs',
        'personal_access_tokens',
        'telescope_entries',
        'telescope_entries_tags',
        'telescope_monitoring'
    ];

    public function createBackup(Backup $backup): void
    {
        try {
            $backup->start();
            
            $tempPath = storage_path('app/temp/backup_' . $backup->id);
            File::makeDirectory($tempPath, 0755, true, true);
            
            $statistics = [];
            
            // Backup database tables
            if (!empty($backup->included_tables) || empty($backup->excluded_tables)) {
                $statistics['tables'] = $this->backupDatabase($backup, $tempPath);
            }
            
            // Backup files
            if ($backup->include_files) {
                $statistics['files'] = $this->backupFiles($backup, $tempPath);
            }
            
            // Create ZIP archive
            $zipPath = $this->createZipArchive($backup, $tempPath);
            
            // Move to final storage
            $finalPath = $this->moveToStorage($backup, $zipPath);
            
            // Get file size
            $size = Storage::disk($backup->disk)->size($finalPath);
            
            // Clean up temp files
            File::deleteDirectory($tempPath);
            File::delete($zipPath);
            
            $backup->complete($finalPath, $size, $statistics);
            
        } catch (\Exception $e) {
            Log::error('Backup failed', [
                'backup_id' => $backup->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Clean up on failure
            if (isset($tempPath)) {
                File::deleteDirectory($tempPath);
            }
            if (isset($zipPath)) {
                File::delete($zipPath);
            }
            
            $backup->fail($e->getMessage());
            throw $e;
        }
    }

    protected function backupDatabase(Backup $backup, string $tempPath): array
    {
        $statistics = [
            'total_tables' => 0,
            'total_records' => 0,
            'tables' => []
        ];
        
        $tables = $this->getTablesToBackup($backup);
        
        foreach ($tables as $table) {
            $query = DB::table($table);
            
            // Filter by tenant if applicable
            if ($backup->tenant_id && Schema::hasColumn($table, 'tenant_id')) {
                $query->where('tenant_id', $backup->tenant_id);
            }
            
            $data = $query->get();
            $count = $data->count();
            
            if ($count > 0) {
                $filePath = $tempPath . '/database/' . $table . '.json';
                File::makeDirectory(dirname($filePath), 0755, true, true);
                File::put($filePath, json_encode($data, JSON_PRETTY_PRINT));
                
                $statistics['tables'][$table] = $count;
                $statistics['total_records'] += $count;
            }
            
            $statistics['total_tables']++;
        }
        
        // Save database schema
        $this->backupSchema($tempPath . '/database/schema.sql');
        
        return $statistics;
    }

    protected function backupFiles(Backup $backup, string $tempPath): array
    {
        $statistics = [
            'total_files' => 0,
            'total_size' => 0,
            'directories' => []
        ];
        
        $directories = $backup->tenant_id 
            ? ['private/tenant_' . $backup->tenant_id, 'public/tenant_' . $backup->tenant_id]
            : ['private', 'public'];
        
        foreach ($directories as $dir) {
            $sourcePath = storage_path('app/' . $dir);
            if (!File::exists($sourcePath)) {
                continue;
            }
            
            $destPath = $tempPath . '/files/' . $dir;
            File::makeDirectory($destPath, 0755, true, true);
            
            $files = File::allFiles($sourcePath);
            $dirStats = [
                'count' => 0,
                'size' => 0
            ];
            
            foreach ($files as $file) {
                $relativePath = str_replace($sourcePath . '/', '', $file->getPathname());
                $destFile = $destPath . '/' . $relativePath;
                
                File::makeDirectory(dirname($destFile), 0755, true, true);
                File::copy($file->getPathname(), $destFile);
                
                $dirStats['count']++;
                $dirStats['size'] += $file->getSize();
                $statistics['total_files']++;
                $statistics['total_size'] += $file->getSize();
            }
            
            if ($dirStats['count'] > 0) {
                $statistics['directories'][$dir] = $dirStats;
            }
        }
        
        return $statistics;
    }

    protected function getTablesToBackup(Backup $backup): array
    {
        $allTables = DB::select('SHOW TABLES');
        $tables = [];
        
        foreach ($allTables as $table) {
            $tableName = array_values((array)$table)[0];
            
            // Check if table should be included
            if (!empty($backup->included_tables)) {
                if (!in_array($tableName, $backup->included_tables)) {
                    continue;
                }
            }
            
            // Check if table should be excluded
            $excludedTables = array_merge(
                $this->defaultExcludedTables,
                $backup->excluded_tables ?? []
            );
            
            if (in_array($tableName, $excludedTables)) {
                continue;
            }
            
            $tables[] = $tableName;
        }
        
        return $tables;
    }

    protected function backupSchema(string $path): void
    {
        $tables = DB::select('SHOW TABLES');
        $schema = '';
        
        foreach ($tables as $table) {
            $tableName = array_values((array)$table)[0];
            
            // Get CREATE TABLE statement
            $createTable = DB::select("SHOW CREATE TABLE `{$tableName}`");
            if (!empty($createTable)) {
                $schema .= $createTable[0]->{'Create Table'} . ";\n\n";
            }
        }
        
        File::put($path, $schema);
    }

    protected function createZipArchive(Backup $backup, string $tempPath): string
    {
        $zipPath = storage_path('app/temp/backup_' . $backup->id . '.zip');
        
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('No se pudo crear el archivo ZIP');
        }
        
        // Add metadata
        $metadata = [
            'backup_id' => $backup->id,
            'created_at' => $backup->created_at->toIso8601String(),
            'tenant_id' => $backup->tenant_id,
            'app_version' => config('app.version', '1.0.0'),
            'backup_version' => '1.0'
        ];
        $zip->addFromString('metadata.json', json_encode($metadata, JSON_PRETTY_PRINT));
        
        // Add all files from temp directory
        $this->addDirectoryToZip($zip, $tempPath, '');
        
        $zip->close();
        
        return $zipPath;
    }

    protected function addDirectoryToZip(ZipArchive $zip, string $path, string $prefix = ''): void
    {
        $files = File::allFiles($path);
        
        foreach ($files as $file) {
            $relativePath = str_replace($path . '/', '', $file->getPathname());
            $zipPath = $prefix ? $prefix . '/' . $relativePath : $relativePath;
            $zip->addFile($file->getPathname(), $zipPath);
        }
    }

    protected function moveToStorage(Backup $backup, string $zipPath): string
    {
        $filename = sprintf(
            'backup_%s_%s.zip',
            $backup->tenant_id ?: 'system',
            Carbon::now()->format('Y-m-d_His')
        );
        
        $storagePath = $backup->tenant_id 
            ? 'backups/tenant_' . $backup->tenant_id . '/' . $filename
            : 'backups/system/' . $filename;
        
        Storage::disk($backup->disk)->put(
            $storagePath,
            File::get($zipPath)
        );
        
        return $storagePath;
    }

    public function restoreBackup(Backup $backup, array $options = []): BackupRestoreLog
    {
        $restoreLog = BackupRestoreLog::create([
            'backup_id' => $backup->id,
            'tenant_id' => $backup->tenant_id,
            'restored_by' => auth()->id(),
            'restore_type' => $options['type'] ?? 'full',
            'notes' => $options['notes'] ?? null
        ]);
        
        try {
            $restoreLog->start();
            
            // Download and extract backup
            $tempPath = $this->extractBackup($backup);
            
            $restoredTables = [];
            $restoredFiles = [];
            
            // Restore database
            if (in_array($options['type'], ['full', 'tables_only'])) {
                $restoredTables = $this->restoreDatabase($tempPath, $backup->tenant_id, $options);
            }
            
            // Restore files
            if (in_array($options['type'], ['full', 'files_only'])) {
                $restoredFiles = $this->restoreFiles($tempPath, $backup->tenant_id, $options);
            }
            
            // Clean up
            File::deleteDirectory($tempPath);
            
            $restoreLog->complete($restoredTables, $restoredFiles);
            
        } catch (\Exception $e) {
            Log::error('Restore failed', [
                'backup_id' => $backup->id,
                'error' => $e->getMessage()
            ]);
            
            if (isset($tempPath)) {
                File::deleteDirectory($tempPath);
            }
            
            $restoreLog->fail($e->getMessage());
            throw $e;
        }
        
        return $restoreLog;
    }

    protected function extractBackup(Backup $backup): string
    {
        $tempPath = storage_path('app/temp/restore_' . $backup->id);
        File::makeDirectory($tempPath, 0755, true, true);
        
        // Download backup file
        $zipPath = $tempPath . '/backup.zip';
        File::put($zipPath, $backup->getFile());
        
        // Extract ZIP
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \Exception('No se pudo abrir el archivo de respaldo');
        }
        
        $zip->extractTo($tempPath);
        $zip->close();
        
        File::delete($zipPath);
        
        return $tempPath;
    }

    protected function restoreDatabase(string $tempPath, ?int $tenantId, array $options): array
    {
        $restoredTables = [];
        $dbPath = $tempPath . '/database';
        
        if (!File::exists($dbPath)) {
            return $restoredTables;
        }
        
        $files = File::files($dbPath);
        
        DB::beginTransaction();
        
        try {
            foreach ($files as $file) {
                if ($file->getExtension() !== 'json') {
                    continue;
                }
                
                $tableName = $file->getFilenameWithoutExtension();
                
                // Skip if specific tables are requested
                if (!empty($options['tables']) && !in_array($tableName, $options['tables'])) {
                    continue;
                }
                
                $data = json_decode(File::get($file->getPathname()), true);
                
                if (empty($data)) {
                    continue;
                }
                
                // Clear existing data if requested
                if ($options['clear_existing'] ?? false) {
                    $query = DB::table($tableName);
                    if ($tenantId && Schema::hasColumn($tableName, 'tenant_id')) {
                        $query->where('tenant_id', $tenantId);
                    }
                    $query->delete();
                }
                
                // Insert data in chunks
                $chunks = array_chunk($data, 100);
                $recordCount = 0;
                
                foreach ($chunks as $chunk) {
                    DB::table($tableName)->insert($chunk);
                    $recordCount += count($chunk);
                }
                
                $restoredTables[$tableName] = $recordCount;
            }
            
            DB::commit();
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
        
        return $restoredTables;
    }

    protected function restoreFiles(string $tempPath, ?int $tenantId, array $options): array
    {
        $restoredFiles = [];
        $filesPath = $tempPath . '/files';
        
        if (!File::exists($filesPath)) {
            return $restoredFiles;
        }
        
        $directories = File::directories($filesPath);
        
        foreach ($directories as $dir) {
            $dirName = basename($dir);
            $destPath = storage_path('app/' . $dirName);
            
            // Clear existing if requested
            if ($options['clear_existing'] ?? false) {
                File::deleteDirectory($destPath);
            }
            
            // Copy files
            File::copyDirectory($dir, $destPath);
            
            $files = File::allFiles($dir);
            $restoredFiles[$dirName] = count($files);
        }
        
        return $restoredFiles;
    }

    public function processScheduledBackups(): array
    {
        $results = [
            'processed' => 0,
            'successful' => 0,
            'failed' => 0
        ];
        
        $schedules = BackupSchedule::getDueSchedules();
        
        foreach ($schedules as $schedule) {
            $results['processed']++;
            
            try {
                $backup = $schedule->createBackup();
                $this->createBackup($backup);
                
                $schedule->recordRun(true);
                $results['successful']++;
                
                // Send success notification
                if ($schedule->shouldNotify('success')) {
                    $this->sendNotification($schedule, $backup, true);
                }
                
            } catch (\Exception $e) {
                $schedule->recordRun(false);
                $results['failed']++;
                
                Log::error('Scheduled backup failed', [
                    'schedule_id' => $schedule->id,
                    'error' => $e->getMessage()
                ]);
                
                // Send failure notification
                if ($schedule->shouldNotify('failure')) {
                    $this->sendNotification($schedule, null, false, $e->getMessage());
                }
            }
        }
        
        return $results;
    }

    protected function sendNotification(BackupSchedule $schedule, ?Backup $backup, bool $success, string $error = null): void
    {
        $emails = $schedule->getNotificationEmails();
        
        if (empty($emails)) {
            return;
        }
        
        // This would integrate with the email notification system
        // For now, we'll just log it
        Log::info('Backup notification', [
            'schedule_id' => $schedule->id,
            'backup_id' => $backup?->id,
            'success' => $success,
            'emails' => $emails,
            'error' => $error
        ]);
    }
}