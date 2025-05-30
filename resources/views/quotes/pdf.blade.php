<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotización #{{ $quote->quote_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
        }
        
        .container {
            padding: 20px;
        }
        
        .header {
            margin-bottom: 30px;
        }
        
        .header-top {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        
        .logo-section {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        
        .company-info {
            display: table-cell;
            width: 50%;
            text-align: right;
            vertical-align: top;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 5px;
        }
        
        .quote-title {
            background-color: #f3f4f6;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .quote-number {
            font-size: 18px;
            font-weight: bold;
            color: #1f2937;
        }
        
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }
        
        .info-column {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 20px;
        }
        
        .info-column:last-child {
            padding-right: 0;
        }
        
        .info-block {
            margin-bottom: 20px;
        }
        
        .info-title {
            font-weight: bold;
            color: #6b7280;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .info-content p {
            margin-bottom: 3px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .items-table th {
            background-color: #f3f4f6;
            padding: 10px;
            text-align: left;
            font-weight: bold;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .items-table .text-right {
            text-align: right;
        }
        
        .totals {
            margin-left: auto;
            width: 300px;
            margin-bottom: 30px;
        }
        
        .totals-row {
            display: table;
            width: 100%;
            padding: 5px 0;
        }
        
        .totals-label {
            display: table-cell;
            text-align: right;
            padding-right: 20px;
        }
        
        .totals-value {
            display: table-cell;
            text-align: right;
            width: 100px;
        }
        
        .totals-row.total {
            font-size: 16px;
            font-weight: bold;
            border-top: 2px solid #333;
            padding-top: 10px;
        }
        
        .notes {
            background-color: #f9fafb;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        
        .notes-title {
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #6b7280;
            font-size: 10px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .status-draft {
            background-color: #e5e7eb;
            color: #374151;
        }
        
        .status-sent {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        .status-approved {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .status-rejected {
            background-color: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-top">
                <div class="logo-section">
                    <div class="company-name">{{ $quote->tenant->name }}</div>
                    <p>RUT: {{ $quote->tenant->rut }}</p>
                    @if($quote->tenant->address)
                        <p>{{ $quote->tenant->address }}</p>
                    @endif
                    @if($quote->tenant->phone)
                        <p>Tel: {{ $quote->tenant->phone }}</p>
                    @endif
                    @if($quote->tenant->email)
                        <p>{{ $quote->tenant->email }}</p>
                    @endif
                </div>
                <div class="company-info">
                    <div class="quote-title">
                        <div class="quote-number">COTIZACIÓN</div>
                        <div style="font-size: 16px; margin-top: 5px;">#{{ $quote->quote_number }}</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="info-grid">
            <div class="info-column">
                <div class="info-block">
                    <div class="info-title">CLIENTE</div>
                    <div class="info-content">
                        <p><strong>{{ $quote->customer->name }}</strong></p>
                        <p>RUT: {{ $quote->customer->rut }}</p>
                        @if($quote->customer->address)
                            <p>{{ $quote->customer->address }}</p>
                        @endif
                        @if($quote->customer->phone)
                            <p>Tel: {{ $quote->customer->phone }}</p>
                        @endif
                        @if($quote->customer->email)
                            <p>{{ $quote->customer->email }}</p>
                        @endif
                    </div>
                </div>
            </div>
            
            <div class="info-column">
                <div class="info-block">
                    <div class="info-title">DETALLES DE LA COTIZACIÓN</div>
                    <div class="info-content">
                        <p><strong>Fecha:</strong> {{ \Carbon\Carbon::parse($quote->quote_date)->format('d/m/Y') }}</p>
                        <p><strong>Válida hasta:</strong> {{ \Carbon\Carbon::parse($quote->valid_until)->format('d/m/Y') }}</p>
                        @if($quote->payment_terms)
                            <p><strong>Términos de pago:</strong> {{ $quote->payment_terms }}</p>
                        @endif
                        <p><strong>Estado:</strong> 
                            <span class="status-badge status-{{ $quote->status }}">
                                {{ $quote->status_label }}
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 40%">Producto/Servicio</th>
                    <th style="width: 15%" class="text-right">Cantidad</th>
                    <th style="width: 15%" class="text-right">Precio Unit.</th>
                    <th style="width: 15%" class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($quote->items as $item)
                <tr>
                    <td>
                        <strong>{{ $item->product->name ?? 'Servicio' }}</strong>
                        @if($item->description)
                            <br><small>{{ $item->description }}</small>
                        @endif
                    </td>
                    <td class="text-right">{{ number_format($item->quantity, 2, ',', '.') }}</td>
                    <td class="text-right">${{ number_format($item->unit_price, 0, ',', '.') }}</td>
                    <td class="text-right">${{ number_format($item->total_amount, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        
        <div class="totals">
            <div class="totals-row">
                <div class="totals-label">Subtotal:</div>
                <div class="totals-value">${{ number_format($quote->subtotal, 0, ',', '.') }}</div>
            </div>
            <div class="totals-row">
                <div class="totals-label">IVA (19%):</div>
                <div class="totals-value">${{ number_format($quote->tax_amount, 0, ',', '.') }}</div>
            </div>
            <div class="totals-row total">
                <div class="totals-label">TOTAL:</div>
                <div class="totals-value">${{ number_format($quote->total_amount, 0, ',', '.') }}</div>
            </div>
        </div>
        
        @if($quote->notes)
        <div class="notes">
            <div class="notes-title">NOTAS</div>
            <p>{{ $quote->notes }}</p>
        </div>
        @endif
        
        <div class="footer">
            <p>Esta cotización tiene una validez de {{ \Carbon\Carbon::parse($quote->quote_date)->diffInDays(\Carbon\Carbon::parse($quote->valid_until)) }} días desde su fecha de emisión.</p>
            <p>Gracias por su preferencia.</p>
        </div>
    </div>
</body>
</html>