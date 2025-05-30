<?php

namespace App\Exports;

use App\Models\BankReconciliation;
use App\Services\BankReconciliationService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class BankReconciliationExport implements WithMultipleSheets
{
    protected BankReconciliation $reconciliation;
    protected array $report;
    protected BankReconciliationService $service;

    public function __construct(BankReconciliation $reconciliation)
    {
        $this->reconciliation = $reconciliation;
        $this->service = app(BankReconciliationService::class);
        $this->report = $this->service->generateReconciliationReport($reconciliation);
    }

    public function sheets(): array
    {
        return [
            new ResumenSheet($this->report),
            new TransaccionesConciliadasSheet($this->report),
            new TransaccionesPendientesSheet($this->report),
            new AjustesSheet($this->report)
        ];
    }
}

class ResumenSheet implements FromArray, WithTitle, WithStyles
{
    protected array $report;

    public function __construct(array $report)
    {
        $this->report = $report;
    }

    public function array(): array
    {
        return [
            ['REPORTE DE CONCILIACIÓN BANCARIA'],
            [],
            ['Información General'],
            ['Cuenta Bancaria:', $this->report['bank_account']['name']],
            ['Banco:', $this->report['bank_account']['bank_name']],
            ['Número de Cuenta:', $this->report['bank_account']['account_number'] ?? 'N/A'],
            ['Período:', \Carbon\Carbon::parse($this->report['reconciliation']['period']['start'])->format('d/m/Y') . ' al ' . \Carbon\Carbon::parse($this->report['reconciliation']['period']['end'])->format('d/m/Y')],
            ['Fecha de Conciliación:', \Carbon\Carbon::parse($this->report['reconciliation']['date'])->format('d/m/Y')],
            ['Estado:', $this->report['reconciliation']['status'] === 'completed' ? 'Completada' : 'Borrador'],
            [],
            ['Resumen de Balances'],
            ['Saldo Inicial:', '$' . number_format($this->report['balances']['opening_balance'], 0, ',', '.')],
            ['Saldo Estado de Cuenta:', '$' . number_format($this->report['balances']['closing_balance'], 0, ',', '.')],
            ['Saldo Sistema:', '$' . number_format($this->report['balances']['system_balance'], 0, ',', '.')],
            ['Diferencia:', '$' . number_format($this->report['balances']['difference'], 0, ',', '.')],
            ['Total Ajustes:', '$' . number_format($this->report['balances']['total_adjustments'], 0, ',', '.')],
            ['Diferencia Final:', '$' . number_format($this->report['balances']['final_difference'], 0, ',', '.')],
            [],
            ['Estadísticas de Transacciones'],
            ['Total Transacciones:', $this->report['summary']['total_transactions']],
            ['Transacciones Conciliadas:', $this->report['summary']['matched_transactions']],
            ['Transacciones Pendientes:', $this->report['summary']['unmatched_transactions']],
            ['Transacciones Ignoradas:', $this->report['summary']['ignored_transactions']],
            [],
            ['Total Depósitos:', '$' . number_format($this->report['summary']['total_deposits'], 0, ',', '.'), '(' . $this->report['summary']['deposit_count'] . ' transacciones)'],
            ['Total Retiros:', '$' . number_format($this->report['summary']['total_withdrawals'], 0, ',', '.'), '(' . $this->report['summary']['withdrawal_count'] . ' transacciones)'],
        ];
    }

    public function title(): string
    {
        return 'Resumen';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 16], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]],
            3 => ['font' => ['bold' => true, 'size' => 14]],
            11 => ['font' => ['bold' => true, 'size' => 14]],
            19 => ['font' => ['bold' => true, 'size' => 14]],
            'A:A' => ['font' => ['bold' => true]],
            'B:C' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]],
        ];
    }
}

class TransaccionesConciliadasSheet implements FromArray, WithHeadings, WithTitle, WithStyles
{
    protected array $report;

    public function __construct(array $report)
    {
        $this->report = $report;
    }

    public function headings(): array
    {
        return ['Fecha', 'Descripción', 'Monto', 'Tipo Conciliación', 'Conciliado con'];
    }

    public function array(): array
    {
        $data = [];
        foreach ($this->report['matched_transactions'] as $transaction) {
            $data[] = [
                \Carbon\Carbon::parse($transaction['date'])->format('d/m/Y'),
                $transaction['description'],
                '$' . number_format($transaction['amount'], 0, ',', '.'),
                $transaction['match_type'] ?? '',
                $transaction['matched_with'] ? $transaction['matched_with']['type'] . ': ' . $transaction['matched_with']['reference'] : ''
            ];
        }
        return $data;
    }

    public function title(): string
    {
        return 'Transacciones Conciliadas';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']]],
        ];
    }
}

class TransaccionesPendientesSheet implements FromArray, WithHeadings, WithTitle, WithStyles
{
    protected array $report;

    public function __construct(array $report)
    {
        $this->report = $report;
    }

    public function headings(): array
    {
        return ['Fecha', 'Descripción', 'Monto', 'Referencia'];
    }

    public function array(): array
    {
        $data = [];
        foreach ($this->report['unmatched_transactions'] as $transaction) {
            $data[] = [
                \Carbon\Carbon::parse($transaction['date'])->format('d/m/Y'),
                $transaction['description'],
                '$' . number_format($transaction['amount'], 0, ',', '.'),
                $transaction['reference'] ?? '-'
            ];
        }
        return $data;
    }

    public function title(): string
    {
        return 'Transacciones Pendientes';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']]],
        ];
    }
}

class AjustesSheet implements FromArray, WithHeadings, WithTitle, WithStyles
{
    protected array $report;

    public function __construct(array $report)
    {
        $this->report = $report;
    }

    public function headings(): array
    {
        return ['Descripción', 'Monto'];
    }

    public function array(): array
    {
        $data = [];
        if (!empty($this->report['adjustments'])) {
            foreach ($this->report['adjustments'] as $adjustment) {
                $data[] = [
                    $adjustment['description'],
                    '$' . number_format($adjustment['amount'], 0, ',', '.')
                ];
            }
        }
        return $data;
    }

    public function title(): string
    {
        return 'Ajustes';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']]],
        ];
    }
}