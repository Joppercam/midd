<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BankStatementParser
{
    /**
     * Parse bank statement from various formats
     */
    public function parseStatement(string $content, string $format, BankAccount $bankAccount): array
    {
        try {
            $transactions = [];
            
            switch ($format) {
                case 'csv':
                    $transactions = $this->parseCSV($content, $bankAccount);
                    break;
                case 'txt':
                    $transactions = $this->parseTXT($content, $bankAccount);
                    break;
                case 'excel':
                    $transactions = $this->parseExcel($content, $bankAccount);
                    break;
                case 'bancoestado':
                    $transactions = $this->parseBancoEstado($content, $bankAccount);
                    break;
                case 'santander':
                    $transactions = $this->parseSantander($content, $bankAccount);
                    break;
                case 'bci':
                    $transactions = $this->parseBCI($content, $bankAccount);
                    break;
                case 'scotiabank':
                    $transactions = $this->parseScotiabank($content, $bankAccount);
                    break;
                default:
                    throw new \Exception("Formato no soportado: {$format}");
            }
            
            return [
                'success' => true,
                'transactions' => $transactions,
                'count' => count($transactions)
            ];
            
        } catch (\Exception $e) {
            Log::error('Error parsing bank statement', [
                'format' => $format,
                'bank_account_id' => $bankAccount->id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'transactions' => []
            ];
        }
    }
    
    /**
     * Parse generic CSV format
     */
    private function parseCSV(string $content, BankAccount $bankAccount): array
    {
        $transactions = [];
        $lines = str_getcsv($content, "\n");
        $headers = [];
        $headerMap = [];
        
        foreach ($lines as $index => $line) {
            $data = str_getcsv($line, ',');
            
            // Skip empty lines
            if (empty(array_filter($data))) {
                continue;
            }
            
            // First non-empty line is header
            if (empty($headers)) {
                $headers = array_map('strtolower', array_map('trim', $data));
                $headerMap = $this->mapCSVHeaders($headers);
                continue;
            }
            
            // Parse transaction
            $transaction = $this->parseCSVTransaction($data, $headerMap, $bankAccount);
            if ($transaction) {
                $transactions[] = $transaction;
            }
        }
        
        return $transactions;
    }
    
    /**
     * Map CSV headers to expected fields
     */
    private function mapCSVHeaders(array $headers): array
    {
        $map = [];
        $fieldMappings = [
            'date' => ['fecha', 'date', 'fecha operacion', 'transaction date'],
            'description' => ['descripcion', 'description', 'detalle', 'concepto', 'detail'],
            'reference' => ['referencia', 'reference', 'numero', 'ref', 'document'],
            'amount' => ['monto', 'amount', 'importe', 'valor', 'cargo', 'abono'],
            'balance' => ['saldo', 'balance', 'saldo disponible', 'available balance'],
            'type' => ['tipo', 'type', 'tipo operacion', 'transaction type']
        ];
        
        foreach ($fieldMappings as $field => $variations) {
            foreach ($headers as $index => $header) {
                if (in_array($header, $variations)) {
                    $map[$field] = $index;
                    break;
                }
            }
        }
        
        return $map;
    }
    
    /**
     * Parse individual CSV transaction
     */
    private function parseCSVTransaction(array $data, array $headerMap, BankAccount $bankAccount): ?array
    {
        try {
            // Get date
            $date = null;
            if (isset($headerMap['date']) && isset($data[$headerMap['date']])) {
                $date = $this->parseDate($data[$headerMap['date']]);
            }
            
            if (!$date) {
                return null;
            }
            
            // Get amount
            $amount = 0;
            if (isset($headerMap['amount']) && isset($data[$headerMap['amount']])) {
                $amount = $this->parseAmount($data[$headerMap['amount']]);
            }
            
            // Get description
            $description = '';
            if (isset($headerMap['description']) && isset($data[$headerMap['description']])) {
                $description = trim($data[$headerMap['description']]);
            }
            
            // Get reference
            $reference = '';
            if (isset($headerMap['reference']) && isset($data[$headerMap['reference']])) {
                $reference = trim($data[$headerMap['reference']]);
            }
            
            // Get balance
            $balance = null;
            if (isset($headerMap['balance']) && isset($data[$headerMap['balance']])) {
                $balance = $this->parseAmount($data[$headerMap['balance']]);
            }
            
            // Determine transaction type
            $type = $amount < 0 ? 'debit' : 'credit';
            
            return [
                'bank_account_id' => $bankAccount->id,
                'date' => $date,
                'reference' => $reference ?: $this->generateReference($date, $amount),
                'description' => $description,
                'amount' => abs($amount),
                'balance' => $balance,
                'transaction_type' => $type,
                'category' => $this->categorizeTransaction($description, $type),
                'status' => 'pending',
                'external_id' => $this->generateExternalId($date, $reference, $amount)
            ];
            
        } catch (\Exception $e) {
            Log::warning('Error parsing CSV transaction', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return null;
        }
    }
    
    /**
     * Parse Banco Estado format
     */
    private function parseBancoEstado(string $content, BankAccount $bankAccount): array
    {
        $transactions = [];
        $lines = explode("\n", $content);
        $inTransactionSection = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines
            if (empty($line)) {
                continue;
            }
            
            // Detect start of transaction section
            if (strpos($line, 'Fecha') !== false && strpos($line, 'Descripción') !== false) {
                $inTransactionSection = true;
                continue;
            }
            
            if (!$inTransactionSection) {
                continue;
            }
            
            // Parse transaction line
            if (preg_match('/^(\d{2}\/\d{2}\/\d{4})\s+(.+?)\s+([\d\.\-]+)\s+([\d\.\-]+)$/', $line, $matches)) {
                $date = $this->parseDate($matches[1]);
                $description = trim($matches[2]);
                $amount = $this->parseAmount($matches[3]);
                $balance = $this->parseAmount($matches[4]);
                
                $transactions[] = [
                    'bank_account_id' => $bankAccount->id,
                    'date' => $date,
                    'reference' => $this->generateReference($date, $amount),
                    'description' => $description,
                    'amount' => abs($amount),
                    'balance' => $balance,
                    'transaction_type' => $amount < 0 ? 'debit' : 'credit',
                    'category' => $this->categorizeTransaction($description, $amount < 0 ? 'debit' : 'credit'),
                    'status' => 'pending',
                    'external_id' => $this->generateExternalId($date, '', $amount)
                ];
            }
        }
        
        return $transactions;
    }
    
    /**
     * Parse Santander format
     */
    private function parseSantander(string $content, BankAccount $bankAccount): array
    {
        $transactions = [];
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Santander format: DD/MM/YYYY | Description | Document | Amount | Balance
            if (preg_match('/^(\d{2}\/\d{2}\/\d{4})\s*\|\s*(.+?)\s*\|\s*(\d+)?\s*\|\s*([\d\.\-,]+)\s*\|\s*([\d\.\-,]+)/', $line, $matches)) {
                $date = $this->parseDate($matches[1]);
                $description = trim($matches[2]);
                $reference = trim($matches[3] ?? '');
                $amount = $this->parseAmount($matches[4]);
                $balance = $this->parseAmount($matches[5]);
                
                $transactions[] = [
                    'bank_account_id' => $bankAccount->id,
                    'date' => $date,
                    'reference' => $reference ?: $this->generateReference($date, $amount),
                    'description' => $description,
                    'amount' => abs($amount),
                    'balance' => $balance,
                    'transaction_type' => $amount < 0 ? 'debit' : 'credit',
                    'category' => $this->categorizeTransaction($description, $amount < 0 ? 'debit' : 'credit'),
                    'status' => 'pending',
                    'external_id' => $this->generateExternalId($date, $reference, $amount)
                ];
            }
        }
        
        return $transactions;
    }
    
    /**
     * Parse BCI format
     */
    private function parseBCI(string $content, BankAccount $bankAccount): array
    {
        // BCI typically uses tab-separated values
        return $this->parseTXT($content, $bankAccount);
    }
    
    /**
     * Parse Scotiabank format
     */
    private function parseScotiabank(string $content, BankAccount $bankAccount): array
    {
        $transactions = [];
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Scotiabank format varies but often includes date at beginning
            if (preg_match('/^(\d{2}-\d{2}-\d{4})\s+(.+?)\s+([\d\.\-,]+)$/', $line, $matches)) {
                $date = $this->parseDate($matches[1]);
                $description = trim($matches[2]);
                $amount = $this->parseAmount($matches[3]);
                
                $transactions[] = [
                    'bank_account_id' => $bankAccount->id,
                    'date' => $date,
                    'reference' => $this->generateReference($date, $amount),
                    'description' => $description,
                    'amount' => abs($amount),
                    'balance' => null, // Scotiabank doesn't always include balance
                    'transaction_type' => $amount < 0 ? 'debit' : 'credit',
                    'category' => $this->categorizeTransaction($description, $amount < 0 ? 'debit' : 'credit'),
                    'status' => 'pending',
                    'external_id' => $this->generateExternalId($date, '', $amount)
                ];
            }
        }
        
        return $transactions;
    }
    
    /**
     * Parse tab-separated text format
     */
    private function parseTXT(string $content, BankAccount $bankAccount): array
    {
        $transactions = [];
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            $data = preg_split('/\t+/', trim($line));
            
            if (count($data) < 3) {
                continue;
            }
            
            // Try to identify date, description, and amount
            $date = null;
            $description = '';
            $amount = 0;
            $balance = null;
            
            foreach ($data as $field) {
                if (!$date && $this->isDate($field)) {
                    $date = $this->parseDate($field);
                } elseif ($this->isAmount($field)) {
                    if ($amount == 0) {
                        $amount = $this->parseAmount($field);
                    } else {
                        $balance = $this->parseAmount($field);
                    }
                } elseif (!empty($field) && !is_numeric($field)) {
                    $description .= ' ' . $field;
                }
            }
            
            if ($date && $amount != 0) {
                $transactions[] = [
                    'bank_account_id' => $bankAccount->id,
                    'date' => $date,
                    'reference' => $this->generateReference($date, $amount),
                    'description' => trim($description),
                    'amount' => abs($amount),
                    'balance' => $balance,
                    'transaction_type' => $amount < 0 ? 'debit' : 'credit',
                    'category' => $this->categorizeTransaction($description, $amount < 0 ? 'debit' : 'credit'),
                    'status' => 'pending',
                    'external_id' => $this->generateExternalId($date, '', $amount)
                ];
            }
        }
        
        return $transactions;
    }
    
    /**
     * Parse Excel format (requires conversion to CSV first)
     */
    private function parseExcel(string $content, BankAccount $bankAccount): array
    {
        // Excel files should be converted to CSV before parsing
        return $this->parseCSV($content, $bankAccount);
    }
    
    /**
     * Parse date from various formats
     */
    private function parseDate(string $dateStr): ?string
    {
        $dateStr = trim($dateStr);
        $formats = [
            'd/m/Y',
            'd-m-Y',
            'Y-m-d',
            'd/m/y',
            'd.m.Y',
            'dmY'
        ];
        
        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $dateStr);
                if ($date) {
                    return $date->format('Y-m-d');
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        
        return null;
    }
    
    /**
     * Parse amount from various formats
     */
    private function parseAmount(string $amountStr): float
    {
        // Remove non-numeric characters except . , -
        $amountStr = preg_replace('/[^\d,.\-]/', '', $amountStr);
        
        // Handle Chilean format (1.234,56)
        if (preg_match('/\d+\.\d{3},\d{2}/', $amountStr)) {
            $amountStr = str_replace('.', '', $amountStr);
            $amountStr = str_replace(',', '.', $amountStr);
        }
        // Handle US format (1,234.56)
        elseif (preg_match('/\d+,\d{3}\.\d{2}/', $amountStr)) {
            $amountStr = str_replace(',', '', $amountStr);
        }
        // Handle simple comma as decimal
        elseif (substr_count($amountStr, ',') == 1 && substr_count($amountStr, '.') == 0) {
            $amountStr = str_replace(',', '.', $amountStr);
        }
        
        return (float) $amountStr;
    }
    
    /**
     * Check if string is a date
     */
    private function isDate(string $str): bool
    {
        return preg_match('/^\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4}$/', trim($str)) ||
               preg_match('/^\d{4}[\/-]\d{1,2}[\/-]\d{1,2}$/', trim($str));
    }
    
    /**
     * Check if string is an amount
     */
    private function isAmount(string $str): bool
    {
        $cleaned = preg_replace('/[^\d,.\-]/', '', $str);
        return is_numeric(str_replace([',', '.'], '', $cleaned));
    }
    
    /**
     * Generate reference number
     */
    private function generateReference(string $date, float $amount): string
    {
        return 'REF-' . str_replace('-', '', $date) . '-' . abs($amount);
    }
    
    /**
     * Generate external ID for duplicate detection
     */
    private function generateExternalId(string $date, string $reference, float $amount): string
    {
        return md5($date . $reference . $amount);
    }
    
    /**
     * Categorize transaction based on description
     */
    private function categorizeTransaction(string $description, string $type): string
    {
        $description = strtolower($description);
        
        $categories = [
            'transfer' => ['transferencia', 'transfer', 'traspaso'],
            'payment' => ['pago', 'payment', 'abono'],
            'fee' => ['comision', 'fee', 'cargo', 'mantencion'],
            'tax' => ['impuesto', 'tax', 'iva', 'retencion'],
            'salary' => ['sueldo', 'salary', 'remuneracion', 'nomina'],
            'purchase' => ['compra', 'purchase', 'pos'],
            'withdrawal' => ['giro', 'retiro', 'withdrawal', 'cajero'],
            'deposit' => ['deposito', 'deposit', 'consignacion'],
            'interest' => ['interes', 'interest'],
            'other' => []
        ];
        
        foreach ($categories as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($description, $keyword) !== false) {
                    return $category;
                }
            }
        }
        
        return 'other';
    }
    
    /**
     * Import transactions to database
     */
    public function importTransactions(array $transactions, BankAccount $bankAccount): array
    {
        $imported = 0;
        $duplicates = 0;
        $errors = 0;
        
        DB::beginTransaction();
        try {
            foreach ($transactions as $transaction) {
                // Check for duplicates
                $exists = BankTransaction::where('bank_account_id', $bankAccount->id)
                    ->where('external_id', $transaction['external_id'])
                    ->exists();
                    
                if ($exists) {
                    $duplicates++;
                    continue;
                }
                
                // Create transaction
                BankTransaction::create([
                    'tenant_id' => $bankAccount->tenant_id,
                    'bank_account_id' => $transaction['bank_account_id'],
                    'date' => $transaction['date'],
                    'reference' => $transaction['reference'],
                    'description' => $transaction['description'],
                    'amount' => $transaction['amount'],
                    'balance' => $transaction['balance'],
                    'transaction_type' => $transaction['transaction_type'],
                    'category' => $transaction['category'],
                    'status' => $transaction['status'],
                    'external_id' => $transaction['external_id']
                ]);
                
                $imported++;
            }
            
            DB::commit();
            
            return [
                'success' => true,
                'imported' => $imported,
                'duplicates' => $duplicates,
                'errors' => $errors,
                'total' => count($transactions)
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error importing bank transactions', [
                'error' => $e->getMessage(),
                'bank_account_id' => $bankAccount->id
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'imported' => 0,
                'duplicates' => 0,
                'errors' => count($transactions),
                'total' => count($transactions)
            ];
        }
    }
}