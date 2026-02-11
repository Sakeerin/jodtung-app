<?php

namespace App\Services;

use App\Models\LineConnection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConnectionService
{
    /**
     * Default expiration time for connection codes in minutes.
     */
    private const DEFAULT_CODE_EXPIRY_MINUTES = 10;

    /**
     * Generate a new connection code for a user.
     *
     * @param User $user
     * @param int|null $expiryMinutes Custom expiry time in minutes
     * @return LineConnection
     */
    public function generateConnectionCode(User $user, ?int $expiryMinutes = null): LineConnection
    {
        $expiryMinutes = $expiryMinutes ?? self::DEFAULT_CODE_EXPIRY_MINUTES;

        // Invalidate any existing unused connection codes
        LineConnection::where('user_id', $user->id)
            ->where('is_connected', false)
            ->delete();

        // Generate a unique code
        do {
            $code = LineConnection::generateCode();
        } while (LineConnection::where('connection_code', $code)->exists());

        // Create new connection record
        $connection = LineConnection::create([
            'user_id' => $user->id,
            'connection_code' => $code,
            'is_connected' => false,
            'code_expires_at' => now()->addMinutes($expiryMinutes),
        ]);

        Log::info('Connection code generated', [
            'user_id' => $user->id,
            'code' => $code,
            'expires_at' => $connection->code_expires_at,
        ]);

        return $connection;
    }

    /**
     * Verify and connect a LINE account using a connection code.
     *
     * @param string $code The connection code (e.g., CONNECT-ABC123)
     * @param string $lineUserId The LINE user ID to connect
     * @return array{success: bool, message: string, user?: User}
     */
    public function connectWithCode(string $code, string $lineUserId): array
    {
        // Normalize code to uppercase
        $code = strtoupper(trim($code));

        // Find the connection record
        $connection = LineConnection::where('connection_code', $code)
            ->where('is_connected', false)
            ->first();

        if (!$connection) {
            return [
                'success' => false,
                'message' => 'รหัสเชื่อมต่อไม่ถูกต้อง',
            ];
        }

        // Check if code is expired
        if ($connection->isCodeExpired()) {
            return [
                'success' => false,
                'message' => 'รหัสเชื่อมต่อหมดอายุแล้ว กรุณาขอรหัสใหม่ที่เว็บไซต์',
            ];
        }

        // Check if this LINE user is already connected to another account
        $existingUser = User::where('line_user_id', $lineUserId)->first();
        if ($existingUser && $existingUser->id !== $connection->user_id) {
            return [
                'success' => false,
                'message' => 'LINE นี้เชื่อมต่อกับบัญชีอื่นอยู่แล้ว',
            ];
        }

        // Connect the accounts
        try {
            DB::transaction(function () use ($connection, $lineUserId) {
                // Update connection record
                $connection->update([
                    'line_user_id' => $lineUserId,
                    'is_connected' => true,
                    'connected_at' => now(),
                ]);

                // Update user's LINE user ID
                $connection->user->update([
                    'line_user_id' => $lineUserId,
                ]);
            });

            Log::info('LINE account connected', [
                'user_id' => $connection->user_id,
                'line_user_id' => $lineUserId,
            ]);

            return [
                'success' => true,
                'message' => 'เชื่อมต่อสำเร็จ',
                'user' => $connection->user->fresh(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to connect LINE account', [
                'error' => $e->getMessage(),
                'user_id' => $connection->user_id,
                'line_user_id' => $lineUserId,
            ]);

            return [
                'success' => false,
                'message' => 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง',
            ];
        }
    }

    /**
     * Disconnect a LINE account from a user.
     *
     * @param User $user
     * @return bool
     */
    public function disconnect(User $user): bool
    {
        try {
            DB::transaction(function () use ($user) {
                // Mark connection as disconnected
                LineConnection::where('user_id', $user->id)
                    ->where('is_connected', true)
                    ->update([
                        'is_connected' => false,
                        'line_user_id' => null,
                    ]);

                // Remove LINE user ID from user
                $user->update([
                    'line_user_id' => null,
                ]);
            });

            Log::info('LINE account disconnected', ['user_id' => $user->id]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to disconnect LINE account', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return false;
        }
    }

    /**
     * Get the connection status for a user.
     *
     * @param User $user
     * @return array{is_connected: bool, connected_at: ?string, line_user_id: ?string}
     */
    public function getStatus(User $user): array
    {
        $connection = LineConnection::where('user_id', $user->id)
            ->where('is_connected', true)
            ->first();

        return [
            'is_connected' => (bool) $connection,
            'connected_at' => $connection?->connected_at?->toIso8601String(),
            'line_user_id' => $connection?->line_user_id,
        ];
    }

    /**
     * Get an active (non-expired, unused) connection code for a user.
     *
     * @param User $user
     * @return LineConnection|null
     */
    public function getActiveConnectionCode(User $user): ?LineConnection
    {
        return LineConnection::where('user_id', $user->id)
            ->where('is_connected', false)
            ->where('code_expires_at', '>', now())
            ->first();
    }

    /**
     * Find a user by their LINE user ID.
     *
     * @param string $lineUserId
     * @return User|null
     */
    public function findUserByLineId(string $lineUserId): ?User
    {
        return User::where('line_user_id', $lineUserId)->first();
    }

    /**
     * Check if a connection code is valid (exists and not expired).
     *
     * @param string $code
     * @return bool
     */
    public function isCodeValid(string $code): bool
    {
        $code = strtoupper(trim($code));

        $connection = LineConnection::where('connection_code', $code)
            ->where('is_connected', false)
            ->first();

        return $connection && !$connection->isCodeExpired();
    }

    /**
     * Clean up expired connection codes.
     * This can be called from a scheduled job.
     *
     * @return int Number of records deleted
     */
    public function cleanupExpiredCodes(): int
    {
        $deleted = LineConnection::where('is_connected', false)
            ->where('code_expires_at', '<', now())
            ->delete();

        if ($deleted > 0) {
            Log::info('Cleaned up expired connection codes', ['count' => $deleted]);
        }

        return $deleted;
    }
}
