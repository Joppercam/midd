<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Customer;
use App\Models\TaxDocument;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $customers = Customer::where('tenant_id', $tenant->id)->get();
            
            if ($customers->isEmpty()) {
                continue;
            }

            // Crear algunos pagos para diferentes clientes
            $paymentMethods = ['cash', 'bank_transfer', 'check', 'credit_card'];
            $statuses = ['confirmed', 'pending'];

            for ($i = 1; $i <= 15; $i++) {
                $customer = $customers->random();
                $paymentDate = Carbon::now()->subDays(rand(1, 90));
                
                $payment = Payment::create([
                    'number' => Payment::generateNumber($tenant->id),
                    'tenant_id' => $tenant->id,
                    'customer_id' => $customer->id,
                    'payment_date' => $paymentDate,
                    'amount' => rand(50000, 2000000),
                    'payment_method' => $paymentMethods[array_rand($paymentMethods)],
                    'reference' => $this->generateReference(),
                    'bank' => rand(0, 1) ? $this->getRandomBank() : null,
                    'description' => 'Pago ' . $i . ' - ' . $customer->name,
                    'status' => $statuses[array_rand($statuses)],
                    'remaining_amount' => 0 // Se calculará después de las asignaciones
                ]);

                // Obtener facturas pendientes del cliente
                $unpaidDocuments = TaxDocument::where('tenant_id', $tenant->id)
                    ->where('customer_id', $customer->id)
                    ->whereIn('document_type', ['invoice', 'debit_note'])
                    ->where('balance', '>', 0)
                    ->where('issue_date', '<=', $paymentDate)
                    ->inRandomOrder()
                    ->limit(rand(1, 3))
                    ->get();

                $remainingPayment = $payment->amount;

                // Asignar el pago a facturas
                foreach ($unpaidDocuments as $document) {
                    if ($remainingPayment <= 0) {
                        break;
                    }

                    $allocationAmount = min($document->balance, $remainingPayment);
                    
                    if ($allocationAmount > 0) {
                        PaymentAllocation::create([
                            'payment_id' => $payment->id,
                            'tax_document_id' => $document->id,
                            'amount' => $allocationAmount,
                            'notes' => 'Asignación automática'
                        ]);

                        $remainingPayment -= $allocationAmount;
                    }
                }

                // Actualizar monto restante
                $payment->update(['remaining_amount' => $remainingPayment]);
            }

            $this->command->info("Pagos creados para tenant: {$tenant->name}");
        }
    }

    private function generateReference(): string
    {
        $types = ['TRANS', 'CHEQ', 'TARJ'];
        $type = $types[array_rand($types)];
        $number = str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
        
        return $type . '-' . $number;
    }

    private function getRandomBank(): string
    {
        $banks = [
            'Banco de Chile',
            'Banco Santander',
            'BancoEstado',
            'Banco de Crédito e Inversiones',
            'Banco Falabella',
            'Banco Security',
            'Banco Ripley',
            'Banco Itaú',
            'Banco Consorcio',
            'Scotiabank Chile'
        ];

        return $banks[array_rand($banks)];
    }
}