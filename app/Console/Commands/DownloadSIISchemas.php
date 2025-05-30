<?php

namespace App\Console\Commands;

use App\Services\SII\XSDValidatorService;
use Illuminate\Console\Command;

class DownloadSIISchemas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sii:download-schemas';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download XSD schemas from SII for XML validation';

    /**
     * Execute the console command.
     */
    public function handle(XSDValidatorService $validator)
    {
        $this->info('Downloading SII XSD schemas...');
        
        // Check if schemas directory exists
        $schemaPath = storage_path('app/sii/schemas');
        if (!is_dir($schemaPath)) {
            mkdir($schemaPath, 0755, true);
            $this->info("Created schemas directory: {$schemaPath}");
        }

        // Download schemas
        $results = $validator->downloadSchemas();
        
        // Display results
        foreach ($results as $name => $result) {
            if ($result['success']) {
                $this->info("✓ {$name}: {$result['message']}");
            } else {
                $this->error("✗ {$name}: {$result['message']}");
            }
        }

        // Show summary
        $successful = collect($results)->where('success', true)->count();
        $failed = collect($results)->where('success', false)->count();
        
        $this->newLine();
        $this->info("Download complete: {$successful} successful, {$failed} failed");

        // List available schemas
        $this->newLine();
        $this->info('Available schemas:');
        $this->table(
            ['Name', 'Filename', 'Status', 'Size'],
            collect($validator->getAvailableSchemas())->map(function ($schema) {
                return [
                    $schema['name'],
                    $schema['filename'],
                    $schema['exists'] ? 'Available' : 'Missing',
                    $schema['exists'] ? $this->formatBytes($schema['size']) : '-',
                ];
            })
        );

        if ($failed > 0) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Format bytes to human readable format
     *
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }
}