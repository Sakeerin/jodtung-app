<?php

namespace Tests\Feature\Services;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TransactionServiceTest extends TestCase
{
    use RefreshDatabase;

    private TransactionService $service;
    private User $user;
    private Category $incomeCategory;
    private Category $expenseCategory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TransactionService::class);

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->incomeCategory = Category::create([
            'name' => 'เงินเดือน',
            'emoji' => '💰',
            'type' => TransactionType::INCOME,
            'is_default' => true,
            'sort_order' => 1,
        ]);

        $this->expenseCategory = Category::create([
            'name' => 'อาหาร',
            'emoji' => '🍜',
            'type' => TransactionType::EXPENSE,
            'is_default' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_create_transaction_returns_transaction_and_balance(): void
    {
        $result = $this->service->create($this->user, [
            'category_id' => $this->incomeCategory->id,
            'type' => TransactionType::INCOME,
            'amount' => 5000,
            'note' => 'เงินเดือน',
            'source' => 'web',
            'transaction_date' => today(),
        ]);

        $this->assertArrayHasKey('transaction', $result);
        $this->assertArrayHasKey('todayBalance', $result);
        $this->assertSame(5000.0, (float) $result['transaction']->amount);
        $this->assertSame(5000.0, $result['todayBalance']);
    }

    public function test_create_transaction_rejects_invalid_amount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->create($this->user, [
            'category_id' => $this->expenseCategory->id,
            'type' => TransactionType::EXPENSE,
            'amount' => 0,
            'source' => 'line',
            'transaction_date' => today(),
        ]);
    }

    public function test_create_from_line(): void
    {
        $result = $this->service->createFromLine(
            user: $this->user,
            type: TransactionType::EXPENSE,
            amount: 150,
            categoryId: $this->expenseCategory->id,
            note: 'ข้าวมันไก่',
        );

        $this->assertSame('line', $result['transaction']->source);
        $this->assertSame(150.0, (float) $result['transaction']->amount);
    }

    public function test_get_summary_today(): void
    {
        $this->service->create($this->user, [
            'category_id' => $this->incomeCategory->id,
            'type' => TransactionType::INCOME,
            'amount' => 1000,
            'source' => 'web',
            'transaction_date' => today(),
        ]);

        $this->service->create($this->user, [
            'category_id' => $this->expenseCategory->id,
            'type' => TransactionType::EXPENSE,
            'amount' => 300,
            'source' => 'web',
            'transaction_date' => today(),
        ]);

        $summary = $this->service->getSummary($this->user, 'summary_today');

        $this->assertSame(1000.0, $summary['totalIncome']);
        $this->assertSame(300.0, $summary['totalExpense']);
        $this->assertSame(700.0, $summary['balance']);
    }

    public function test_get_summary_month(): void
    {
        $this->service->create($this->user, [
            'category_id' => $this->incomeCategory->id,
            'type' => TransactionType::INCOME,
            'amount' => 20000,
            'source' => 'web',
            'transaction_date' => today(),
        ]);

        $summary = $this->service->getSummary($this->user, 'summary_month');

        $this->assertSame(20000.0, $summary['totalIncome']);
        $this->assertSame('เดือนนี้', $summary['periodLabel']);
    }

    public function test_get_stats_by_category(): void
    {
        $this->service->create($this->user, [
            'category_id' => $this->expenseCategory->id,
            'type' => TransactionType::EXPENSE,
            'amount' => 200,
            'source' => 'web',
            'transaction_date' => today(),
        ]);

        $stats = $this->service->getStatsByCategory($this->user);

        $this->assertNotEmpty($stats['expenseByCategory']);
        $this->assertSame(200.0, $stats['totalExpense']);
    }

    public function test_cancel_last_deletes_most_recent(): void
    {
        $this->service->create($this->user, [
            'category_id' => $this->expenseCategory->id,
            'type' => TransactionType::EXPENSE,
            'amount' => 100,
            'source' => 'web',
            'transaction_date' => today(),
        ]);

        $result2 = $this->service->create($this->user, [
            'category_id' => $this->expenseCategory->id,
            'type' => TransactionType::EXPENSE,
            'amount' => 200,
            'source' => 'web',
            'transaction_date' => today(),
        ]);

        $deleted = $this->service->cancelLast($this->user);

        $this->assertNotNull($deleted);
        $this->assertSame(200.0, (float) $deleted->amount);
        $this->assertDatabaseMissing('transactions', ['id' => $result2['transaction']->id]);
    }

    public function test_cancel_last_with_no_transactions_returns_null(): void
    {
        $deleted = $this->service->cancelLast($this->user);

        $this->assertNull($deleted);
    }

    public function test_clear_period_deletes_month_transactions(): void
    {
        $this->service->create($this->user, [
            'category_id' => $this->expenseCategory->id,
            'type' => TransactionType::EXPENSE,
            'amount' => 100,
            'source' => 'web',
            'transaction_date' => today(),
        ]);

        $this->service->create($this->user, [
            'category_id' => $this->expenseCategory->id,
            'type' => TransactionType::EXPENSE,
            'amount' => 200,
            'source' => 'web',
            'transaction_date' => today(),
        ]);

        $count = $this->service->clearPeriod($this->user);

        $this->assertSame(2, $count);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_get_filtered_transactions_by_type(): void
    {
        $this->service->create($this->user, [
            'category_id' => $this->incomeCategory->id,
            'type' => TransactionType::INCOME,
            'amount' => 5000,
            'source' => 'web',
            'transaction_date' => today(),
        ]);

        $this->service->create($this->user, [
            'category_id' => $this->expenseCategory->id,
            'type' => TransactionType::EXPENSE,
            'amount' => 200,
            'source' => 'web',
            'transaction_date' => today(),
        ]);

        $incomeOnly = $this->service->getFilteredTransactions($this->user, [
            'type' => 'income',
        ]);

        $this->assertSame(1, $incomeOnly->total());
    }
}
