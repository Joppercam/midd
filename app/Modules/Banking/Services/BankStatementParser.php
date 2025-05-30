<?php

namespace App\Modules\Banking\Services;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;

class BankStatementParser
{
    protected $parsers = [];

    public function __construct()
    {
        $this->loadParsers();
    }

    protected function loadParsers()
    {
        $this->parsers = config('banking.parsers', [
            'banco_estado' => [
                'date_column' => 0,
                'description_column' => 1,
                'reference_column' => 2,
                'debit_column' => 3,
                'credit_column' => 4,
                'balance_column' => 5,
                'date_format' => 'd/m/Y',
            ],
            'banco_chile' => [
                'date_column' => 0,
                'description_column' => 2,
                'reference_column' => 1,
                'debit_column' => 4,
                'credit_column' => 3,
                'balance_column' => 5,
                'date_format' => 'd-m-Y',
            ],
            'santander' => [
                'date_column' => 0,
                'description_column' => 1,
                'reference_column' => 2,
                'amount_column' => 3,
                'balance_column' => 4,
                'date_format' => 'd/m/Y',
            ],
        ]);
    }

    public function parse(string $filePath, BankAccount $account): Collection
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        
        switch (strtolower($extension)) {
            case 'csv':
                return $this->parseCsv($filePath, $account);
            case 'xls':
            case 'xlsx':
                return $this->parseExcel($filePath, $account);
            case 'txt':
                return $this->parseText($filePath, $account);
            default:
                throw new \InvalidArgumentException("Unsupported file format: {$extension}");
        }
    }

    public function import(string $filePath, BankAccount $account): array
    {
        $transactions = $this->parse($filePath, $account);
        
        return DB::transaction(function () use ($transactions, $account) {
            $imported = 0;
            $duplicates = 0;
            $errors = [];

            foreach ($transactions as $transaction) {
                try {
                    if ($this->isDuplicate($transaction, $account)) {
                        $duplicates++;
                        continue;
                    }

                    BankTransaction::create([
                        'tenant_id' => $account->tenant_id,
                        'bank_account_id' => $account->id,
                        'date' => $transaction['date'],
                        'description' => $transaction['description'],
                        'reference' => $transaction['reference'],
                        'amount' => $transaction['amount'],
                        'type' => $transaction['amount'] > 0 ? 'deposit' : 'withdrawal',
                        'running_balance' => $transaction['balance'] ?? null,
                        'status' => 'pending',
                    ]);

                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'line' => $imported + $duplicates + count($errors) + 1,
                        'error' => $e->getMessage(),
                        'data' => $transaction,
                    ];
                }
            }

            // Update account balance if we have balance information
            if ($transactions->isNotEmpty() && isset($transactions->last()['balance'])) {
                $account->update(['current_balance' => $transactions->last()['balance']]);
            }

            return [
                'imported' => $imported,
                'duplicates' => $duplicates,
                'errors' => $errors,
                'total' => $transactions->count(),
            ];
        });
    }

    protected function parseCsv(string $filePath, BankAccount $account): Collection
    {
        $parser = $this->getParser($account->bank_name);
        $transactions = collect();

        if (($handle = fopen($filePath, 'r')) !== false) {
            $lineNumber = 0;
            $headers = null;

            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                $lineNumber++;

                // Skip header row
                if ($lineNumber === 1 && $this->isHeaderRow($data)) {
                    $headers = $data;
                    continue;
                }

                // Skip empty rows
                if (empty(array_filter($data))) {
                    continue;
                }

                try {
                    $transaction = $this->parseRow($data, $parser);
                    if ($transaction) {
                        $transactions->push($transaction);
                    }
                } catch (\Exception $e) {
                    // Log parsing error but continue
                    \Log::warning("Failed to parse line {$lineNumber}: " . $e->getMessage());
                }
            }

            fclose($handle);
        }

        return $transactions;
    }

    protected function parseExcel(string $filePath, BankAccount $account): Collection
    {
        $parser = $this->getParser($account->bank_name);
        $transactions = collect();

        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        foreach ($rows as $index => $row) {
            // Skip header row
            if ($index === 0 && $this->isHeaderRow($row)) {
                continue;
            }

            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            try {
                $transaction = $this->parseRow($row, $parser);
                if ($transaction) {
                    $transactions->push($transaction);
                }
            } catch (\Exception $e) {
                // Log parsing error but continue
                \Log::warning("Failed to parse row {$index}: " . $e->getMessage());
            }
        }

        return $transactions;
    }

    protected function parseText(string $filePath, BankAccount $account): Collection
    {
        // For banks that provide fixed-width text files
        $parser = $this->getParser($account->bank_name);
        $transactions = collect();

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $index => $line) {
            // Skip headers or summary lines
            if ($this->isHeaderOrSummaryLine($line)) {
                continue;
            }

            try {
                $transaction = $this->parseTextLine($line, $parser);
                if ($transaction) {
                    $transactions->push($transaction);
                }
            } catch (\Exception $e) {
                \Log::warning("Failed to parse line {$index}: " . $e->getMessage());
            }
        }

        return $transactions;
    }

    protected function parseRow(array $data, array $parser): ?array
    {
        // Handle different parser configurations
        if (isset($parser['amount_column'])) {
            // Single amount column (positive for deposits, negative for withdrawals)
            $amount = $this->parseAmount($data[$parser['amount_column']] ?? 0);
        } else {
            // Separate debit/credit columns
            $debit = $this->parseAmount($data[$parser['debit_column']] ?? 0);
            $credit = $this->parseAmount($data[$parser['credit_column']] ?? 0);
            $amount = $credit - $debit;
        }

        // Skip if no amount
        if ($amount == 0) {
            return null;
        }

        return [
            'date' => $this->parseDate($data[$parser['date_column']] ?? '', $parser['date_format']),
            'description' => $this->cleanDescription($data[$parser['description_column']] ?? ''),
            'reference' => $data[$parser['reference_column']] ?? null,
            'amount' => $amount,
            'balance' => isset($parser['balance_column']) ? $this->parseAmount($data[$parser['balance_column']] ?? null) : null,
        ];
    }

    protected function parseTextLine(string $line, array $parser): ?array
    {
        // This would parse fixed-width text files based on position configurations
        // Implementation depends on specific bank format
        return null;
    }

    protected function getParser(string $bankName): array
    {
        $key = $this->normalizeBankName($bankName);
        
        if (isset($this->parsers[$key])) {
            return $this->parsers[$key];
        }

        // Default parser configuration
        return [
            'date_column' => 0,
            'description_column' => 1,
            'reference_column' => 2,
            'debit_column' => 3,
            'credit_column' => 4,
            'balance_column' => 5,
            'date_format' => 'd/m/Y',
        ];
    }

    protected function normalizeBankName(string $bankName): string
    {
        $normalized = strtolower($bankName);
        $normalized = str_replace(['banco ', 'bank '], '', $normalized);
        $normalized = str_replace([' ', '-', '.'], '_', $normalized);
        
        return $normalized;
    }

    protected function parseDate(string $date, string $format): string
    {
        try {
            return Carbon::createFromFormat($format, trim($date))->format('Y-m-d');
        } catch (\Exception $e) {
            // Try alternative formats
            $alternativeFormats = ['d/m/Y', 'd-m-Y', 'Y-m-d', 'm/d/Y'];
            
            foreach ($alternativeFormats as $altFormat) {
                try {
                    return Carbon::createFromFormat($altFormat, trim($date))->format('Y-m-d');
                } catch (\Exception $e) {
                    continue;
                }
            }
            
            throw new \InvalidArgumentException("Unable to parse date: {$date}");
        }
    }

    protected function parseAmount($value): float
    {
        if (is_null($value) || $value === '') {
            return 0;
        }

        // Remove currency symbols and spaces
        $value = preg_replace('/[^\d,.-]/', '', $value);
        
        // Handle different decimal separators
        if (strpos($value, ',') !== false && strpos($value, '.') !== false) {
            // Both separators present, assume comma is thousands separator
            $value = str_replace(',', '', $value);
        } elseif (substr_count($value, ',') === 1 && strpos($value, ',') > strlen($value) - 4) {
            // Single comma near the end, assume it's decimal separator
            $value = str_replace(',', '.', $value);
        } else {
            // Remove all commas (thousands separators)
            $value = str_replace(',', '', $value);
        }

        return (float) $value;
    }

    protected function cleanDescription(string $description): string
    {
        // Remove extra spaces and normalize
        $description = preg_replace('/\s+/', ' ', trim($description));
        
        // Remove common bank codes or prefixes
        $description = preg_replace('/^[A-Z]{2,4}\d{4,}\s+/', '', $description);
        
        return $description;
    }

    protected function isHeaderRow(array $data): bool
    {
        // Check if this looks like a header row
        $headerKeywords = ['fecha', 'date', 'descripcion', 'description', 'monto', 'amount', 'saldo', 'balance'];
        
        foreach ($data as $cell) {
            if (is_string($cell) && !empty($cell)) {
                foreach ($headerKeywords as $keyword) {
                    if (stripos($cell, $keyword) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    protected function isHeaderOrSummaryLine(string $line): bool
    {
        // Check for common header or summary patterns
        $patterns = [
            '/^[\s-=]+$/',  // Lines with only spaces, dashes, or equals
            '/^(fecha|date|total|saldo)/i',  // Common header words
            '/^[A-Z\s]+$/',  // All caps lines (often headers)
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, trim($line))) {
                return true;
            }
        }

        return false;
    }

    protected function isDuplicate(array $transaction, BankAccount $account): bool
    {
        return BankTransaction::where('bank_account_id', $account->id)
            ->where('date', $transaction['date'])
            ->where('amount', $transaction['amount'])
            ->where('description', $transaction['description'])
            ->exists();
    }
}