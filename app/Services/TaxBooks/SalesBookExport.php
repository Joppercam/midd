<?php

namespace App\Services\TaxBooks;

use App\Models\SalesBook;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class SalesBookExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, WithColumnWidths, WithEvents
{
    protected $salesBook;

    public function __construct(SalesBook $salesBook)
    {
        $this->salesBook = $salesBook;
    }

    public function collection()
    {
        return $this->salesBook->entries()->orderBy('document_date')->get();
    }

    public function headings(): array
    {
        return [
            'Fecha',
            'Tipo Doc',
            'N° Documento',
            'RUT Cliente',
            'Razón Social',
            'Exento',
            'Neto',
            'IVA',
            'Total',
            'Estado',
        ];
    }

    public function map($entry): array
    {
        return [
            $entry->document_date->format('d/m/Y'),
            $entry->document_type_label,
            $entry->document_number,
            $entry->formatted_rut,
            $entry->customer_name,
            number_format($entry->exempt_amount, 0, ',', '.'),
            number_format($entry->net_amount, 0, ',', '.'),
            number_format($entry->tax_amount, 0, ',', '.'),
            number_format($entry->total_amount, 0, ',', '.'),
            $entry->status_label,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Header styles
        $sheet->getStyle('A1:J1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2563EB'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        // Add borders to all cells
        $highestRow = $sheet->getHighestRow();
        $sheet->getStyle("A1:J{$highestRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ]);

        // Number format for amounts
        $sheet->getStyle('F:I')->getNumberFormat()->setFormatCode('#,##0');

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,  // Fecha
            'B' => 20,  // Tipo Doc
            'C' => 15,  // N° Documento
            'D' => 15,  // RUT Cliente
            'E' => 35,  // Razón Social
            'F' => 12,  // Exento
            'G' => 12,  // Neto
            'H' => 12,  // IVA
            'I' => 12,  // Total
            'J' => 12,  // Estado
        ];
    }

    public function title(): string
    {
        return 'Libro de Ventas';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                
                // Add totals row
                $totalRow = $highestRow + 2;
                $sheet->setCellValue("E{$totalRow}", 'TOTALES:');
                $sheet->setCellValue("F{$totalRow}", "=SUM(F2:F{$highestRow})");
                $sheet->setCellValue("G{$totalRow}", "=SUM(G2:G{$highestRow})");
                $sheet->setCellValue("H{$totalRow}", "=SUM(H2:H{$highestRow})");
                $sheet->setCellValue("I{$totalRow}", "=SUM(I2:I{$highestRow})");
                
                // Style totals row
                $sheet->getStyle("E{$totalRow}:I{$totalRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F3F4F6'],
                    ],
                    'borders' => [
                        'top' => ['borderStyle' => Border::BORDER_DOUBLE],
                    ],
                ]);

                // Add header information
                $sheet->insertNewRowBefore(1, 4);
                $sheet->mergeCells('A1:J1');
                $sheet->setCellValue('A1', $this->salesBook->tenant->name);
                $sheet->mergeCells('A2:J2');
                $sheet->setCellValue('A2', 'LIBRO DE VENTAS - ' . strtoupper($this->salesBook->period_name));
                $sheet->mergeCells('A3:J3');
                $sheet->setCellValue('A3', 'RUT: ' . $this->salesBook->tenant->rut);
                
                // Style header information
                $sheet->getStyle('A1:A3')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
            },
        ];
    }
}