@extends('emails.layout')

@section('content')
<div style="background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
    <h2 style="margin: 0; color: #155724;">✅ Pago Confirmado</h2>
</div>

<p>Estimado/a <strong>{{ $customer->name }}</strong>,</p>

<p>Nos complace confirmar que hemos recibido su pago correspondiente a la siguiente factura:</p>

<div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 8px 0; border-bottom: 1px solid #dee2e6;"><strong>Número de Factura:</strong></td>
            <td style="padding: 8px 0; border-bottom: 1px solid #dee2e6; text-align: right;">{{ $invoice->document_number }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; border-bottom: 1px solid #dee2e6;"><strong>Fecha de Emisión:</strong></td>
            <td style="padding: 8px 0; border-bottom: 1px solid #dee2e6; text-align: right;">{{ $invoice->issue_date->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; border-bottom: 1px solid #dee2e6;"><strong>Monto Total:</strong></td>
            <td style="padding: 8px 0; border-bottom: 1px solid #dee2e6; text-align: right;">
                <strong>${{ number_format($invoice->total_amount, 0, ',', '.') }}</strong>
            </td>
        </tr>
        @if(!empty($paymentDetails))
        <tr>
            <td style="padding: 8px 0; border-bottom: 1px solid #dee2e6;"><strong>Método de Pago:</strong></td>
            <td style="padding: 8px 0; border-bottom: 1px solid #dee2e6; text-align: right;">{{ $paymentDetails['method'] ?? 'No especificado' }}</td>
        </tr>
        @if(isset($paymentDetails['date']))
        <tr>
            <td style="padding: 8px 0; border-bottom: 1px solid #dee2e6;"><strong>Fecha de Pago:</strong></td>
            <td style="padding: 8px 0; border-bottom: 1px solid #dee2e6; text-align: right;">{{ \Carbon\Carbon::parse($paymentDetails['date'])->format('d/m/Y') }}</td>
        </tr>
        @endif
        @if(isset($paymentDetails['reference']))
        <tr>
            <td style="padding: 8px 0;"><strong>Referencia:</strong></td>
            <td style="padding: 8px 0; text-align: right;">{{ $paymentDetails['reference'] }}</td>
        </tr>
        @endif
        @endif
    </table>
</div>

@if(!empty($paymentDetails['notes']))
<div style="background-color: #e7f3ff; padding: 15px; border-radius: 5px; margin: 20px 0;">
    <p style="margin: 0;"><strong>Notas del pago:</strong></p>
    <p style="margin: 5px 0 0 0;">{{ $paymentDetails['notes'] }}</p>
</div>
@endif

<p>Su factura ha sido marcada como <strong>pagada</strong> en nuestro sistema. Si adjuntamos un comprobante de pago, lo encontrará como archivo adjunto en este correo.</p>

<div style="background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0;">
    <p style="margin: 0;"><strong>📄 Importante:</strong> Conserve este correo como comprobante de pago para sus registros contables.</p>
</div>

<p>Si tiene alguna consulta sobre este pago o necesita información adicional, no dude en contactarnos.</p>

<p>¡Gracias por su pago puntual!</p>

<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6;">
    <p style="margin: 0;"><strong>{{ $tenant->company_name }}</strong></p>
    @if($tenant->phone)
    <p style="margin: 5px 0;">📞 {{ $tenant->phone }}</p>
    @endif
    @if($tenant->email)
    <p style="margin: 5px 0;">✉️ {{ $tenant->email }}</p>
    @endif
    @if($tenant->address)
    <p style="margin: 5px 0;">📍 {{ $tenant->address }}</p>
    @endif
</div>
@endsection