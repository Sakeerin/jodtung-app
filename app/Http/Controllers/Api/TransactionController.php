<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(
        private TransactionService $transactionService
    ) {}

    /**
     * List transactions with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $filters = $request->only([
            'period', 'type', 'category_id',
            'start_date', 'end_date', 'per_page',
        ]);

        $transactions = $this->transactionService->getFilteredTransactions($user, $filters);

        return response()->json($transactions);
    }

    /**
     * Create a new transaction.
     */
    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $data['source'] = 'web';

        $result = $this->transactionService->create($user, $data);

        return response()->json([
            'message' => 'บันทึกรายการสำเร็จ',
            'transaction' => $result['transaction'],
            'todayBalance' => $result['todayBalance'],
        ], 201);
    }

    /**
     * Get a single transaction.
     */
    public function show(Request $request, Transaction $transaction): JsonResponse
    {
        // Ensure the user owns this transaction
        if ($transaction->user_id !== $request->user()->id) {
            return response()->json(['error' => 'ไม่มีสิทธิ์เข้าถึง'], 403);
        }

        $transaction->load('category');

        return response()->json($transaction);
    }

    /**
     * Update a transaction.
     */
    public function update(UpdateTransactionRequest $request, Transaction $transaction): JsonResponse
    {
        // Ensure the user owns this transaction
        if ($transaction->user_id !== $request->user()->id) {
            return response()->json(['error' => 'ไม่มีสิทธิ์เข้าถึง'], 403);
        }

        $updated = $this->transactionService->update($transaction, $request->validated());

        return response()->json([
            'message' => 'แก้ไขรายการสำเร็จ',
            'transaction' => $updated,
        ]);
    }

    /**
     * Delete a transaction.
     */
    public function destroy(Request $request, Transaction $transaction): JsonResponse
    {
        // Ensure the user owns this transaction
        if ($transaction->user_id !== $request->user()->id) {
            return response()->json(['error' => 'ไม่มีสิทธิ์เข้าถึง'], 403);
        }

        $this->transactionService->delete($transaction);

        return response()->json([
            'message' => 'ลบรายการสำเร็จ',
        ]);
    }
}
