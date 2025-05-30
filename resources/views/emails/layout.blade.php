<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Notificación')</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .header {
            background-color: #4f46e5;
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
        }
        .content {
            padding: 30px;
        }
        .greeting {
            font-size: 18px;
            margin-bottom: 20px;
        }
        .message {
            margin-bottom: 25px;
        }
        .highlight-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }
        .invoice-details {
            background-color: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }
        .invoice-details h3 {
            margin: 0 0 15px 0;
            color: #0369a1;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .detail-label {
            font-weight: bold;
            color: #64748b;
        }
        .detail-value {
            color: #1e293b;
        }
        .amount {
            font-size: 20px;
            font-weight: bold;
            color: #dc2626;
        }
        .button {
            display: inline-block;
            background-color: #4f46e5;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            margin: 20px 0;
        }
        .button:hover {
            background-color: #4338ca;
        }
        .footer {
            background-color: #f8fafc;
            padding: 20px 30px;
            border-top: 1px solid #e2e8f0;
            font-size: 14px;
            color: #64748b;
        }
        .company-info {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }
        .company-info strong {
            color: #1e293b;
        }
        .tracking-pixel {
            width: 1px;
            height: 1px;
        }
        @media only screen and (max-width: 600px) {
            .container {
                margin: 10px;
                border-radius: 0;
            }
            .header, .content, .footer {
                padding: 20px;
            }
            .detail-row {
                flex-direction: column;
            }
            .detail-label {
                margin-bottom: 4px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $tenant->name }}</h1>
        </div>
        
        <div class="content">
            @yield('content')
        </div>
        
        <div class="footer">
            <p>Este es un mensaje automático, por favor no responder a este email.</p>
            
            <div class="company-info">
                <strong>{{ $tenant->name }}</strong><br>
                @if(isset($tenant->settings['address']))
                    {{ $tenant->settings['address'] }}<br>
                @endif
                @if(isset($tenant->settings['phone']))
                    Teléfono: {{ $tenant->settings['phone'] }}<br>
                @endif
                @if(isset($tenant->settings['email']))
                    Email: {{ $tenant->settings['email'] }}
                @endif
            </div>
        </div>
    </div>
    
    <!-- Tracking pixel -->
    @if(isset($trackingUrl))
        <img src="{{ $trackingUrl }}" alt="" class="tracking-pixel">
    @endif
</body>
</html>