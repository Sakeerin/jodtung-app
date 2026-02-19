<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'emoji' => ['required', 'string', 'max:10'],
            'type' => ['required', Rule::enum(TransactionType::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'กรุณาระบุชื่อหมวดหมู่',
            'name.max' => 'ชื่อหมวดหมู่ยาวเกินไป',
            'emoji.required' => 'กรุณาระบุไอคอน/อีโมจิ',
            'type.required' => 'กรุณาระบุประเภท (income/expense)',
        ];
    }
}
