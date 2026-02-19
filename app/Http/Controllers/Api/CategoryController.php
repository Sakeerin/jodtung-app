<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Enums\TransactionType;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * List all categories (defaults + user's custom).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $categories = Category::where(function ($query) use ($user) {
            $query->where('is_default', true)
                ->orWhere('user_id', $user->id);
        })
            ->orderBy('type')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Group by type
        $grouped = [
            'income' => $categories->where('type', TransactionType::INCOME)->values(),
            'expense' => $categories->where('type', TransactionType::EXPENSE)->values(),
        ];

        return response()->json($grouped);
    }

    /**
     * Create a new custom category.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Get the next sort order
        $maxSortOrder = Category::where('user_id', $user->id)->max('sort_order') ?? 0;

        $category = Category::create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'emoji' => $data['emoji'],
            'type' => $data['type'],
            'is_default' => false,
            'sort_order' => $maxSortOrder + 1,
        ]);

        return response()->json([
            'message' => 'สร้างหมวดหมู่สำเร็จ',
            'category' => $category,
        ], 201);
    }

    /**
     * Get a single category.
     */
    public function show(Request $request, Category $category): JsonResponse
    {
        // Allow viewing defaults or own categories
        if (!$category->is_default && $category->user_id !== $request->user()->id) {
            return response()->json(['error' => 'ไม่มีสิทธิ์เข้าถึง'], 403);
        }

        return response()->json($category);
    }

    /**
     * Update a custom category.
     */
    public function update(StoreCategoryRequest $request, Category $category): JsonResponse
    {
        // Only allow updating own custom categories
        if ($category->is_default || $category->user_id !== $request->user()->id) {
            return response()->json(['error' => 'ไม่สามารถแก้ไขหมวดหมู่เริ่มต้นได้'], 403);
        }

        $category->update($request->validated());

        return response()->json([
            'message' => 'แก้ไขหมวดหมู่สำเร็จ',
            'category' => $category,
        ]);
    }

    /**
     * Delete a custom category.
     */
    public function destroy(Request $request, Category $category): JsonResponse
    {
        // Only allow deleting own custom categories
        if ($category->is_default || $category->user_id !== $request->user()->id) {
            return response()->json(['error' => 'ไม่สามารถลบหมวดหมู่เริ่มต้นได้'], 403);
        }

        // Check if category is in use
        if ($category->transactions()->count() > 0) {
            return response()->json([
                'error' => 'ไม่สามารถลบหมวดหมู่ที่มีรายการอยู่ได้',
            ], 400);
        }

        // Delete shortcuts using this category
        $category->shortcuts()->delete();
        $category->delete();

        return response()->json([
            'message' => 'ลบหมวดหมู่สำเร็จ',
        ]);
    }
}
