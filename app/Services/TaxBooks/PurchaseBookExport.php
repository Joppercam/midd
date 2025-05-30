<?php

namespace App\Services\TaxBooks;

use App\Models\PurchaseBook;
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

class PurchaseBookExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, WithColumnWidths, WithEvents
{
    protected $purchaseBook;

    public function __construct(PurchaseBook $purchaseBook)
    {
        $this->purchaseBook = $purchaseBook;
    }

    public function collection()
    {
        return $this->purchaseBook->entries()->orderBy('document_date')->get();
    }

    public function headings(): array
    {
        return [
            'Fecha',
            'Tipo Doc',
            'N° Documento',
            'RUT Proveedor',
            'Razón Social',
            'Descripción',
            'Exento',
            'Neto',
            'IVA',
            'Total',
            'Retención',
            'Otros Imp.',
        ];
    }

    public function map($entry): array
    {
        return [
            $entry->document_date->format('d/m/Y'),
            $entry->document_type_label,
            $entry->document_number,
            $entry->formatted_rut,
            $entry->supplier_name,
            $entry->description,
            number_format($entry->exempt_amount, 0, ',', '.'),
            number_format($entry->net_amount, 0, ',', '.'),
            number_format($entry->tax_amount, 0, ',', '.'),
            number_format($entry->total_amount, 0, ',', '.'),
            number_format($entry->withholding_amount, 0, ',', '.'),
            number_format($entry->other_taxes, 0, ',', '.'),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Header styles
        $sheet->getStyle('A1:L1')->applyFromArray([
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
        $sheet->getStyle("A1:L{$highestRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ]);

        // Number format for amounts
        $sheet->getStyle('G:L')->getNumberFormat()->setFormatCode('#,##0');

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,  // Fecha
            'B' => 20,  // Tipo Doc
            'C' => 15,  // N° Documento
            'D' => 15,  // RUT Proveedor
            'E' => 30,  // Razón Social
            'F' => 30,  // Descripción
            'G' => 12,  // Exento
            'H' => 12,  // Neto
            'I' => 12,  // IVA
            'J' => 12,  // Total
            'K' => 12,  // Retención
            'L' => 12,  // Otros Imp.
        ];
    }

    public function title(): string
    {
        return 'Libro de Compras';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                
                // Add totals row
                $totalRow = $highestRow + 2;
                $sheet->setCellValue("F{$totalRow}", 'TOTALES:');
                $sheet->setCellValue("G{$totalRow}", "=SUM(G2:G{$highestRow})");
                $sheet->setCellValue("H{$totalRow}", "=SUM(H2:H{$highestRow})");
                $sheet->setCellValue("I{$totalRow}", "=SUM(I2:I{$highestRow})");
                $sheet->setCellValue("J{$totalRow}", "=SUM(J2:J{$highestRow})");
                $sheet->setCellValue("K{$totalRow}", "=SUM(K2:K{$highestRow})");
                $sheet->setCellValue("L{$totalRow}", "=SUM(L2:L{$highestRow})");
                
                // Style totals row
                $sheet->getStyle("F{$totalRow}:L{$totalRow}")->applyFromArray([
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
                $sheet->mergeCells('A1:L1');
                $sheet->setCellValue('A1', $this->purchaseBook->tenant->name);
                $sheet->mergeCells('A2:L2');
                $sheet->setCellValue('A2', 'LIBRO DE COMPRAS - ' . strtoupper($this->purchaseBook->period_name));
                $sheet->mergeCells('A3:L3');
                $sheet->setCellValue('A3', 'RUT: ' . $this->purchaseBook->tenant->rut);
                
                // Style header information
                $sheet->getStyle('A1:A3')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
            },
        ];
    }
}