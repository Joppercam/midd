<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $invoice->document_type_display }} {{ $invoice->number }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            background-color: #4F46E5;
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .content {
            padding: 30px;
        }
        .greeting {
            font-size: 18px;
            margin-bottom: 20px;
        }
        .message {
            margin-bottom: 30px;
            padding: 20px;
            background-color: #f9fafb;
            border-radius: 6px;
            border-left: 4px solid #4F46E5;
        }
        .invoice-details {
            background-color: #f3f4f6;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 30px;
        }
        .invoice-details h3 {
            margin-top: 0;
            color: #4F46E5;
            font-size: 18px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .detail-label {
            font-weight: 600;
            color: #6b7280;
        }
        .detail-value {
            color: #111827;
        }
        .amount {
            font-size: 24px;
            font-weight: bold;
            color: #4F46E5;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #4F46E5;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        .button.secondary {
            background-color: #6b7280;
        }
        .footer {
            background-color: #f9fafb;
            padding: 20px 30px;
            text-align: center;
            color: #6b7280;
            font-size: 14px;
        }
        .footer a {
            color: #4F46E5;
            text-decoration: none;
        }
        .warning {
            background-color: #fef3c7;
            border: 1px solid #f59e0b;
            color: #92400e;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .success {
            background-color: #d1fae5;
            border: 1px solid #10b981;
            color: #065f46;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $tenant->business_name }}</h1>
            <p style="margin: 5px 0; opacity: 0.9;">{{ $tenant->tax_id }}</p>
        </div>
        
        <div class="content">
            <h2 class="greeting">Estimado(a) {{ $customer->business_name }},</h2>
            
            @if($customMessage)
                <div class="message">
                    {!! nl2br(e($customMessage)) !!}
                </div>
            @else
                <p>Le enviamos su {{ strtolower($invoice->document_type_display) }} electrónica correspondiente a los servicios/productos adquiridos.</p>
            @endif
            
            <div class="invoice-details">
                <h3>Detalles del Documento</h3>
                
                <div class="detail-row">
                    <span class="detail-label">Tipo de Documento:</span>
                    <span class="detail-value">{{ $invoice->document_type_display }}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Número:</span>
                    <span class="detail-value">{{ $invoice->number }}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Fecha de Emisión:</span>
                    <span class="detail-value">{{ $invoice->date->format('d/m/Y') }}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Fecha de Vencimiento:</span>
                    <span class="detail-value">{{ $invoice->due_date->format('d/m/Y') }}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Total a Pagar:</span>
                    <span class="detail-value amount">${{ number_format($invoice->total, 0, ',', '.') }}</span>
                </div>
            </div>
            
            @if($invoice->status === 'sent' && $invoice->due_date->isPast())
                <div class="warning">
                    <strong>⚠️ Documento Vencido</strong><br>
                    Este documento se encuentra vencido. Por favor, regularice su situación a la brevedad.
                </div>
            @elseif($invoice->status === 'paid')
                <div class="success">
                    <strong>✓ Documento Pagado</strong><br>
                    Este documento ya ha sido pagado. Gracias por su preferencia.
                </div>
            @endif
            
            <div style="margin-top: 30px;">
                <a href="{{ url('/invoices/' . $invoice->id) }}" class="button">Ver Documento en Línea</a>
                <a href="{{ url('/invoices/' . $invoice->id . '/download') }}" class="button secondary">Descargar PDF</a>
            </div>
            
            <p style="margin-top: 30px; color: #6b7280;">
                Si tiene alguna pregunta sobre este documento, no dude en contactarnos.
            </p>
        </div>
        
        <div class="footer">
            <p>
                {{ $tenant->business_name }}<br>
                {{ $tenant->address }}<br>
                {{ $tenant->city }}, {{ $tenant->country }}<br>
                Tel: {{ $tenant->phone ?? 'No especificado' }}
            </p>
            <p style="margin-top: 15px;">
                <small>Este es un correo electrónico automático, por favor no responda a este mensaje.</small>
            </p>
            <p style="margin-top: 10px;">
                <a href="{{ url('/') }}">Visitar sitio web</a> | 
                <a href="{{ url('/emails/' . ($notification->id ?? '') . '/track-open') }}" style="display: none;">
                    <img src="{{ url('/emails/' . ($notification->id ?? '') . '/track-open') }}" width="1" height="1" alt="">
                </a>
            </p>
        </div>
    </div>
</body>
</html>