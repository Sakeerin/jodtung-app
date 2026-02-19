<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'exists:categories,id'],
            'type' => ['sometimes', Rule::enum(TransactionType::class)],
            'amount' => ['sometimes', 'numeric', 'min:0.01', 'max:999999999.99'],
            'note' => ['nullable', 'string', 'max:255'],
            'transaction_date' => ['sometimes', 'date', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.exists' => 'หมวดหมู่ไม่ถูกต้อง',
            'amount.min' => 'จำนวนเงินต้องมากกว่า 0',
            'amount.max' => 'จำนวนเงินมากเกินไป',
            'note.max' => 'หมายเหตุยาวเกินไป (ไม่เกิน 255 ตัวอักษร)',
            'transaction_date.before_or_equal' => 'วันที่ต้องไม่เกินวันนี้',
        ];
    }
}
