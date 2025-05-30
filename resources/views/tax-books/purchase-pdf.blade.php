<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Libro de Compras - {{ $book->period_name }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #000;
        }
        
        .container {
            padding: 10px;
        }
        
        .header {
            margin-bottom: 20px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .header h2 {
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .company-info {
            margin-bottom: 20px;
        }
        
        .company-info p {
            margin-bottom: 3px;
        }
        
        .period-info {
            margin-bottom: 20px;
            font-weight: bold;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        table th {
            background-color: #f0f0f0;
            padding: 5px 3px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #000;
            font-size: 9px;
        }
        
        table td {
            padding: 3px;
            border: 1px solid #000;
            font-size: 9px;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .totals-row {
            font-weight: bold;
            background-color: #f0f0f0;
        }
        
        .summary-table {
            margin-top: 30px;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #000;
        }
        
        .footer p {
            margin-bottom: 5px;
        }
        
        .page-break {
            page-break-after: always;
        }
        
        @page {
            margin: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>LIBRO DE COMPRAS</h1>
            <h2>{{ strtoupper($book->period_name) }}</h2>
        </div>
        
        <div class="company-info">
            <p><strong>Razón Social:</strong> {{ $tenant->name }}</p>
            <p><strong>RUT:</strong> {{ $tenant->rut }}</p>
            @if($tenant->address)
                <p><strong>Dirección:</strong> {{ $tenant->address }}</p>
            @endif
            @if($tenant->economic_activity)
                <p><strong>Giro:</strong> {{ $tenant->economic_activity }}</p>
            @endif
        </div>
        
        <table>
            <thead>
                <tr>
                    <th style="width: 8%">Fecha</th>
                    <th style="width: 8%">Tipo Doc</th>
                    <th style="width: 8%">N° Doc</th>
                    <th style="width: 10%">RUT</th>
                    <th style="width: 18%">Razón Social</th>
                    <th style="width: 14%">Descripción</th>
                    <th style="width: 7%" class="text-right">Exento</th>
                    <th style="width: 8%" class="text-right">Neto</th>
                    <th style="width: 7%" class="text-right">IVA</th>
                    <th style="width: 8%" class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($book->entries as $entry)
                <tr>
                    <td>{{ $entry->document_date->format('d/m/Y') }}</td>
                    <td>{{ $entry->document_type_label }}</td>
                    <td>{{ $entry->document_number }}</td>
                    <td>{{ $entry->formatted_rut }}</td>
                    <td>{{ $entry->supplier_name }}</td>
                    <td>{{ Str::limit($entry->description, 30) }}</td>
                    <td class="text-right">{{ number_format($entry->exempt_amount, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($entry->net_amount, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($entry->tax_amount, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($entry->total_amount, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="totals-row">
                    <td colspan="6">TOTALES</td>
                    <td class="text-right">{{ number_format($book->total_exempt, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($book->total_net, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($book->total_tax, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($book->total_amount, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
        
        @if($book->summary && count($book->summary) > 0)
        <div class="summary-table">
            <h3>RESUMEN POR TIPO DE DOCUMENTO</h3>
            <table>
                <thead>
                    <tr>
                        <th>Tipo de Documento</th>
                        <th class="text-right">Cantidad</th>
                        <th class="text-right">Exento</th>
                        <th class="text-right">Neto</th>
                        <th class="text-right">IVA</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($book->summary as $item)
                    <tr>
                        <td>{{ \App\Models\PurchaseBookEntry::find(1)->getDocumentTypeLabelAttribute($item['document_type']) ?? $item['document_type'] }}</td>
                        <td class="text-right">{{ $item['count'] }}</td>
                        <td class="text-right">{{ number_format($item['exempt'], 0, ',', '.') }}</td>
                        <td class="text-right">{{ number_format($item['net'], 0, ',', '.') }}</td>
                        <td class="text-right">{{ number_format($item['tax'], 0, ',', '.') }}</td>
                        <td class="text-right">{{ number_format($item['total'], 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
        
        <div class="footer">
            <p><strong>Total de documentos:</strong> {{ $book->total_documents }}</p>
            <p><strong>IVA Crédito Fiscal del período:</strong> ${{ number_format($book->total_tax, 0, ',', '.') }}</p>
            @if($book->total_withholding > 0)
                <p><strong>Total Retenciones:</strong> ${{ number_format($book->total_withholding, 0, ',', '.') }}</p>
            @endif
            @if($book->total_other_taxes > 0)
                <p><strong>Otros Impuestos:</strong> ${{ number_format($book->total_other_taxes, 0, ',', '.') }}</p>
            @endif
            <p><strong>Fecha de generación:</strong> {{ $book->generated_at->format('d/m/Y H:i') }}</p>
            <p><strong>Estado del libro:</strong> {{ $book->status_label }}</p>
        </div>
    </div>
</body>
</html>