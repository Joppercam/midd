<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\BankReconciliationService;
use App\Models\BankTransaction;
use App\Models\BankReconciliation;
use App\Models\BankAccount;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\TaxDocument;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class BankReconciliationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected BankReconciliationService $service;
    protected BankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new BankReconciliationService();
        
        // Create tenant and user
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        
        // Create bank account
        $this->bankAccount = BankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'bank_name' => 'Test Bank',
            'account_number' => '123456789',
            'account_type' => 'checking',
            'currency' => 'CLP',
            'current_balance' => 1000000
        ]);
        
        // Set tenant context
        app()->instance('currentTenant', $this->tenant);
        Auth::login($this->user);
    }

    /** @test */
    public function it_can_find_matches_for_bank_transaction()
    {
        // Create a customer and payment
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Customer',
            'rut' => '12345678-9'
        ]);
        
        $payment = Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'payment_date' => now(),
            'amount' => 100000,
            'payment_method' => 'transfer',
            'reference_number' => 'REF123'
        ]);
        
        // Create bank transaction
        $transaction = BankTransaction::factory()->create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => now(),
            'description' => 'Transfer from Test Customer REF123',
            'amount' => 100000,
            'type' => 'credit',
            'status' => 'pending'
        ]);
        
        // Find matches
        $matches = $this->service->findMatches($transaction);
        
        $this->assertCount(1, $matches);
        $this->assertEquals('payment', $matches[0]['type']);
        $this->assertEquals($payment->id, $matches[0]['match']->id);
        $this->assertEquals(100, $matches[0]['score']);
    }

    /** @test */
    public function it_can_match_expense_transactions()
    {
        // Create a supplier and expense
        $supplier = Supplier::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Supplier'
        ]);
        
        $expense = Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $supplier->id,
            'date' => now(),
            'total_amount' => 50000,
            'payment_status' => 'paid',
            'reference' => 'EXP123'
        ]);
        
        // Create bank transaction
        $transaction = BankTransaction::factory()->create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => now(),
            'description' => 'Payment to Test Supplier EXP123',
            'amount' => -50000,
            'type' => 'debit',
            'status' => 'pending'
        ]);
        
        // Find matches
        $matches = $this->service->findMatches($transaction);
        
        $this->assertCount(1, $matches);
        $this->assertEquals('expense', $matches[0]['type']);
        $this->assertEquals($expense->id, $matches[0]['match']->id);
    }

    /** @test */
    public function it_can_auto_match_transactions_with_high_confidence()
    {
        // Create payment with exact match
        $payment = Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payment_date' => now(),
            'amount' => 75000,
            'reference_number' => 'AUTO123'
        ]);
        
        // Create bank transaction
        $transaction = BankTransaction::factory()->create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => now(),
            'description' => 'AUTO123',
            'amount' => 75000,
            'type' => 'credit',
            'status' => 'pending'
        ]);
        
        // Auto match
        $result = $this->service->autoMatch($this->bankAccount->id);
        
        $this->assertEquals(1, $result['matched']);
        $this->assertEquals(0, $result['skipped']);
        
        // Verify transaction was matched
        $transaction->refresh();
        $this->assertEquals('matched', $transaction->status);
    }

    /** @test */
    public function it_skips_auto_match_for_low_confidence_matches()
    {
        // Create payment with partial match
        $payment = Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payment_date' => now()->subDays(10),
            'amount' => 100000,
            'reference_number' => 'PAY123'
        ]);
        
        // Create bank transaction with similar but not exact details
        $transaction = BankTransaction::factory()->create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => now(),
            'description' => 'Payment received',
            'amount' => 100000,
            'type' => 'credit',
            'status' => 'pending'
        ]);
        
        // Auto match
        $result = $this->service->autoMatch($this->bankAccount->id);
        
        $this->assertEquals(0, $result['matched']);
        $this->assertEquals(1, $result['skipped']);
        
        // Verify transaction was not matched
        $transaction->refresh();
        $this->assertEquals('pending', $transaction->status);
    }

    /** @test */
    public function it_can_create_reconciliation()
    {
        // Create transactions
        $matchedTransaction = BankTransaction::factory()->create([
            'bank_account_id' => $this->bankAccount->id,
            'status' => 'matched',
            'amount' => 50000,
            'type' => 'credit'
        ]);
        
        $pendingTransaction = BankTransaction::factory()->create([
            'bank_account_id' => $this->bankAccount->id,
            'status' => 'pending',
            'amount' => 25000,
            'type' => 'credit'
        ]);
        
        // Create reconciliation
        $data = [
            'period_start' => now()->startOfMonth()->format('Y-m-d'),
            'period_end' => now()->endOfMonth()->format('Y-m-d'),
            'starting_balance' => 1000000,
            'ending_balance' => 1075000,
            'notes' => 'Monthly reconciliation'
        ];
        
        $reconciliation = $this->service->createReconciliation($this->bankAccount->id, $data);
        
        $this->assertInstanceOf(BankReconciliation::class, $reconciliation);
        $this->assertEquals($this->bankAccount->id, $reconciliation->bank_account_id);
        $this->assertEquals(1000000, $reconciliation->starting_balance);
        $this->assertEquals(1075000, $reconciliation->ending_balance);
        $this->assertEquals('pending', $reconciliation->status);
    }

    /** @test */
    public function it_can_complete_reconciliation()
    {
        // Create reconciliation
        $reconciliation = BankReconciliation::factory()->create([
            'bank_account_id' => $this->bankAccount->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
            'starting_balance' => 1000000,
            'ending_balance' => 1100000
        ]);
        
        // Create matched transactions
        BankTransaction::factory()->count(3)->create([
            'bank_account_id' => $this->bankAccount->id,
            'status' => 'matched',
            'reconciliation_id' => $reconciliation->id
        ]);
        
        // Complete reconciliation
        $completed = $this->service->completeReconciliation($reconciliation->id);
        
        $this->assertTrue($completed);
        $reconciliation->refresh();
        $this->assertEquals('completed', $reconciliation->status);
        $this->assertNotNull($reconciliation->reconciled_at);
        $this->assertEquals($this->user->id, $reconciliation->reconciled_by);
    }

    /** @test */
    public function it_cannot_complete_reconciliation_with_pending_transactions()
    {
        // Create reconciliation
        $reconciliation = BankReconciliation::factory()->create([
            'bank_account_id' => $this->bankAccount->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'pending'
        ]);
        
        // Create pending transaction
        BankTransaction::factory()->create([
            'bank_account_id' => $this->bankAccount->id,
            'status' => 'pending',
            'reconciliation_id' => $reconciliation->id
        ]);
        
        // Try to complete reconciliation
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot complete reconciliation with pending transactions');
        
        $this->service->completeReconciliation($reconciliation->id);
    }

    /** @test */
    public function it_can_generate_reconciliation_report()
    {
        // Create reconciliation
        $reconciliation = BankReconciliation::factory()->create([
            'bank_account_id' => $this->bankAccount->id,
            'tenant_id' => $this->tenant->id,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'starting_balance' => 1000000,
            'ending_balance' => 1200000
        ]);
        
        // Create transactions
        BankTransaction::factory()->create([
            'bank_account_id' => $this->bankAccount->id,
            'amount' => 100000,
            'type' => 'credit',
            'status' => 'matched',
            'reconciliation_id' => $reconciliation->id
        ]);
        
        BankTransaction::factory()->create([
            'bank_account_id' => $this->bankAccount->id,
            'amount' => 100000,
            'type' => 'credit',
            'status' => 'pending',
            'reconciliation_id' => $reconciliation->id
        ]);
        
        // Generate report
        $report = $this->service->generateReconciliationReport($reconciliation->id);
        
        $this->assertArrayHasKey('reconciliation', $report);
        $this->assertArrayHasKey('matched_transactions', $report);
        $this->assertArrayHasKey('unmatched_transactions', $report);
        $this->assertArrayHasKey('summary', $report);
        
        $this->assertEquals(1, $report['summary']['total_matched']);
        $this->assertEquals(1, $report['summary']['total_unmatched']);
        $this->assertEquals(100000, $report['summary']['matched_amount']);
        $this->assertEquals(100000, $report['summary']['unmatched_amount']);
    }

    /** @test */
    public function it_can_calculate_matching_score_for_exact_reference()
    {
        $payment = Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'amount' => 50000,
            'reference_number' => 'REF12345',
            'payment_date' => now()
        ]);
        
        $transaction = BankTransaction::factory()->create([
            'bank_account_id' => $this->bankAccount->id,
            'amount' => 50000,
            'description' => 'Payment REF12345',
            'date' => now()
        ]);
        
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateMatchScore');
        $method->setAccessible(true);
        
        $score = $method->invoke($this->service, $transaction, $payment, 'payment');
        
        $this->assertEquals(100, $score); // Perfect match
    }

    /** @test */
    public function it_can_calculate_matching_score_for_fuzzy_match()
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'ABC Company Ltd'
        ]);
        
        $payment = Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'amount' => 75000,
            'payment_date' => now()->subDays(2)
        ]);
        
        $transaction = BankTransaction::factory()->create([
            'bank_account_id' => $this->bankAccount->id,
            'amount' => 75000,
            'description' => 'Transfer from ABC Company',
            'date' => now()
        ]);
        
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateMatchScore');
        $method->setAccessible(true);
        
        $score = $method->invoke($this->service, $transaction, $payment, 'payment');
        
        $this->assertGreaterThan(50, $score); // Good match but not perfect
        $this->assertLessThan(100, $score);
    }

    /** @test */
    public function it_can_generate_monthly_summary()
    {
        // Create transactions for the month
        $creditTransactions = BankTransaction::factory()->count(5)->create([
            'bank_account_id' => $this->bankAccount->id,
            'type' => 'credit',
            'amount' => 100000,
            'date' => now(),
            'status' => 'matched'
        ]);
        
        $debitTransactions = BankTransaction::factory()->count(3)->create([
            'bank_account_id' => $this->bankAccount->id,
            'type' => 'debit',
            'amount' => -50000,
            'date' => now(),
            'status' => 'matched'
        ]);
        
        // Generate summary
        $summary = $this->service->generateMonthlySummary(
            $this->bankAccount->id,
            now()->format('Y-m')
        );
        
        $this->assertArrayHasKey('period', $summary);
        $this->assertArrayHasKey('total_credits', $summary);
        $this->assertArrayHasKey('total_debits', $summary);
        $this->assertArrayHasKey('net_change', $summary);
        $this->assertArrayHasKey('transaction_count', $summary);
        
        $this->assertEquals(500000, $summary['total_credits']);
        $this->assertEquals(150000, $summary['total_debits']);
        $this->assertEquals(350000, $summary['net_change']);
        $this->assertEquals(8, $summary['transaction_count']);
    }

    /** @test */
    public function it_respects_tenant_isolation_when_finding_matches()
    {
        // Create another tenant
        $otherTenant = Tenant::factory()->create();
        
        // Create payment in other tenant
        Payment::factory()->create([
            'tenant_id' => $otherTenant->id,
            'amount' => 100000,
            'reference_number' => 'OTHER123'
        ]);
        
        // Create payment in current tenant
        $currentPayment = Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'amount' => 100000,
            'reference_number' => 'CURRENT123'
        ]);
        
        // Create bank transaction
        $transaction = BankTransaction::factory()->create([
            'bank_account_id' => $this->bankAccount->id,
            'amount' => 100000,
            'description' => 'Payment OTHER123 CURRENT123',
            'type' => 'credit'
        ]);
        
        // Find matches
        $matches = $this->service->findMatches($transaction);
        
        // Should only find the current tenant's payment
        $this->assertCount(1, $matches);
        $this->assertEquals($currentPayment->id, $matches[0]['match']->id);
    }

    /** @test */
    public function it_can_handle_adjustment_entries()
    {
        // Create bank transaction for adjustment
        $transaction = BankTransaction::factory()->create([
            'bank_account_id' => $this->bankAccount->id,
            'amount' => -500,
            'description' => 'Bank fee',
            'type' => 'debit',
            'status' => 'pending'
        ]);
        
        // Mark as adjustment
        $result = $this->service->markAsAdjustment($transaction->id, 'Monthly bank fee');
        
        $this->assertTrue($result);
        $transaction->refresh();
        $this->assertEquals('adjustment', $transaction->status);
        $this->assertStringContains('Monthly bank fee', $transaction->notes);
    }

    /** @test */
    public function it_can_undo_reconciliation()
    {
        // Create completed reconciliation
        $reconciliation = BankReconciliation::factory()->create([
            'bank_account_id' => $this->bankAccount->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'completed',
            'reconciled_at' => now(),
            'reconciled_by' => $this->user->id
        ]);
        
        // Create reconciled transactions
        $transactions = BankTransaction::factory()->count(3)->create([
            'bank_account_id' => $this->bankAccount->id,
            'status' => 'reconciled',
            'reconciliation_id' => $reconciliation->id
        ]);
        
        // Undo reconciliation
        $result = $this->service->undoReconciliation($reconciliation->id);
        
        $this->assertTrue($result);
        $reconciliation->refresh();
        $this->assertEquals('pending', $reconciliation->status);
        $this->assertNull($reconciliation->reconciled_at);
        
        // Check transactions are back to matched status
        foreach ($transactions as $transaction) {
            $transaction->refresh();
            $this->assertEquals('matched', $transaction->status);
        }
    }

    /** @test */
    public function it_can_get_reconciliation_statistics()
    {
        // Create multiple reconciliations
        BankReconciliation::factory()->count(3)->create([
            'bank_account_id' => $this->bankAccount->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'completed'
        ]);
        
        BankReconciliation::factory()->count(2)->create([
            'bank_account_id' => $this->bankAccount->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'pending'
        ]);
        
        // Get statistics
        $stats = $this->service->getReconciliationStats($this->bankAccount->id);
        
        $this->assertArrayHasKey('total_reconciliations', $stats);
        $this->assertArrayHasKey('completed_reconciliations', $stats);
        $this->assertArrayHasKey('pending_reconciliations', $stats);
        $this->assertArrayHasKey('last_reconciliation_date', $stats);
        
        $this->assertEquals(5, $stats['total_reconciliations']);
        $this->assertEquals(3, $stats['completed_reconciliations']);
        $this->assertEquals(2, $stats['pending_reconciliations']);
    }
}