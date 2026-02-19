<?php

namespace Tests\Feature\Services;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Group;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TransactionServiceGroupScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_summary_uses_group_scope_not_sender_scope(): void
    {
        $service = app(TransactionService::class);

        $group = Group::create([
            'line_group_id' => 'C99999999999999999999999999999999',
            'name' => 'Office',
            'is_active' => true,
        ]);

        $sender = User::create([
            'name' => 'Sender',
            'email' => 'sender@example.com',
            'password' => Hash::make('password123'),
        ]);

        $otherMember = User::create([
            'name' => 'Other',
            'email' => 'other@example.com',
            'password' => Hash::make('password123'),
        ]);

        $category = Category::create([
            'name' => 'อาหาร',
            'emoji' => '🍜',
            'type' => TransactionType::EXPENSE,
            'is_default' => true,
            'sort_order' => 1,
        ]);

        Transaction::create([
            'user_id' => $sender->id,
            'group_id' => $group->id,
            'category_id' => $category->id,
            'type' => TransactionType::EXPENSE,
            'amount' => 100,
            'source' => 'line',
            'transaction_date' => today(),
        ]);

        Transaction::create([
            'user_id' => $otherMember->id,
            'group_id' => $group->id,
            'category_id' => $category->id,
            'type' => TransactionType::EXPENSE,
            'amount' => 200,
            'source' => 'line',
            'transaction_date' => today(),
        ]);

        $summary = $service->getSummary($sender, 'summary_all', $group->id);

        $this->assertSame(0.0, $summary['totalIncome']);
        $this->assertSame(300.0, $summary['totalExpense']);
        $this->assertSame(-300.0, $summary['balance']);
    }

    public function test_cancel_last_can_work_in_group_without_user_context(): void
    {
        $service = app(TransactionService::class);

        $group = Group::create([
            'line_group_id' => 'C88888888888888888888888888888888',
            'name' => 'Family',
            'is_active' => true,
        ]);

        $user = User::create([
            'name' => 'Member',
            'email' => 'member@example.com',
            'password' => Hash::make('password123'),
        ]);

        $category = Category::create([
            'name' => 'ทั่วไป',
            'emoji' => '🧾',
            'type' => TransactionType::EXPENSE,
            'is_default' => true,
            'sort_order' => 1,
        ]);

        $first = Transaction::create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'category_id' => $category->id,
            'type' => TransactionType::EXPENSE,
            'amount' => 50,
            'source' => 'line',
            'transaction_date' => today(),
        ]);

        $last = Transaction::create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'category_id' => $category->id,
            'type' => TransactionType::EXPENSE,
            'amount' => 75,
            'source' => 'line',
            'transaction_date' => today(),
        ]);

        $deleted = $service->cancelLast(null, $group->id);

        $this->assertNotNull($deleted);
        $this->assertSame($last->id, $deleted->id);
        $this->assertDatabaseHas('transactions', ['id' => $first->id]);
        $this->assertDatabaseMissing('transactions', ['id' => $last->id]);
    }
}
