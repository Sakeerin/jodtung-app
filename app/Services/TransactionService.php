<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    /**
     * Create a new transaction.
     *
     * @return array{transaction: Transaction, todayBalance: float}
     */
    public function create(User $user, array $data): array
    {
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'group_id' => $data['group_id'] ?? null,
            'category_id' => $data['category_id'],
            'type' => $data['type'],
            'amount' => $data['amount'],
            'note' => $data['note'] ?? null,
            'source' => $data['source'] ?? 'web',
            'line_message_id' => $data['line_message_id'] ?? null,
            'transaction_date' => $data['transaction_date'] ?? today(),
        ]);

        $transaction->load('category');

        $todayBalance = $this->getTodayBalance($user, $data['group_id'] ?? null);

        return [
            'transaction' => $transaction,
            'todayBalance' => $todayBalance,
        ];
    }

    /**
     * Create a transaction from a LINE message.
     *
     * @return array{transaction: Transaction, todayBalance: float}
     */
    public function createFromLine(
        User $user,
        TransactionType $type,
        float $amount,
        ?int $categoryId,
        ?string $note = null,
        ?int $groupId = null,
        ?string $lineMessageId = null
    ): array {
        return $this->create($user, [
            'category_id' => $categoryId,
            'type' => $type,
            'amount' => $amount,
            'note' => $note,
            'source' => 'line',
            'group_id' => $groupId,
            'line_message_id' => $lineMessageId,
            'transaction_date' => today(),
        ]);
    }

    /**
     * Cancel (delete) the last transaction for a user.
     *
     * @return Transaction|null The deleted transaction, or null if none found
     */
    public function cancelLast(?User $user, ?int $groupId = null): ?Transaction
    {
        $query = $this->buildScopedQuery($user, $groupId);

        $transaction = $query->with('category')
            ->orderBy('created_at', 'desc')
            ->first();

        if ($transaction) {
            $deleted = clone $transaction;
            $transaction->delete();
            return $deleted;
        }

        return null;
    }

    /**
     * Get summary (total income, total expense) for a period.
     *
     * @return array{totalIncome: float, totalExpense: float, balance: float, periodLabel: string, periodDetail: ?string}
     */
    public function getSummary(?User $user, string $period, ?int $groupId = null): array
    {
        $query = $this->buildScopedQuery($user, $groupId);

        $dateRange = $this->getDateRange($period);
        if ($dateRange) {
            $query->whereBetween('transaction_date', [$dateRange['start'], $dateRange['end']]);
        }

        $totals = $query->select(
            DB::raw("COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as total_income"),
            DB::raw("COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as total_expense")
        )->first();

        $totalIncome = (float) $totals->total_income;
        $totalExpense = (float) $totals->total_expense;

        return [
            'totalIncome' => $totalIncome,
            'totalExpense' => $totalExpense,
            'balance' => $totalIncome - $totalExpense,
            'periodLabel' => $this->getPeriodLabel($period),
            'periodDetail' => $this->getPeriodDetail($period),
        ];
    }

    /**
     * Get statistics by category for a period.
     *
     * @return array{incomeByCategory: array, expenseByCategory: array, totalIncome: float, totalExpense: float}
     */
    public function getStatsByCategory(?User $user, string $period = 'summary_month', ?int $groupId = null): array
    {
        $query = $this->buildScopedQuery($user, $groupId);

        $dateRange = $this->getDateRange($period);
        if ($dateRange) {
            $query->whereBetween('transaction_date', [$dateRange['start'], $dateRange['end']]);
        }

        // Get income by category
        $incomeByCategory = (clone $query)
            ->where('type', TransactionType::INCOME)
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->select(
                'categories.name',
                'categories.emoji',
                DB::raw('SUM(transactions.amount) as total')
            )
            ->groupBy('categories.id', 'categories.name', 'categories.emoji')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'emoji' => $row->emoji,
                'amount' => (float) $row->total,
            ])
            ->toArray();

        // Get expense by category
        $expenseByCategory = (clone $query)
            ->where('type', TransactionType::EXPENSE)
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->select(
                'categories.name',
                'categories.emoji',
                DB::raw('SUM(transactions.amount) as total')
            )
            ->groupBy('categories.id', 'categories.name', 'categories.emoji')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'emoji' => $row->emoji,
                'amount' => (float) $row->total,
            ])
            ->toArray();

        $totalIncome = array_sum(array_column($incomeByCategory, 'amount'));
        $totalExpense = array_sum(array_column($expenseByCategory, 'amount'));

        return [
            'incomeByCategory' => $incomeByCategory,
            'expenseByCategory' => $expenseByCategory,
            'totalIncome' => $totalIncome,
            'totalExpense' => $totalExpense,
            'periodLabel' => $this->getPeriodLabel($period),
        ];
    }

    /**
     * Get recent transactions.
     */
    public function getRecent(?User $user, ?int $groupId = null, int $limit = 10): Collection
    {
        $query = $this->buildScopedQuery($user, $groupId)
            ->with('category');

        return $query->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get today's net balance for a user.
     */
    public function getTodayBalance(?User $user, ?int $groupId = null): float
    {
        $query = $this->buildScopedQuery($user, $groupId)
            ->whereDate('transaction_date', today());

        $totals = $query->select(
            DB::raw("COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as total_income"),
            DB::raw("COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as total_expense")
        )->first();

        return (float) $totals->total_income - (float) $totals->total_expense;
    }

    /**
     * Update a transaction.
     */
    public function update(Transaction $transaction, array $data): Transaction
    {
        $transaction->update(array_filter([
            'category_id' => $data['category_id'] ?? null,
            'type' => $data['type'] ?? null,
            'amount' => $data['amount'] ?? null,
            'note' => array_key_exists('note', $data) ? $data['note'] : null,
            'transaction_date' => $data['transaction_date'] ?? null,
        ], fn ($value) => $value !== null));

        $transaction->load('category');

        return $transaction;
    }

    /**
     * Delete a transaction.
     */
    public function delete(Transaction $transaction): bool
    {
        return $transaction->delete();
    }

    /**
     * Clear all transactions for a period (used by /เคลียร์ยอด).
     *
     * @return int Number of deleted transactions
     */
    public function clearPeriod(?User $user, string $period = 'summary_month', ?int $groupId = null): int
    {
        $query = $this->buildScopedQuery($user, $groupId);

        $dateRange = $this->getDateRange($period);
        if ($dateRange) {
            $query->whereBetween('transaction_date', [$dateRange['start'], $dateRange['end']]);
        }

        return $query->delete();
    }

    /**
     * Get paginated transactions with filters.
     */
    public function getFilteredTransactions(User $user, array $filters = [])
    {
        $query = Transaction::where('user_id', $user->id)
            ->with('category')
            ->whereNull('group_id');

        // Filter by period
        if (!empty($filters['period'])) {
            $dateRange = $this->getDateRange($filters['period']);
            if ($dateRange) {
                $query->whereBetween('transaction_date', [$dateRange['start'], $dateRange['end']]);
            }
        }

        // Filter by type
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Filter by category
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // Filter by date range
        if (!empty($filters['start_date'])) {
            $query->whereDate('transaction_date', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->whereDate('transaction_date', '<=', $filters['end_date']);
        }

        return $query->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Get date range for a period command.
     *
     * @return array{start: Carbon, end: Carbon}|null
     */
    private function getDateRange(string $period): ?array
    {
        return match ($period) {
            'summary_today' => [
                'start' => today()->startOfDay(),
                'end' => today()->endOfDay(),
            ],
            'summary_week' => [
                'start' => now()->startOfWeek(),
                'end' => now()->endOfWeek(),
            ],
            'summary_month' => [
                'start' => now()->startOfMonth(),
                'end' => now()->endOfMonth(),
            ],
            'summary_all' => null,
            default => null,
        };
    }

    /**
     * Get human-readable period label.
     */
    private function getPeriodLabel(string $period): string
    {
        return match ($period) {
            'summary_today' => 'วันนี้',
            'summary_week' => 'สัปดาห์นี้',
            'summary_month' => 'เดือนนี้',
            'summary_all' => 'ทั้งหมด',
            default => 'เดือนนี้',
        };
    }

    /**
     * Get period detail string (e.g., date range).
     */
    private function getPeriodDetail(string $period): ?string
    {
        $dateRange = $this->getDateRange($period);
        if (!$dateRange) {
            return null;
        }

        $start = $dateRange['start']->translatedFormat('j M Y');
        $end = $dateRange['end']->translatedFormat('j M Y');

        if ($period === 'summary_today') {
            return $start;
        }

        return "{$start} - {$end}";
    }

    /**
     * Build base transaction query for personal or group account.
     */
    private function buildScopedQuery(?User $user, ?int $groupId = null): Builder
    {
        if ($groupId !== null) {
            return Transaction::where('group_id', $groupId);
        }

        if (!$user) {
            throw new \InvalidArgumentException('User is required when querying personal transactions.');
        }

        return Transaction::where('user_id', $user->id)
            ->whereNull('group_id');
    }
}
