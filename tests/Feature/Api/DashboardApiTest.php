<?php

namespace Tests\Feature\Api;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $incomeCategory = Category::create([
            'name' => 'เงินเดือน',
            'emoji' => '💰',
            'type' => TransactionType::INCOME,
            'is_default' => true,
            'sort_order' => 1,
        ]);

        $expenseCategory = Category::create([
            'name' => 'อาหาร',
            'emoji' => '🍜',
            'type' => TransactionType::EXPENSE,
            'is_default' => true,
            'sort_order' => 1,
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'category_id' => $incomeCategory->id,
            'type' => TransactionType::INCOME,
            'amount' => 10000,
            'source' => 'web',
            'transaction_date' => today(),
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'category_id' => $expenseCategory->id,
            'type' => TransactionType::EXPENSE,
            'amount' => 500,
            'source' => 'web',
            'transaction_date' => today(),
        ]);
    }

    public function test_get_summary(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/summary?period=summary_month');

        $response->assertOk()
            ->assertJsonStructure(['totalIncome', 'totalExpense', 'balance', 'periodLabel']);

        $data = $response->json();
        $this->assertEquals(10000.0, $data['totalIncome']);
        $this->assertEquals(500.0, $data['totalExpense']);
        $this->assertEquals(9500.0, $data['balance']);
    }

    public function test_get_chart_data(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/chart?period=summary_month');

        $response->assertOk()
            ->assertJsonStructure([
                'incomeByCategory',
                'expenseByCategory',
                'totalIncome',
                'totalExpense',
            ]);
    }

    public function test_get_recent_transactions(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/recent?limit=5');

        $response->assertOk();

        $data = $response->json();
        $this->assertCount(2, $data);
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->getJson('/api/dashboard/summary')->assertStatus(401);
        $this->getJson('/api/dashboard/chart')->assertStatus(401);
        $this->getJson('/api/dashboard/recent')->assertStatus(401);
    }
}
