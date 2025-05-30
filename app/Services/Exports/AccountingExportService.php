<?php

namespace App\Services\Exports;

use App\Models\TaxDocument;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class AccountingExportService
{
    public function exportToContpaq(int $year, int $month): string
    {
        $tenant = app('currentTenant');
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        
        $data = $this->getAccountingData($startDate, $endDate);
        
        $content = $this->formatContpaqData($data, $tenant);
        
        $filename = "contpaq_{$tenant->id}_{$year}_{$month}.txt";
        Storage::disk('local')->put("exports/accounting/{$filename}", $content);
        
        return storage_path("app/exports/accounting/{$filename}");
    }

    public function exportToMonica(int $year, int $month): string
    {
        $tenant = app('currentTenant');
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        
        $data = $this->getAccountingData($startDate, $endDate);
        
        $content = $this->formatMonicaData($data, $tenant);
        
        $filename = "monica_{$tenant->id}_{$year}_{$month}.csv";
        Storage::disk('local')->put("exports/accounting/{$filename}", $content);
        
        return storage_path("app/exports/accounting/{$filename}");
    }

    public function exportToTango(int $year, int $month): string
    {
        $tenant = app('currentTenant');
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        
        $data = $this->getAccountingData($startDate, $endDate);
        
        $content = $this->formatTangoData($data, $tenant);
        
        $filename = "tango_{$tenant->id}_{$year}_{$month}.txt";
        Storage::disk('local')->put("exports/accounting/{$filename}", $content);
        
        return storage_path("app/exports/accounting/{$filename}");
    }

    public function exportSIIFormat(int $year, int $month): array
    {
        $tenant = app('currentTenant');
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        
        $sales = $this->getSalesForSII($startDate, $endDate);
        $purchases = $this->getPurchasesForSII($startDate, $endDate);
        
        return [
            'sales' => $this->formatSalesForSII($sales, $tenant, $year, $month),
            'purchases' => $this->formatPurchasesForSII($purchases, $tenant, $year, $month)
        ];
    }

    protected function getAccountingData(Carbon $startDate, Carbon $endDate): array
    {
        $tenant = app('currentTenant');
        
        // Ventas (facturas emitidas)
        $sales = TaxDocument::where('tenant_id', $tenant->id)
            ->where('type', 'invoice')
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('status', ['issued', 'paid'])
            ->with(['customer', 'items.product'])
            ->get();
        
        // Compras (gastos)
        $purchases = Expense::where('tenant_id', $tenant->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('status', ['approved', 'paid'])
            ->with('supplier')
            ->get();
        
        // Pagos recibidos
        $payments = Payment::where('tenant_id', $tenant->id)
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->where('status', 'completed')
            ->with('customer')
            ->get();
        
        return [
            'sales' => $sales,
            'purchases' => $purchases,
            'payments' => $payments,
            'period' => [
                'start' => $startDate,
                'end' => $endDate
            ]
        ];
    }

    protected function formatContpaqData(array $data, Tenant $tenant): string
    {
        $lines = [];
        
        // Header
        $lines[] = sprintf(
            "EMPRESA=%s|RUT=%s|PERIODO=%s",
            $tenant->name,
            $tenant->rut,
            $data['period']['start']->format('Y-m')
        );
        
        // Ventas
        foreach ($data['sales'] as $sale) {
            $lines[] = sprintf(
                "VENTA|%s|%s|%s|%s|%.2f|%.2f|%.2f|%s",
                $sale->date->format('d/m/Y'),
                $sale->number,
                $sale->customer->rut ?? '66666666-6',
                $sale->customer->name ?? 'Consumidor Final',
                $sale->subtotal,
                $sale->tax_amount,
                $sale->total_amount,
                $sale->type === 'credit_note' ? 'NC' : 'FA'
            );
        }
        
        // Compras
        foreach ($data['purchases'] as $purchase) {
            $lines[] = sprintf(
                "COMPRA|%s|%s|%s|%s|%.2f|%.2f|%.2f|%s",
                $purchase->date->format('d/m/Y'),
                $purchase->document_number,
                $purchase->supplier->rut,
                $purchase->supplier->name,
                $purchase->net_amount,
                $purchase->tax_amount,
                $purchase->total_amount,
                $purchase->document_type === 'credit_note' ? 'NC' : 'FA'
            );
        }
        
        return implode("\n", $lines);
    }

    protected function formatMonicaData(array $data, Tenant $tenant): string
    {
        $csv = [];
        
        // Header CSV
        $csv[] = 'Tipo,Fecha,Documento,RUT,Razon Social,Neto,IVA,Total,Tipo Doc';
        
        // Ventas
        foreach ($data['sales'] as $sale) {
            $csv[] = sprintf(
                'VENTA,%s,%s,%s,"%s",%.2f,%.2f,%.2f,%s',
                $sale->date->format('d/m/Y'),
                $sale->number,
                $sale->customer->rut ?? '66666666-6',
                str_replace('"', '""', $sale->customer->name ?? 'Consumidor Final'),
                $sale->subtotal,
                $sale->tax_amount,
                $sale->total_amount,
                $sale->type === 'credit_note' ? 'NC' : 'FA'
            );
        }
        
        // Compras
        foreach ($data['purchases'] as $purchase) {
            $csv[] = sprintf(
                'COMPRA,%s,%s,%s,"%s",%.2f,%.2f,%.2f,%s',
                $purchase->date->format('d/m/Y'),
                $purchase->document_number,
                $purchase->supplier->rut,
                str_replace('"', '""', $purchase->supplier->name),
                $purchase->net_amount,
                $purchase->tax_amount,
                $purchase->total_amount,
                $purchase->document_type === 'credit_note' ? 'NC' : 'FA'
            );
        }
        
        return implode("\n", $csv);
    }

    protected function formatTangoData(array $data, Tenant $tenant): string
    {
        $lines = [];
        
        // Header Tango
        $lines[] = sprintf("EMP|%s|%s", $tenant->rut, $tenant->name);
        $lines[] = sprintf("PER|%s", $data['period']['start']->format('Ym'));
        
        // Ventas con formato Tango
        foreach ($data['sales'] as $sale) {
            $accountCode = $sale->type === 'credit_note' ? '11010002' : '11010001'; // Cuentas por cobrar
            
            $lines[] = sprintf(
                "ASI|%s|%s|%s|%s|D|%.2f|%s",
                $sale->date->format('dmY'),
                $accountCode,
                $sale->number,
                'Venta a ' . ($sale->customer->name ?? 'Consumidor Final'),
                $sale->total_amount,
                $sale->customer->rut ?? '66666666-6'
            );
            
            // Contrapartida en ventas
            $lines[] = sprintf(
                "ASI|%s|41010001|%s|%s|C|%.2f|%s",
                $sale->date->format('dmY'),
                $sale->number,
                'Venta a ' . ($sale->customer->name ?? 'Consumidor Final'),
                $sale->subtotal,
                $sale->customer->rut ?? '66666666-6'
            );
            
            // IVA débito fiscal
            if ($sale->tax_amount > 0) {
                $lines[] = sprintf(
                    "ASI|%s|21040001|%s|%s|C|%.2f|%s",
                    $sale->date->format('dmY'),
                    $sale->number,
                    'IVA Débito Fiscal',
                    $sale->tax_amount,
                    $sale->customer->rut ?? '66666666-6'
                );
            }
        }
        
        // Compras con formato Tango
        foreach ($data['purchases'] as $purchase) {
            $accountCode = $this->getExpenseAccountCode($purchase->category);
            
            $lines[] = sprintf(
                "ASI|%s|%s|%s|%s|D|%.2f|%s",
                $purchase->date->format('dmY'),
                $accountCode,
                $purchase->document_number,
                'Compra a ' . $purchase->supplier->name,
                $purchase->net_amount,
                $purchase->supplier->rut
            );
            
            // IVA crédito fiscal
            if ($purchase->tax_amount > 0) {
                $lines[] = sprintf(
                    "ASI|%s|11050001|%s|%s|D|%.2f|%s",
                    $purchase->date->format('dmY'),
                    $purchase->document_number,
                    'IVA Crédito Fiscal',
                    $purchase->tax_amount,
                    $purchase->supplier->rut
                );
            }
            
            // Contrapartida en cuentas por pagar
            $lines[] = sprintf(
                "ASI|%s|21010001|%s|%s|C|%.2f|%s",
                $purchase->date->format('dmY'),
                $purchase->document_number,
                'Compra a ' . $purchase->supplier->name,
                $purchase->total_amount,
                $purchase->supplier->rut
            );
        }
        
        return implode("\n", $lines);
    }

    protected function getSalesForSII(Carbon $startDate, Carbon $endDate): Collection
    {
        $tenant = app('currentTenant');
        
        return TaxDocument::where('tenant_id', $tenant->id)
            ->whereIn('type', ['invoice', 'credit_note', 'debit_note', 'exempt_invoice'])
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('status', ['issued', 'paid'])
            ->with(['customer'])
            ->orderBy('date')
            ->orderBy('number')
            ->get();
    }

    protected function getPurchasesForSII(Carbon $startDate, Carbon $endDate): Collection
    {
        $tenant = app('currentTenant');
        
        return Expense::where('tenant_id', $tenant->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('status', ['approved', 'paid'])
            ->with('supplier')
            ->orderBy('date')
            ->orderBy('document_number')
            ->get();
    }

    protected function formatSalesForSII(Collection $sales, Tenant $tenant, int $year, int $month): array
    {
        $data = [
            'RutEmisorLibro' => $tenant->rut,
            'PeriodoTributario' => sprintf('%04d-%02d', $year, $month),
            'TipoOperacion' => 'VENTA',
            'TipoLibro' => 'ESPECIAL',
            'TipoEnvio' => 'TOTAL',
            'FolioNotificacion' => '',
            'ResumenPeriodo' => [
                'TotalesServicio' => [],
                'TotOtrosImp' => [],
                'FolioNotificacion' => ''
            ],
            'Detalle' => []
        ];
        
        $totals = [
            'TpoDoc' => [],
            'MntExe' => 0,
            'MntNeto' => 0,
            'MntIVA' => 0,
            'TasaIVA' => 19,
            'MntTotal' => 0
        ];
        
        foreach ($sales as $sale) {
            $docType = $this->getSIIDocumentType($sale->type);
            
            $detail = [
                'TpoDoc' => $docType,
                'NroDoc' => (int)$sale->number,
                'TasaImp' => $sale->tax_amount > 0 ? 19 : 0,
                'FchDoc' => $sale->date->format('Y-m-d'),
                'RUTDoc' => $sale->customer->rut ?? '66666666-6',
                'RznSoc' => $sale->customer->name ?? 'Consumidor Final',
                'MntExe' => $sale->tax_amount > 0 ? 0 : (int)$sale->total_amount,
                'MntNeto' => (int)$sale->subtotal,
                'MntIVA' => (int)$sale->tax_amount,
                'MntTotal' => (int)$sale->total_amount
            ];
            
            $data['Detalle'][] = $detail;
            
            // Acumular totales
            if (!isset($totals['TpoDoc'][$docType])) {
                $totals['TpoDoc'][$docType] = [
                    'TpoDoc' => $docType,
                    'TotDoc' => 0,
                    'TotMntExe' => 0,
                    'TotMntNeto' => 0,
                    'TotMntIVA' => 0,
                    'TotMntTotal' => 0
                ];
            }
            
            $totals['TpoDoc'][$docType]['TotDoc']++;
            $totals['TpoDoc'][$docType]['TotMntExe'] += $detail['MntExe'];
            $totals['TpoDoc'][$docType]['TotMntNeto'] += $detail['MntNeto'];
            $totals['TpoDoc'][$docType]['TotMntIVA'] += $detail['MntIVA'];
            $totals['TpoDoc'][$docType]['TotMntTotal'] += $detail['MntTotal'];
            
            $totals['MntExe'] += $detail['MntExe'];
            $totals['MntNeto'] += $detail['MntNeto'];
            $totals['MntIVA'] += $detail['MntIVA'];
            $totals['MntTotal'] += $detail['MntTotal'];
        }
        
        $data['ResumenPeriodo'] = [
            'TotalesPeriodo' => [
                'TpoDoc' => array_values($totals['TpoDoc']),
                'TotMntExe' => $totals['MntExe'],
                'TotMntNeto' => $totals['MntNeto'],
                'TotMntIVA' => $totals['MntIVA'],
                'TotMntTotal' => $totals['MntTotal']
            ]
        ];
        
        return $data;
    }

    protected function formatPurchasesForSII(Collection $purchases, Tenant $tenant, int $year, int $month): array
    {
        $data = [
            'RutEmisorLibro' => $tenant->rut,
            'PeriodoTributario' => sprintf('%04d-%02d', $year, $month),
            'TipoOperacion' => 'COMPRA',
            'TipoLibro' => 'ESPECIAL',
            'TipoEnvio' => 'TOTAL',
            'ResumenPeriodo' => [],
            'Detalle' => []
        ];
        
        $totals = [
            'TpoDoc' => [],
            'MntExe' => 0,
            'MntNeto' => 0,
            'MntIVA' => 0,
            'MntTotal' => 0
        ];
        
        foreach ($purchases as $purchase) {
            $docType = $this->getSIIDocumentType($purchase->document_type);
            
            $detail = [
                'TpoDoc' => $docType,
                'NroDoc' => (int)$purchase->document_number,
                'TasaImp' => $purchase->tax_amount > 0 ? 19 : 0,
                'FchDoc' => $purchase->date->format('Y-m-d'),
                'RUTDoc' => $purchase->supplier->rut,
                'RznSoc' => $purchase->supplier->name,
                'MntExe' => $purchase->tax_amount > 0 ? 0 : (int)$purchase->total_amount,
                'MntNeto' => (int)$purchase->net_amount,
                'MntIVA' => (int)$purchase->tax_amount,
                'MntTotal' => (int)$purchase->total_amount,
                'IVAUsoComun' => 0,
                'IVANoRec' => []
            ];
            
            $data['Detalle'][] = $detail;
            
            // Acumular totales
            if (!isset($totals['TpoDoc'][$docType])) {
                $totals['TpoDoc'][$docType] = [
                    'TpoDoc' => $docType,
                    'TotDoc' => 0,
                    'TotMntExe' => 0,
                    'TotMntNeto' => 0,
                    'TotMntIVA' => 0,
                    'TotMntTotal' => 0
                ];
            }
            
            $totals['TpoDoc'][$docType]['TotDoc']++;
            $totals['TpoDoc'][$docType]['TotMntExe'] += $detail['MntExe'];
            $totals['TpoDoc'][$docType]['TotMntNeto'] += $detail['MntNeto'];
            $totals['TpoDoc'][$docType]['TotMntIVA'] += $detail['MntIVA'];
            $totals['TpoDoc'][$docType]['TotMntTotal'] += $detail['MntTotal'];
            
            $totals['MntExe'] += $detail['MntExe'];
            $totals['MntNeto'] += $detail['MntNeto'];
            $totals['MntIVA'] += $detail['MntIVA'];
            $totals['MntTotal'] += $detail['MntTotal'];
        }
        
        $data['ResumenPeriodo'] = [
            'TotalesPeriodo' => [
                'TpoDoc' => array_values($totals['TpoDoc']),
                'TotMntExe' => $totals['MntExe'],
                'TotMntNeto' => $totals['MntNeto'],
                'TotMntIVA' => $totals['MntIVA'],
                'TotMntTotal' => $totals['MntTotal']
            ]
        ];
        
        return $data;
    }

    protected function getSIIDocumentType(string $type): int
    {
        return match($type) {
            'invoice' => 33,          // Factura Electrónica
            'credit_note' => 61,      // Nota de Crédito Electrónica
            'debit_note' => 56,       // Nota de Débito Electrónica
            'exempt_invoice' => 34,   // Factura Exenta Electrónica
            'receipt' => 39,          // Boleta Electrónica
            'purchase_invoice' => 30, // Factura de Compra
            default => 33
        };
    }

    protected function getExpenseAccountCode(string $category): string
    {
        return match($category) {
            'office_supplies' => '51010001',  // Materiales de oficina
            'travel' => '51020001',           // Gastos de viaje
            'utilities' => '51030001',        // Servicios básicos
            'rent' => '51040001',             // Arriendos
            'professional_services' => '51050001', // Servicios profesionales
            'advertising' => '51060001',      // Publicidad
            'maintenance' => '51070001',      // Mantención
            'insurance' => '51080001',        // Seguros
            'taxes' => '51090001',            // Impuestos
            'interest' => '51100001',         // Intereses
            default => '51990001'             // Otros gastos
        };
    }

    public function getAvailableFormats(): array
    {
        return [
            'contpaq' => [
                'name' => 'CONTPAq',
                'description' => 'Formato para importar en CONTPAq',
                'extension' => 'txt'
            ],
            'monica' => [
                'name' => 'Mónica',
                'description' => 'Formato CSV para sistema Mónica',
                'extension' => 'csv'
            ],
            'tango' => [
                'name' => 'Tango Gestión',
                'description' => 'Formato para importar en Tango',
                'extension' => 'txt'
            ],
            'sii' => [
                'name' => 'SII Chile',
                'description' => 'Formato oficial para envío al SII',
                'extension' => 'xml'
            ]
        ];
    }
}