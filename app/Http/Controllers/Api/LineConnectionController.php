<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LineConnectionController extends Controller
{
    public function __construct(
        private ConnectionService $connectionService
    ) {}

    /**
     * Get the LINE connection status for the authenticated user.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $status = $this->connectionService->getStatus($user);

        // Check for active connection code
        $activeCode = $this->connectionService->getActiveConnectionCode($user);

        return response()->json([
            'is_connected' => $status['is_connected'],
            'connected_at' => $status['connected_at'],
            'connection_code' => $activeCode?->connection_code,
            'code_expires_at' => $activeCode?->code_expires_at?->toIso8601String(),
        ]);
    }

    /**
     * Generate a new connection code.
     */
    public function generateCode(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if already connected
        if ($user->line_user_id) {
            return response()->json([
                'error' => 'LINE account already connected',
            ], 400);
        }

        $connection = $this->connectionService->generateConnectionCode($user);

        return response()->json([
            'connection_code' => $connection->connection_code,
            'expires_at' => $connection->code_expires_at->toIso8601String(),
            'expires_in_seconds' => $connection->code_expires_at->diffInSeconds(now()),
        ]);
    }

    /**
     * Disconnect the LINE account.
     */
    public function disconnect(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->line_user_id) {
            return response()->json([
                'error' => 'No LINE account connected',
            ], 400);
        }

        $success = $this->connectionService->disconnect($user);

        if ($success) {
            return response()->json([
                'message' => 'LINE account disconnected successfully',
            ]);
        }

        return response()->json([
            'error' => 'Failed to disconnect LINE account',
        ], 500);
    }
}
