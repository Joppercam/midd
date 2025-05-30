<?php

namespace App\Console\Commands;

use App\Services\WebhookService;
use Illuminate\Console\Command;

class ProcessWebhooks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webhooks:process {--limit=100 : Maximum number of webhook calls to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending webhook calls and retry failed ones';

    protected $webhookService;

    public function __construct(WebhookService $webhookService)
    {
        parent::__construct();
        $this->webhookService = $webhookService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Processing pending webhook calls...');
        
        $startTime = microtime(true);
        $processed = $this->webhookService->retryFailedCalls();
        $duration = round(microtime(true) - $startTime, 2);
        
        $this->info("Processed {$processed} webhook calls in {$duration} seconds");
        
        return Command::SUCCESS;
    }
}