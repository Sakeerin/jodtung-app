<?php

namespace Tests\Feature\Api;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TransactionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->category = Category::create([
            'name' => 'อาหาร',
            'emoji' => '🍜',
            'type' => TransactionType::EXPENSE,
            'is_default' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_list_transactions(): void
    {
        Transaction::create([
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
            'type' => TransactionType::EXPENSE,
            'amount' => 150,
            'source' => 'web',
            'transaction_date' => today(),
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/transactions');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_create_transaction(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/transactions', [
            'category_id' => $this->category->id,
            'type' => 'expense',
            'amount' => 250.50,
            'note' => 'ข้าวผัด',
            'transaction_date' => today()->toDateString(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'บันทึกรายการสำเร็จ');

        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'amount' => 250.50,
            'note' => 'ข้าวผัด',
        ]);
    }

    public function test_create_transaction_validation_fails(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/transactions', [
            'type' => 'expense',
            // missing category_id and amount
        ]);

        $response->assertStatus(422);
    }

    public function test_show_transaction(): void
    {
        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
            'type' => TransactionType::EXPENSE,
            'amount' => 100,
            'source' => 'web',
            'transaction_date' => today(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/transactions/{$transaction->id}");

        $response->assertOk();
    }

    public function test_update_transaction(): void
    {
        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
            'type' => TransactionType::EXPENSE,
            'amount' => 100,
            'source' => 'web',
            'transaction_date' => today(),
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/transactions/{$transaction->id}", [
                'amount' => 999,
                'note' => 'updated',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'แก้ไขรายการสำเร็จ');
    }

    public function test_delete_transaction(): void
    {
        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
            'type' => TransactionType::EXPENSE,
            'amount' => 100,
            'source' => 'web',
            'transaction_date' => today(),
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/transactions/{$transaction->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'ลบรายการสำเร็จ');

        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
    }

    public function test_cannot_access_other_users_transaction(): void
    {
        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => Hash::make('password123'),
        ]);

        $transaction = Transaction::create([
            'user_id' => $otherUser->id,
            'category_id' => $this->category->id,
            'type' => TransactionType::EXPENSE,
            'amount' => 100,
            'source' => 'web',
            'transaction_date' => today(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/transactions/{$transaction->id}");

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $response = $this->getJson('/api/transactions');

        $response->assertStatus(401);
    }
}
