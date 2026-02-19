<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'exists:categories,id'],
            'type' => ['required', Rule::enum(TransactionType::class)],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'note' => ['nullable', 'string', 'max:255'],
            'transaction_date' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'กรุณาเลือกหมวดหมู่',
            'category_id.exists' => 'หมวดหมู่ไม่ถูกต้อง',
            'type.required' => 'กรุณาระบุประเภท (income/expense)',
            'amount.required' => 'กรุณาระบุจำนวนเงิน',
            'amount.min' => 'จำนวนเงินต้องมากกว่า 0',
            'amount.max' => 'จำนวนเงินมากเกินไป',
            'note.max' => 'หมายเหตุยาวเกินไป (ไม่เกิน 255 ตัวอักษร)',
            'transaction_date.before_or_equal' => 'วันที่ต้องไม่เกินวันนี้',
        ];
    }
}
