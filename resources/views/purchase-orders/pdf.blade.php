<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Orden de Compra {{ $order->order_number }}</title>
    <style>
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        
        .header {
            margin-bottom: 30px;
            border-bottom: 2px solid #4F46E5;
            padding-bottom: 20px;
        }
        
        .header h1 {
            color: #4F46E5;
            margin: 0 0 10px 0;
            font-size: 24px;
        }
        
        .header .order-info {
            float: right;
            text-align: right;
        }
        
        .header .order-number {
            font-size: 18px;
            font-weight: bold;
            color: #4F46E5;
        }
        
        .info-section {
            margin-bottom: 20px;
            clear: both;
        }
        
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        
        .info-col {
            display: table-cell;
            width: 50%;
            padding-right: 20px;
            vertical-align: top;
        }
        
        .info-col:last-child {
            padding-right: 0;
        }
        
        .info-block {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        .info-block h3 {
            margin: 0 0 10px 0;
            color: #4F46E5;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-block p {
            margin: 5px 0;
        }
        
        .info-block strong {
            display: inline-block;
            width: 100px;
            color: #666;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-draft { background: #e5e7eb; color: #374151; }
        .status-sent { background: #dbeafe; color: #1e40af; }
        .status-confirmed { background: #fef3c7; color: #92400e; }
        .status-partial { background: #fed7aa; color: #9a3412; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .items-table th {
            background: #4F46E5;
            color: white;
            padding: 10px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .items-table tbody tr:nth-child(even) {
            background: #f9fafb;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .totals-section {
            margin-top: 30px;
            float: right;
            width: 300px;
        }
        
        .totals-table {
            width: 100%;
        }
        
        .totals-table tr {
            border-bottom: 1px solid #e5e7eb;
        }
        
        .totals-table td {
            padding: 8px 0;
        }
        
        .totals-table .total-row {
            font-size: 16px;
            font-weight: bold;
            color: #4F46E5;
            border-top: 2px solid #4F46E5;
            border-bottom: none;
        }
        
        .notes-section {
            clear: both;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }
        
        .notes-section h3 {
            color: #4F46E5;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .notes-section p {
            white-space: pre-wrap;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }
        
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #666;
            font-size: 10px;
        }
        
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="order-info">
            <div class="order-number">{{ $order->order_number }}</div>
            <div>Fecha: {{ $order->order_date->format('d/m/Y') }}</div>
            <div class="status-badge status-{{ $order->status }}">
                {{ $order->status_label }}
            </div>
        </div>
        <h1>ORDEN DE COMPRA</h1>
        @if($order->tenant->name)
            <p style="margin: 0; color: #666;">{{ $order->tenant->name }}</p>
        @endif
    </div>

    <div class="info-grid">
        <div class="info-col">
            <div class="info-block">
                <h3>Proveedor</h3>
                <p><strong>Nombre:</strong> {{ $order->supplier->name }}</p>
                <p><strong>RUT:</strong> {{ $order->supplier->rut }}</p>
                @if($order->supplier->email)
                    <p><strong>Email:</strong> {{ $order->supplier->email }}</p>
                @endif
                @if($order->supplier->phone)
                    <p><strong>Teléfono:</strong> {{ $order->supplier->phone }}</p>
                @endif
            </div>
        </div>
        
        <div class="info-col">
            <div class="info-block">
                <h3>Información de la Orden</h3>
                <p><strong>Fecha Orden:</strong> {{ $order->order_date->format('d/m/Y') }}</p>
                @if($order->expected_date)
                    <p><strong>Fecha Esperada:</strong> {{ $order->expected_date->format('d/m/Y') }}</p>
                @endif
                @if($order->reference)
                    <p><strong>Referencia:</strong> {{ $order->reference }}</p>
                @endif
                @if($order->currency !== 'CLP')
                    <p><strong>Moneda:</strong> {{ $order->currency }} (TC: {{ number_format($order->exchange_rate, 4) }})</p>
                @endif
            </div>
        </div>
    </div>

    @if($order->shipping_address || $order->billing_address)
        <div class="info-grid">
            @if($order->shipping_address)
                <div class="info-col">
                    <div class="info-block">
                        <h3>Dirección de Envío</h3>
                        <p>{{ nl2br($order->shipping_address) }}</p>
                        @if($order->shipping_method)
                            <p><strong>Método:</strong> {{ $order->shipping_method }}</p>
                        @endif
                    </div>
                </div>
            @endif
            
            @if($order->billing_address)
                <div class="info-col">
                    <div class="info-block">
                        <h3>Dirección de Facturación</h3>
                        <p>{{ nl2br($order->billing_address) }}</p>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <h3 style="color: #4F46E5; margin-top: 30px; margin-bottom: 15px;">DETALLE DE LA ORDEN</h3>
    
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 40%">Descripción</th>
                <th style="width: 10%">SKU</th>
                <th style="width: 10%" class="text-center">Cantidad</th>
                <th style="width: 10%" class="text-center">Unidad</th>
                <th style="width: 10%" class="text-right">Precio</th>
                <th style="width: 10%" class="text-center">Desc %</th>
                <th style="width: 10%" class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td>{{ $item->sku ?: '-' }}</td>
                    <td class="text-center">{{ number_format($item->quantity, 2) }}</td>
                    <td class="text-center">{{ $item->unit }}</td>
                    <td class="text-right">${{ number_format($item->unit_price, 0, ',', '.') }}</td>
                    <td class="text-center">{{ $item->discount_percent }}%</td>
                    <td class="text-right">${{ number_format($item->total, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals-section">
        <table class="totals-table">
            <tr>
                <td>Subtotal:</td>
                <td class="text-right">${{ number_format($order->subtotal, 0, ',', '.') }}</td>
            </tr>
            @if($order->discount_amount > 0)
                <tr>
                    <td>Descuento:</td>
                    <td class="text-right">-${{ number_format($order->discount_amount, 0, ',', '.') }}</td>
                </tr>
            @endif
            <tr>
                <td>IVA (19%):</td>
                <td class="text-right">${{ number_format($order->tax_amount, 0, ',', '.') }}</td>
            </tr>
            <tr class="total-row">
                <td>TOTAL:</td>
                <td class="text-right">${{ number_format($order->total, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    <div style="clear: both;"></div>

    @if($order->notes)
        <div class="notes-section">
            <h3>Notas</h3>
            <p>{{ $order->notes }}</p>
        </div>
    @endif

    @if($order->terms)
        <div class="notes-section">
            <h3>Términos y Condiciones</h3>
            <p>{{ $order->terms }}</p>
        </div>
    @endif

    <div class="footer">
        <p>Documento generado el {{ now()->format('d/m/Y H:i') }}</p>
        <p>CrecePyme - Sistema de Gestión Empresarial</p>
    </div>
</body>
</html>