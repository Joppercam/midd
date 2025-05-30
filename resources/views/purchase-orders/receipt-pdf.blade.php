<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Recepción {{ $receipt->receipt_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
        }
        .header {
            margin-bottom: 30px;
        }
        .company-info {
            float: left;
            width: 50%;
        }
        .document-info {
            float: right;
            width: 40%;
            text-align: right;
        }
        .company-name {
            font-size: 18px;
            font-weight: bold;
            color: #222;
            margin-bottom: 5px;
        }
        .document-title {
            font-size: 16px;
            font-weight: bold;
            color: #444;
            margin-bottom: 10px;
        }
        .clearfix {
            clear: both;
        }
        .info-section {
            background-color: #f9f9f9;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .info-row {
            margin-bottom: 8px;
        }
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 150px;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            margin-top: 20px;
            margin-bottom: 10px;
            color: #222;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th {
            background-color: #f3f4f6;
            font-weight: bold;
            text-align: left;
            padding: 10px 8px;
            border-bottom: 2px solid #ddd;
            font-size: 11px;
            text-transform: uppercase;
        }
        td {
            padding: 10px 8px;
            border-bottom: 1px solid #eee;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .condition-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        .condition-good {
            background-color: #d1fae5;
            color: #065f46;
        }
        .condition-damaged {
            background-color: #fef3c7;
            color: #92400e;
        }
        .condition-rejected {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .summary-section {
            margin-top: 30px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
        .summary-grid {
            display: table;
            width: 100%;
        }
        .summary-item {
            display: table-cell;
            width: 25%;
            text-align: center;
            padding: 10px;
        }
        .summary-value {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .summary-label {
            font-size: 11px;
            color: #666;
        }
        .notes-section {
            margin-top: 20px;
            padding: 15px;
            background-color: #fff9e6;
            border-left: 4px solid #f59e0b;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-info">
            <div class="company-name">{{ $receipt->purchaseOrder->tenant->name }}</div>
            <div>{{ $receipt->purchaseOrder->tenant->tax_id }}</div>
            <div>{{ $receipt->purchaseOrder->tenant->address }}</div>
            <div>{{ $receipt->purchaseOrder->tenant->city }}, {{ $receipt->purchaseOrder->tenant->country }}</div>
        </div>
        <div class="document-info">
            <div class="document-title">RECEPCIÓN DE MERCANCÍA</div>
            <div style="font-size: 14px; font-weight: bold; margin: 10px 0;">
                {{ $receipt->receipt_number }}
            </div>
            <div>Fecha: {{ \Carbon\Carbon::parse($receipt->received_at)->format('d/m/Y') }}</div>
        </div>
        <div class="clearfix"></div>
    </div>

    <div class="info-section">
        <div class="section-title">Información de la Orden de Compra</div>
        <div class="info-row">
            <span class="info-label">Número de Orden:</span>
            {{ $receipt->purchaseOrder->order_number }}
        </div>
        <div class="info-row">
            <span class="info-label">Proveedor:</span>
            {{ $receipt->purchaseOrder->supplier->name }}
        </div>
        <div class="info-row">
            <span class="info-label">RUT Proveedor:</span>
            {{ $receipt->purchaseOrder->supplier->tax_id }}
        </div>
        <div class="info-row">
            <span class="info-label">Fecha de Orden:</span>
            {{ \Carbon\Carbon::parse($receipt->purchaseOrder->order_date)->format('d/m/Y') }}
        </div>
    </div>

    <div class="info-section">
        <div class="section-title">Información de la Recepción</div>
        <div class="info-row">
            <span class="info-label">Recibido por:</span>
            {{ $receipt->received_by }}
        </div>
        <div class="info-row">
            <span class="info-label">Registrado por:</span>
            {{ $receipt->user->name }}
        </div>
        <div class="info-row">
            <span class="info-label">Documento Referencia:</span>
            {{ $receipt->reference_document ?: 'No especificado' }}
        </div>
        @if($receipt->notes)
        <div class="info-row">
            <span class="info-label">Notas:</span>
            {{ $receipt->notes }}
        </div>
        @endif
    </div>

    <div class="section-title">Detalle de Items Recibidos</div>
    <table>
        <thead>
            <tr>
                <th style="width: 40%">Producto</th>
                <th class="text-center" style="width: 15%">Cantidad Recibida</th>
                <th class="text-center" style="width: 15%">Condición</th>
                <th style="width: 30%">Observaciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($receipt->items as $item)
            <tr>
                <td>
                    <strong>{{ $item->purchaseOrderItem->product->name }}</strong><br>
                    <span style="font-size: 11px; color: #666;">
                        SKU: {{ $item->purchaseOrderItem->product->sku }}
                    </span>
                </td>
                <td class="text-center">{{ $item->quantity_received }}</td>
                <td class="text-center">
                    <span class="condition-badge condition-{{ $item->condition }}">
                        {{ $item->condition === 'good' ? 'BUENO' : ($item->condition === 'damaged' ? 'DAÑADO' : 'RECHAZADO') }}
                    </span>
                </td>
                <td>{{ $item->notes ?: '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary-section">
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-value">{{ $receipt->items->sum('quantity_received') }}</div>
                <div class="summary-label">Total Recibido</div>
            </div>
            <div class="summary-item">
                <div class="summary-value" style="color: #059669;">
                    {{ $receipt->items->where('condition', 'good')->sum('quantity_received') }}
                </div>
                <div class="summary-label">En Buenas Condiciones</div>
            </div>
            <div class="summary-item">
                <div class="summary-value" style="color: #f59e0b;">
                    {{ $receipt->items->where('condition', 'damaged')->sum('quantity_received') }}
                </div>
                <div class="summary-label">Dañados</div>
            </div>
            <div class="summary-item">
                <div class="summary-value" style="color: #dc2626;">
                    {{ $receipt->items->where('condition', 'rejected')->sum('quantity_received') }}
                </div>
                <div class="summary-label">Rechazados</div>
            </div>
        </div>
    </div>

    @if($receipt->items->where('condition', '!=', 'good')->count() > 0)
    <div class="notes-section">
        <strong>Nota:</strong> Se han recibido items en condición no óptima. 
        Se recomienda realizar seguimiento con el proveedor para los items dañados o rechazados.
    </div>
    @endif

    <div style="margin-top: 60px;">
        <table style="width: 100%; border: none;">
            <tr>
                <td style="width: 45%; text-align: center; border: none; padding: 20px;">
                    <div style="border-top: 1px solid #333; margin-top: 40px;"></div>
                    <strong>Recibido por</strong><br>
                    {{ $receipt->received_by }}
                </td>
                <td style="width: 10%; border: none;"></td>
                <td style="width: 45%; text-align: center; border: none; padding: 20px;">
                    <div style="border-top: 1px solid #333; margin-top: 40px;"></div>
                    <strong>Autorizado por</strong><br>
                    Firma y Sello
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>Documento generado el {{ now()->format('d/m/Y H:i') }}</p>
        <p>Este documento es una constancia de recepción de mercancía</p>
    </div>
</body>
</html>