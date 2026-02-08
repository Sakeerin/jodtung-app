<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class LineAutoLoginController extends Controller
{
    /**
     * Auto-login via short-lived token from LINE.
     */
    public function login(Request $request): JsonResponse
    {
        $token = $request->query('token');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token ไม่ถูกต้อง',
            ], 400);
        }

        // Get user ID from cache
        $userId = Cache::get("line_auto_login:{$token}");

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Token หมดอายุหรือไม่ถูกต้อง',
            ], 401);
        }

        // Delete token from cache (one-time use)
        Cache::forget("line_auto_login:{$token}");

        // Find user
        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบผู้ใช้งาน',
            ], 404);
        }

        // Create API token
        $apiToken = $user->createToken('line_auto_login')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'เข้าสู่ระบบสำเร็จ',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar_url' => $user->avatar_url,
                ],
                'token' => $apiToken,
            ],
        ]);
    }

    /**
     * Generate auto-login token for a user.
     * This should be called when sending "Open Web" action from LINE.
     */
    public static function generateToken(int $userId): string
    {
        $token = Str::random(64);
        $expiry = config('app.auto_login_token_expiry', 300); // 5 minutes default

        Cache::put("line_auto_login:{$token}", $userId, $expiry);

        return $token;
    }

    /**
     * Generate auto-login URL for a user.
     */
    public static function generateUrl(int $userId): string
    {
        $token = self::generateToken($userId);
        return config('app.url') . '/api/auth/line-auto-login?token=' . $token;
    }
}
