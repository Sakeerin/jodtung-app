<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShortcutRequest;
use App\Models\Shortcut;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShortcutController extends Controller
{
    /**
     * List all shortcuts for the user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $shortcuts = Shortcut::where('user_id', $user->id)
            ->with('category')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($shortcuts);
    }

    /**
     * Create a new shortcut.
     */
    public function store(StoreShortcutRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Check for duplicate keyword
        $existing = Shortcut::where('user_id', $user->id)
            ->where('keyword', $data['keyword'])
            ->first();

        if ($existing) {
            return response()->json([
                'error' => "คำสั่งลัด \"{$data['keyword']}\" มีอยู่แล้ว",
            ], 400);
        }

        $shortcut = Shortcut::create([
            'user_id' => $user->id,
            'keyword' => $data['keyword'],
            'emoji' => $data['emoji'] ?? null,
            'category_id' => $data['category_id'],
            'type' => $data['type'],
        ]);

        $shortcut->load('category');

        return response()->json([
            'message' => 'สร้างคำสั่งลัดสำเร็จ',
            'shortcut' => $shortcut,
        ], 201);
    }

    /**
     * Get a single shortcut.
     */
    public function show(Request $request, Shortcut $shortcut): JsonResponse
    {
        if ($shortcut->user_id !== $request->user()->id) {
            return response()->json(['error' => 'ไม่มีสิทธิ์เข้าถึง'], 403);
        }

        $shortcut->load('category');

        return response()->json($shortcut);
    }

    /**
     * Update a shortcut.
     */
    public function update(StoreShortcutRequest $request, Shortcut $shortcut): JsonResponse
    {
        if ($shortcut->user_id !== $request->user()->id) {
            return response()->json(['error' => 'ไม่มีสิทธิ์เข้าถึง'], 403);
        }

        $data = $request->validated();

        // Check for duplicate keyword (excluding current shortcut)
        $existing = Shortcut::where('user_id', $request->user()->id)
            ->where('keyword', $data['keyword'])
            ->where('id', '!=', $shortcut->id)
            ->first();

        if ($existing) {
            return response()->json([
                'error' => "คำสั่งลัด \"{$data['keyword']}\" มีอยู่แล้ว",
            ], 400);
        }

        $shortcut->update($data);
        $shortcut->load('category');

        return response()->json([
            'message' => 'แก้ไขคำสั่งลัดสำเร็จ',
            'shortcut' => $shortcut,
        ]);
    }

    /**
     * Delete a shortcut.
     */
    public function destroy(Request $request, Shortcut $shortcut): JsonResponse
    {
        if ($shortcut->user_id !== $request->user()->id) {
            return response()->json(['error' => 'ไม่มีสิทธิ์เข้าถึง'], 403);
        }

        $shortcut->delete();

        return response()->json([
            'message' => 'ลบคำสั่งลัดสำเร็จ',
        ]);
    }
}
