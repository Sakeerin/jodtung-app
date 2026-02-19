<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private TransactionService $transactionService
    ) {}

    /**
     * Get dashboard summary (income/expense/balance).
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $period = $request->get('period', 'summary_month');

        $summary = $this->transactionService->getSummary($user, $period);

        return response()->json($summary);
    }

    /**
     * Get chart data (category breakdown for donut chart).
     */
    public function chart(Request $request): JsonResponse
    {
        $user = $request->user();
        $period = $request->get('period', 'summary_month');

        $stats = $this->transactionService->getStatsByCategory($user, $period);

        return response()->json($stats);
    }

    /**
     * Get recent transactions.
     */
    public function recent(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = $request->integer('limit', 10);

        $transactions = $this->transactionService->getRecent($user, null, min($limit, 50));

        return response()->json($transactions);
    }
}
