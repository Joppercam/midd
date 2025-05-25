<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $invoice->formatted_number }}</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        
        .header {
            border-bottom: 2px solid #3490dc;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .company-info {
            float: left;
            width: 60%;
        }
        
        .document-info {
            float: right;
            width: 35%;
            text-align: right;
        }
        
        .document-type {
            font-size: 24px;
            font-weight: bold;
            color: #3490dc;
            margin-bottom: 10px;
        }
        
        .document-number {
            font-size: 18px;
            margin-bottom: 5px;
        }
        
        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }
        
        .section {
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #2d3748;
        }
        
        .info-table {
            width: 100%;
            margin-bottom: 20px;
        }
        
        .info-table td {
            padding: 5px 0;
        }
        
        .info-table .label {
            font-weight: bold;
            width: 30%;
            color: #718096;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .items-table th {
            background-color: #f7fafc;
            border-bottom: 2px solid #e2e8f0;
            padding: 12px;
            text-align: left;
            font-weight: bold;
            color: #4a5568;
        }
        
        .items-table td {
            border-bottom: 1px solid #e2e8f0;
            padding: 12px;
        }
        
        .items-table th.text-right,
        .items-table td.text-right {
            text-align: right;
        }
        
        .totals {
            float: right;
            width: 40%;
            margin-top: 20px;
        }
        
        .totals table {
            width: 100%;
        }
        
        .totals td {
            padding: 8px 0;
        }
        
        .totals .label {
            text-align: right;
            padding-right: 20px;
            color: #718096;
        }
        
        .totals .amount {
            text-align: right;
            font-weight: bold;
        }
        
        .totals .total-row {
            font-size: 18px;
            border-top: 2px solid #e2e8f0;
            padding-top: 10px;
        }
        
        .footer {
            margin-top: 50px;
            text-align: center;
            color: #718096;
            font-size: 12px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-draft {
            background-color: #e2e8f0;
            color: #4a5568;
        }
        
        .status-sent {
            background-color: #bee3f8;
            color: #2b6cb0;
        }
        
        .status-accepted {
            background-color: #c6f6d5;
            color: #276749;
        }
        
        .status-rejected {
            background-color: #fed7d7;
            color: #9b2c2c;
        }
        
        .paid-stamp {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 60px;
            color: #48bb78;
            opacity: 0.3;
            font-weight: bold;
            text-transform: uppercase;
            z-index: -1;
        }
        
        @media print {
            body {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    @if($invoice->paid_at)
    <div class="paid-stamp">PAGADO</div>
    @endif

    <div class="header clearfix">
        <div class="company-info">
            <h1 style="margin: 0;">{{ $invoice->tenant->name }}</h1>
            <p style="margin: 5px 0;">RUT: {{ $invoice->tenant->formatted_rut }}</p>
            @if($invoice->tenant->address)
            <p style="margin: 5px 0;">{{ $invoice->tenant->address }}</p>
            @endif
            @if($invoice->tenant->phone)
            <p style="margin: 5px 0;">Tel: {{ $invoice->tenant->phone }}</p>
            @endif
            @if($invoice->tenant->email)
            <p style="margin: 5px 0;">Email: {{ $invoice->tenant->email }}</p>
            @endif
        </div>
        
        <div class="document-info">
            <div class="document-type">
                @switch($invoice->type)
                    @case('invoice')
                        FACTURA ELECTRÓNICA
                        @break
                    @case('receipt')
                        BOLETA ELECTRÓNICA
                        @break
                    @case('credit_note')
                        NOTA DE CRÉDITO
                        @break
                    @case('debit_note')
                        NOTA DE DÉBITO
                        @break
                @endswitch
            </div>
            <div class="document-number">N° {{ $invoice->number }}</div>
            <div>Fecha: {{ $invoice->issue_date->format('d/m/Y') }}</div>
            <div>
                <span class="status-badge status-{{ $invoice->status }}">
                    @switch($invoice->status)
                        @case('draft')
                            Borrador
                            @break
                        @case('sent')
                            Enviado
                            @break
                        @case('accepted')
                            Aceptado
                            @break
                        @case('rejected')
                            Rechazado
                            @break
                    @endswitch
                </span>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">DATOS DEL CLIENTE</div>
        <table class="info-table">
            <tr>
                <td class="label">Razón Social:</td>
                <td>{{ $invoice->customer->name }}</td>
            </tr>
            <tr>
                <td class="label">RUT:</td>
                <td>{{ $invoice->customer->formatted_rut }}</td>
            </tr>
            @if($invoice->customer->address)
            <tr>
                <td class="label">Dirección:</td>
                <td>{{ $invoice->customer->address }}</td>
            </tr>
            @endif
            @if($invoice->customer->commune)
            <tr>
                <td class="label">Comuna:</td>
                <td>{{ $invoice->customer->commune }}</td>
            </tr>
            @endif
            @if($invoice->customer->phone)
            <tr>
                <td class="label">Teléfono:</td>
                <td>{{ $invoice->customer->phone }}</td>
            </tr>
            @endif
            @if($invoice->customer->email)
            <tr>
                <td class="label">Email:</td>
                <td>{{ $invoice->customer->email }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="section">
        <div class="section-title">DETALLE</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 10%">Código</th>
                    <th style="width: 40%">Descripción</th>
                    <th class="text-right" style="width: 15%">Cantidad</th>
                    <th class="text-right" style="width: 15%">Precio Unit.</th>
                    <th class="text-right" style="width: 20%">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $item)
                <tr>
                    <td>{{ $item->product->code ?? '-' }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="text-right">{{ number_format($item->quantity, 2, ',', '.') }}</td>
                    <td class="text-right">${{ number_format($item->unit_price, 0, ',', '.') }}</td>
                    <td class="text-right">${{ number_format($item->total, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="clearfix">
        <div class="totals">
            <table>
                <tr>
                    <td class="label">Subtotal:</td>
                    <td class="amount">${{ number_format($invoice->subtotal, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td class="label">IVA (19%):</td>
                    <td class="amount">${{ number_format($invoice->tax_amount, 0, ',', '.') }}</td>
                </tr>
                <tr class="total-row">
                    <td class="label">TOTAL:</td>
                    <td class="amount">${{ number_format($invoice->total, 0, ',', '.') }}</td>
                </tr>
            </table>
        </div>
    </div>

    <div class="section" style="margin-top: 100px;">
        <table class="info-table">
            <tr>
                <td class="label">Vencimiento:</td>
                <td>{{ $invoice->due_date->format('d/m/Y') }}</td>
            </tr>
            @if($invoice->paid_at)
            <tr>
                <td class="label">Fecha de Pago:</td>
                <td>{{ $invoice->paid_at->format('d/m/Y') }}</td>
            </tr>
            @endif
            @if($invoice->sii_track_id)
            <tr>
                <td class="label">Track ID SII:</td>
                <td>{{ $invoice->sii_track_id }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="footer">
        <p>Documento tributario electrónico emitido según Res. Ex. SII N° {{ $invoice->tenant->sii_configuration->resolution_number ?? '0' }} del {{ $invoice->tenant->sii_configuration ? $invoice->tenant->sii_configuration->resolution_date->format('d/m/Y') : 'Sin configurar' }}</p>
        <p>Timbre Electrónico SII</p>
    </div>
</body>
</html>