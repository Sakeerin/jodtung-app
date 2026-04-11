<?php

namespace Tests\Feature\Services;

use App\Models\LineConnection;
use App\Models\User;
use App\Services\ConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ConnectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private ConnectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ConnectionService::class);
    }

    public function test_generate_connection_code_creates_valid_code(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $connection = $this->service->generateConnectionCode($user);

        $this->assertNotNull($connection);
        $this->assertStringStartsWith('CONNECT-', $connection->connection_code);
        $this->assertFalse($connection->is_connected);
        $this->assertNotNull($connection->code_expires_at);
        $this->assertDatabaseHas('line_connections', [
            'user_id' => $user->id,
            'is_connected' => false,
        ]);
    }

    public function test_connect_with_valid_code_links_accounts(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'connect@example.com',
            'password' => Hash::make('password123'),
        ]);

        $connection = $this->service->generateConnectionCode($user);
        $result = $this->service->connectWithCode($connection->connection_code, 'U_LINE_USER_123');

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('line_connections', [
            'user_id' => $user->id,
            'is_connected' => true,
            'line_user_id' => 'U_LINE_USER_123',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'line_user_id' => 'U_LINE_USER_123',
        ]);
    }

    public function test_connect_with_expired_code_fails(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'expired@example.com',
            'password' => Hash::make('password123'),
        ]);

        $connection = $this->service->generateConnectionCode($user);

        // Manually expire the code
        $connection->update(['code_expires_at' => now()->subMinutes(1)]);

        $result = $this->service->connectWithCode($connection->connection_code, 'U_LINE_USER_456');

        $this->assertFalse($result['success']);
        $this->assertStringContains('หมดอายุ', $result['message']);
    }

    public function test_connect_with_invalid_code_fails(): void
    {
        $result = $this->service->connectWithCode('CONNECT-INVALID', 'U_LINE_USER_789');

        $this->assertFalse($result['success']);
    }

    public function test_connect_already_linked_line_user_fails(): void
    {
        $user1 = User::create([
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'password' => Hash::make('password123'),
            'line_user_id' => 'U_EXISTING_LINE',
        ]);

        $user2 = User::create([
            'name' => 'User 2',
            'email' => 'user2@example.com',
            'password' => Hash::make('password123'),
        ]);

        $connection = $this->service->generateConnectionCode($user2);
        $result = $this->service->connectWithCode($connection->connection_code, 'U_EXISTING_LINE');

        $this->assertFalse($result['success']);
    }

    public function test_disconnect_removes_line_link(): void
    {
        $user = User::create([
            'name' => 'Connected User',
            'email' => 'disconnect@example.com',
            'password' => Hash::make('password123'),
            'line_user_id' => 'U_DISCONNECT_TEST',
        ]);

        LineConnection::create([
            'user_id' => $user->id,
            'line_user_id' => 'U_DISCONNECT_TEST',
            'connection_code' => 'CONNECT-DSCNCT',
            'is_connected' => true,
            'connected_at' => now(),
            'code_expires_at' => now()->addMinutes(10),
        ]);

        $result = $this->service->disconnect($user);

        $this->assertTrue($result);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'line_user_id' => null,
        ]);
    }

    public function test_cleanup_expired_codes(): void
    {
        $user = User::create([
            'name' => 'Cleanup User',
            'email' => 'cleanup@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Create an expired code
        LineConnection::create([
            'user_id' => $user->id,
            'connection_code' => 'CONNECT-EXPRD1',
            'is_connected' => false,
            'code_expires_at' => now()->subMinutes(5),
        ]);

        // Create a valid code
        LineConnection::create([
            'user_id' => $user->id,
            'connection_code' => 'CONNECT-VALID1',
            'is_connected' => false,
            'code_expires_at' => now()->addMinutes(5),
        ]);

        $deleted = $this->service->cleanupExpiredCodes();

        $this->assertSame(1, $deleted);
        $this->assertDatabaseMissing('line_connections', ['connection_code' => 'CONNECT-EXPRD1']);
        $this->assertDatabaseHas('line_connections', ['connection_code' => 'CONNECT-VALID1']);
    }

    /**
     * Custom assertion for string contains (PHPUnit 11 compatible).
     */
    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'."
        );
    }
}
