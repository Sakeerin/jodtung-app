<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LineConnection;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'name.required' => 'กรุณากรอกชื่อ',
            'email.required' => 'กรุณากรอกอีเมล',
            'email.email' => 'รูปแบบอีเมลไม่ถูกต้อง',
            'email.unique' => 'อีเมลนี้ถูกใช้งานแล้ว',
            'password.required' => 'กรุณากรอกรหัสผ่าน',
            'password.min' => 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร',
            'password.confirmed' => 'การยืนยันรหัสผ่านไม่ตรงกัน',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'การลงทะเบียนล้มเหลว',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Create user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Generate connection code
        $connectionCode = $this->generateUniqueConnectionCode();
        $user->lineConnection()->create([
            'connection_code' => $connectionCode,
            'is_connected' => false,
            'code_expires_at' => now()->addMinutes(10), // Code expires in 10 minutes
        ]);

        // Create API token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'ลงทะเบียนสำเร็จ',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'connection_code' => $connectionCode,
                'token' => $token,
            ],
        ], 201);
    }

    /**
     * Generate a unique connection code.
     */
    private function generateUniqueConnectionCode(): string
    {
        do {
            $code = LineConnection::generateCode();
        } while (LineConnection::where('connection_code', $code)->exists());

        return $code;
    }
}
