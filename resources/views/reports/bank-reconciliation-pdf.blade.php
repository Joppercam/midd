<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Conciliación Bancaria</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
        }
        .header {
            margin-bottom: 30px;
            border-bottom: 2px solid #ddd;
            padding-bottom: 20px;
        }
        .company-info {
            margin-bottom: 20px;
        }
        .company-name {
            font-size: 18px;
            font-weight: bold;
            color: #222;
        }
        .report-title {
            font-size: 16px;
            font-weight: bold;
            margin-top: 10px;
        }
        .info-section {
            margin-bottom: 20px;
        }
        .info-row {
            margin-bottom: 5px;
        }
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 150px;
        }
        .balance-section {
            background-color: #f9f9f9;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .balance-grid {
            display: table;
            width: 100%;
        }
        .balance-item {
            display: table-cell;
            width: 33.33%;
            text-align: center;
            padding: 10px;
        }
        .balance-label {
            font-size: 11px;
            color: #666;
        }
        .balance-amount {
            font-size: 16px;
            font-weight: bold;
            margin-top: 5px;
        }
        .positive {
            color: #16a34a;
        }
        .negative {
            color: #dc2626;
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
            padding: 8px;
            border-bottom: 1px solid #ddd;
            font-size: 11px;
            text-transform: uppercase;
        }
        td {
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .summary-box {
            background-color: #f9f9f9;
            padding: 15px;
            margin-top: 20px;
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
            font-size: 18px;
            font-weight: bold;
        }
        .summary-label {
            font-size: 11px;
            color: #666;
            margin-top: 5px;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            margin-top: 30px;
            margin-bottom: 15px;
            color: #222;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-info">
            <div class="company-name">{{ $tenant->name }}</div>
            <div>{{ $tenant->tax_id }}</div>
            <div>{{ $tenant->address }}</div>
            <div>{{ $tenant->city }}, {{ $tenant->country }}</div>
        </div>
        <div class="report-title">Reporte de Conciliación Bancaria</div>
    </div>

    <div class="info-section">
        <div class="info-row">
            <span class="info-label">Cuenta Bancaria:</span>
            {{ $report['bank_account']['name'] }} - {{ $report['bank_account']['bank_name'] }}
        </div>
        <div class="info-row">
            <span class="info-label">Número de Cuenta:</span>
            {{ $report['bank_account']['account_number'] ?? 'N/A' }}
        </div>
        <div class="info-row">
            <span class="info-label">Período:</span>
            {{ \Carbon\Carbon::parse($report['reconciliation']['period']['start'])->format('d/m/Y') }} al 
            {{ \Carbon\Carbon::parse($report['reconciliation']['period']['end'])->format('d/m/Y') }}
        </div>
        <div class="info-row">
            <span class="info-label">Fecha de Conciliación:</span>
            {{ \Carbon\Carbon::parse($report['reconciliation']['date'])->format('d/m/Y') }}
        </div>
        <div class="info-row">
            <span class="info-label">Estado:</span>
            {{ $report['reconciliation']['status'] === 'completed' ? 'Completada' : 'Borrador' }}
        </div>
    </div>

    <div class="balance-section">
        <div class="balance-grid">
            <div class="balance-item">
                <div class="balance-label">Saldo Inicial</div>
                <div class="balance-amount">${{ number_format($report['balances']['opening_balance'], 0, ',', '.') }}</div>
            </div>
            <div class="balance-item">
                <div class="balance-label">Saldo Estado de Cuenta</div>
                <div class="balance-amount">${{ number_format($report['balances']['closing_balance'], 0, ',', '.') }}</div>
            </div>
            <div class="balance-item">
                <div class="balance-label">Saldo Sistema</div>
                <div class="balance-amount">${{ number_format($report['balances']['system_balance'], 0, ',', '.') }}</div>
            </div>
        </div>
        <hr style="margin: 15px 0;">
        <div class="balance-grid">
            <div class="balance-item">
                <div class="balance-label">Diferencia</div>
                <div class="balance-amount {{ $report['balances']['difference'] == 0 ? 'positive' : 'negative' }}">
                    ${{ number_format($report['balances']['difference'], 0, ',', '.') }}
                </div>
            </div>
            <div class="balance-item">
                <div class="balance-label">Ajustes</div>
                <div class="balance-amount">${{ number_format($report['balances']['total_adjustments'], 0, ',', '.') }}</div>
            </div>
            <div class="balance-item">
                <div class="balance-label">Diferencia Final</div>
                <div class="balance-amount {{ $report['balances']['final_difference'] == 0 ? 'positive' : 'negative' }}">
                    ${{ number_format($report['balances']['final_difference'], 0, ',', '.') }}
                </div>
            </div>
        </div>
    </div>

    <div class="summary-box">
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-value">{{ $report['summary']['total_transactions'] }}</div>
                <div class="summary-label">Total Transacciones</div>
            </div>
            <div class="summary-item">
                <div class="summary-value positive">{{ $report['summary']['matched_transactions'] }}</div>
                <div class="summary-label">Conciliadas</div>
            </div>
            <div class="summary-item">
                <div class="summary-value" style="color: #f59e0b;">{{ $report['summary']['unmatched_transactions'] }}</div>
                <div class="summary-label">Pendientes</div>
            </div>
            <div class="summary-item">
                <div class="summary-value">{{ $report['summary']['ignored_transactions'] }}</div>
                <div class="summary-label">Ignoradas</div>
            </div>
        </div>
    </div>

    @if(count($report['matched_transactions']) > 0)
    <div class="section-title">Transacciones Conciliadas</div>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Descripción</th>
                <th class="text-right">Monto</th>
                <th>Conciliado con</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['matched_transactions'] as $transaction)
            <tr>
                <td>{{ \Carbon\Carbon::parse($transaction['date'])->format('d/m/Y') }}</td>
                <td>{{ $transaction['description'] }}</td>
                <td class="text-right {{ $transaction['amount'] > 0 ? 'positive' : 'negative' }}">
                    ${{ number_format(abs($transaction['amount']), 0, ',', '.') }}
                </td>
                <td>
                    @if($transaction['matched_with'])
                        {{ $transaction['matched_with']['type'] }}: {{ $transaction['matched_with']['reference'] }}
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if(count($report['unmatched_transactions']) > 0)
    <div class="section-title">Transacciones No Conciliadas</div>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Descripción</th>
                <th class="text-right">Monto</th>
                <th>Referencia</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['unmatched_transactions'] as $transaction)
            <tr>
                <td>{{ \Carbon\Carbon::parse($transaction['date'])->format('d/m/Y') }}</td>
                <td>{{ $transaction['description'] }}</td>
                <td class="text-right {{ $transaction['amount'] > 0 ? 'positive' : 'negative' }}">
                    ${{ number_format(abs($transaction['amount']), 0, ',', '.') }}
                </td>
                <td>{{ $transaction['reference'] ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if(!empty($report['adjustments']) && count($report['adjustments']) > 0)
    <div class="section-title">Ajustes</div>
    <table>
        <thead>
            <tr>
                <th>Descripción</th>
                <th class="text-right">Monto</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['adjustments'] as $adjustment)
            <tr>
                <td>{{ $adjustment['description'] }}</td>
                <td class="text-right {{ $adjustment['amount'] > 0 ? 'positive' : 'negative' }}">
                    ${{ number_format(abs($adjustment['amount']), 0, ',', '.') }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="footer">
        <p>Reporte generado el {{ now()->format('d/m/Y H:i') }}</p>
    </div>
</body>
</html>