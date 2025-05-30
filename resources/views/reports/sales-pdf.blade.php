<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Ventas</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            margin: 0;
            color: #1a1a1a;
        }
        .period {
            text-align: center;
            color: #666;
            margin-bottom: 20px;
        }
        .summary {
            background-color: #f8f9fa;
            padding: 15px;
            margin-bottom: 30px;
            border-radius: 5px;
        }
        .summary-grid {
            display: flex;
            justify-content: space-between;
        }
        .summary-item {
            text-align: center;
        }
        .summary-label {
            font-size: 12px;
            color: #666;
        }
        .summary-value {
            font-size: 24px;
            font-weight: bold;
            color: #1a1a1a;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th {
            background-color: #f8f9fa;
            text-align: left;
            padding: 8px;
            font-size: 12px;
            text-transform: uppercase;
            color: #666;
        }
        td {
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        .text-right {
            text-align: right;
        }
        .section-title {
            font-size: 18px;
            font-weight: bold;
            margin: 20px 0 10px 0;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Reporte de Ventas</h1>
    </div>

    <div class="period">
        Período: {{ \Carbon\Carbon::parse($period['start'])->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($period['end'])->format('d/m/Y') }}
        ({{ $period['days'] }} días)
    </div>

    <div class="summary">
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-label">Total Ventas</div>
                <div class="summary-value">${{ number_format($summary->total_amount, 0, ',', '.') }}</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Documentos</div>
                <div class="summary-value">{{ $summary->total_documents }}</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Venta Promedio</div>
                <div class="summary-value">${{ number_format($summary->average_sale, 0, ',', '.') }}</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">IVA Total</div>
                <div class="summary-value">${{ number_format($summary->total_tax, 0, ',', '.') }}</div>
            </div>
        </div>
    </div>

    <div class="section-title">Top 10 Productos</div>
    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>SKU</th>
                <th class="text-right">Cantidad</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($top_products as $product)
            <tr>
                <td>{{ $product->name }}</td>
                <td>{{ $product->sku }}</td>
                <td class="text-right">{{ number_format($product->total_quantity, 0, ',', '.') }}</td>
                <td class="text-right">${{ number_format($product->total_revenue, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="section-title">Top 10 Clientes</div>
    <table>
        <thead>
            <tr>
                <th>Cliente</th>
                <th>RUT</th>
                <th class="text-right">Documentos</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($top_customers as $customer)
            <tr>
                <td>{{ $customer->name }}</td>
                <td>{{ $customer->rut }}</td>
                <td class="text-right">{{ $customer->document_count }}</td>
                <td class="text-right">${{ number_format($customer->total_amount, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Generado el {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>