<?php

namespace App\Services;

use App\Models\Webhook;
use App\Models\WebhookCall;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    public function dispatch(string $event, array $payload, int $tenantId = null): void
    {
        $webhooks = Webhook::where('active', true)
            ->when($tenantId, fn($query) => $query->where('tenant_id', $tenantId))
            ->get()
            ->filter(fn($webhook) => $webhook->subscribesToEvent($event));

        foreach ($webhooks as $webhook) {
            $this->createWebhookCall($webhook, $event, $payload);
        }
    }

    protected function createWebhookCall(Webhook $webhook, string $event, array $payload): WebhookCall
    {
        $fullPayload = [
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'data' => $payload,
        ];

        $call = WebhookCall::create([
            'tenant_id' => $webhook->tenant_id,
            'webhook_id' => $webhook->id,
            'event' => $event,
            'payload' => $fullPayload,
            'attempts' => 0,
        ]);

        // Dispatch to queue
        dispatch(function () use ($call) {
            $this->processWebhookCall($call);
        })->afterResponse();

        return $call;
    }

    public function processWebhookCall(WebhookCall $call): void
    {
        if (!$call->shouldRetry()) {
            return;
        }

        $webhook = $call->webhook;
        
        if (!$webhook->isActive()) {
            $call->markAsFailed('Webhook is inactive');
            return;
        }

        $call->incrementAttempts();

        try {
            $payloadJson = json_encode($call->payload);
            $signature = $webhook->generateSignature($payloadJson);
            
            $headers = array_merge(
                $webhook->headers ?? [],
                [
                    'Content-Type' => 'application/json',
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-Event' => $call->event,
                    'X-Webhook-Timestamp' => now()->timestamp,
                ]
            );

            $response = Http::withHeaders($headers)
                ->timeout($webhook->timeout ?? 30)
                ->post($webhook->url, $call->payload);

            $webhook->update([
                'last_called_at' => now(),
                'last_status' => $response->status(),
            ]);

            if ($response->successful()) {
                $call->markAsCompleted($response->status(), [
                    'body' => $response->json() ?? $response->body(),
                    'headers' => $response->headers(),
                ]);
                
                $webhook->resetFailures();
            } else {
                $this->handleFailedResponse($call, $webhook, $response->status(), $response->body());
            }
        } catch (\Exception $e) {
            $this->handleException($call, $webhook, $e);
        }
    }

    protected function handleFailedResponse(WebhookCall $call, Webhook $webhook, int $statusCode, string $responseBody): void
    {
        $error = "HTTP {$statusCode}: " . substr($responseBody, 0, 500);
        
        $webhook->update([
            'last_error' => $error,
        ]);

        if ($call->attempts >= ($webhook->max_retries ?? 3)) {
            $call->markAsFailed($error, $statusCode);
            $webhook->incrementFailure();
        } else {
            // Will retry
            $call->update([
                'status_code' => $statusCode,
                'error_message' => $error,
            ]);
        }
    }

    protected function handleException(WebhookCall $call, Webhook $webhook, \Exception $e): void
    {
        $error = "Exception: " . $e->getMessage();
        
        Log::error('Webhook call failed', [
            'webhook_id' => $webhook->id,
            'call_id' => $call->id,
            'error' => $error,
            'trace' => $e->getTraceAsString(),
        ]);

        $webhook->update([
            'last_error' => $error,
        ]);

        if ($call->attempts >= ($webhook->max_retries ?? 3)) {
            $call->markAsFailed($error);
            $webhook->incrementFailure();
        } else {
            // Will retry
            $call->update([
                'error_message' => $error,
            ]);
        }
    }

    public function retryFailedCalls(): int
    {
        $calls = WebhookCall::whereNull('completed_at')
            ->whereNull('failed_at')
            ->where(function ($query) {
                $query->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            })
            ->with('webhook')
            ->get();

        $processed = 0;
        
        foreach ($calls as $call) {
            if ($call->shouldRetry()) {
                $this->processWebhookCall($call);
                $processed++;
            }
        }

        return $processed;
    }

    public static function getAvailableEvents(): array
    {
        return [
            // Customers
            'customer.created' => 'Cliente creado',
            'customer.updated' => 'Cliente actualizado',
            'customer.deleted' => 'Cliente eliminado',
            
            // Products
            'product.created' => 'Producto creado',
            'product.updated' => 'Producto actualizado',
            'product.deleted' => 'Producto eliminado',
            'product.low_stock' => 'Stock bajo en producto',
            
            // Invoices
            'invoice.created' => 'Factura creada',
            'invoice.sent' => 'Factura enviada al SII',
            'invoice.accepted' => 'Factura aceptada por SII',
            'invoice.rejected' => 'Factura rechazada por SII',
            'invoice.cancelled' => 'Factura anulada',
            'invoice.paid' => 'Factura pagada',
            
            // Payments
            'payment.received' => 'Pago recibido',
            'payment.allocated' => 'Pago asignado',
            'payment.reversed' => 'Pago revertido',
            
            // Expenses
            'expense.created' => 'Gasto creado',
            'expense.approved' => 'Gasto aprobado',
            'expense.paid' => 'Gasto pagado',
            
            // Bank
            'bank.transaction_imported' => 'Transacción bancaria importada',
            'bank.transaction_matched' => 'Transacción bancaria conciliada',
            'bank.reconciliation_completed' => 'Conciliación bancaria completada',
        ];
    }
}