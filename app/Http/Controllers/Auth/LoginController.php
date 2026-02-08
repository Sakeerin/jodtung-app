<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class LoginController extends Controller
{
    /**
     * Login user and create token.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'กรุณากรอกอีเมล',
            'email.email' => 'รูปแบบอีเมลไม่ถูกต้อง',
            'password.required' => 'กรุณากรอกรหัสผ่าน',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'ข้อมูลไม่ถูกต้อง',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง',
            ], 401);
        }

        // Revoke all previous tokens (optional: for single device login)
        // $user->tokens()->delete();

        // Create new token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'เข้าสู่ระบบสำเร็จ',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar_url' => $user->avatar_url,
                    'has_line_connection' => $user->hasLineConnection(),
                ],
                'token' => $token,
            ],
        ]);
    }

    /**
     * Logout user (revoke token).
     */
    public function logout(Request $request): JsonResponse
    {
        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'ออกจากระบบสำเร็จ',
        ]);
    }

    /**
     * Get authenticated user.
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('lineConnection');

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar_url' => $user->avatar_url,
                    'has_line_connection' => $user->hasLineConnection(),
                    'connection_code' => $user->lineConnection?->connection_code,
                ],
            ],
        ]);
    }
}
