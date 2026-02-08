<?php

namespace Database\Seeders;

use App\Enums\TransactionType;
use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Default Income Categories
        $incomeCategories = [
            ['name' => 'เงินเดือน', 'emoji' => '💰', 'sort_order' => 1],
            ['name' => 'โบนัส', 'emoji' => '🎁', 'sort_order' => 2],
            ['name' => 'ลงทุน', 'emoji' => '📈', 'sort_order' => 3],
            ['name' => 'ขายของ', 'emoji' => '🏪', 'sort_order' => 4],
            ['name' => 'รายรับอื่นๆ', 'emoji' => '✨', 'sort_order' => 5],
        ];

        foreach ($incomeCategories as $category) {
            Category::create([
                'user_id' => null, // Default category
                'name' => $category['name'],
                'emoji' => $category['emoji'],
                'type' => TransactionType::INCOME,
                'is_default' => true,
                'sort_order' => $category['sort_order'],
            ]);
        }

        // Default Expense Categories
        $expenseCategories = [
            ['name' => 'อาหาร', 'emoji' => '🍔', 'sort_order' => 1],
            ['name' => 'เดินทาง', 'emoji' => '🚗', 'sort_order' => 2],
            ['name' => 'ช้อปปิ้ง', 'emoji' => '🛒', 'sort_order' => 3],
            ['name' => 'บันเทิง', 'emoji' => '🎬', 'sort_order' => 4],
            ['name' => 'สุขภาพ', 'emoji' => '💊', 'sort_order' => 5],
            ['name' => 'ค่าบ้าน', 'emoji' => '🏠', 'sort_order' => 6],
            ['name' => 'ค่าน้ำ/ค่าไฟ', 'emoji' => '💡', 'sort_order' => 7],
            ['name' => 'โทรศัพท์/อินเทอร์เน็ต', 'emoji' => '📱', 'sort_order' => 8],
            ['name' => 'การศึกษา', 'emoji' => '📚', 'sort_order' => 9],
            ['name' => 'รายจ่ายอื่นๆ', 'emoji' => '💸', 'sort_order' => 10],
        ];

        foreach ($expenseCategories as $category) {
            Category::create([
                'user_id' => null, // Default category
                'name' => $category['name'],
                'emoji' => $category['emoji'],
                'type' => TransactionType::EXPENSE,
                'is_default' => true,
                'sort_order' => $category['sort_order'],
            ]);
        }
    }
}
