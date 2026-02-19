<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShortcutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'keyword' => ['required', 'string', 'max:50'],
            'emoji' => ['nullable', 'string', 'max:10'],
            'category_id' => ['required', 'exists:categories,id'],
            'type' => ['required', Rule::enum(TransactionType::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'keyword.required' => 'กรุณาระบุคำสั่งลัด',
            'keyword.max' => 'คำสั่งลัดยาวเกินไป',
            'category_id.required' => 'กรุณาเลือกหมวดหมู่',
            'category_id.exists' => 'หมวดหมู่ไม่ถูกต้อง',
            'type.required' => 'กรุณาระบุประเภท (income/expense)',
        ];
    }
}
