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
    public function create(User $user, array $data): array
    {
        // Validate amount bounds
        $amount = (float) ($data['amount'] ?? 0);
        if ($amount < 0.01 || $amount > 999999999.99) {
            throw new \InvalidArgumentException('จำนวนเงินต้องอยู่ระหว่าง 0.01 - 999,999,999.99');
        }

        // Truncate note if too long
        if (isset($data['note']) && mb_strlen($data['note']) > 255) {
            $data['note'] = mb_substr($data['note'], 0, 255);
        }

        return DB::transaction(function () use ($user, $data) {
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
        });
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

    public function cancelLast(?User $user, ?int $groupId = null): ?Transaction
    {
        return DB::transaction(function () use ($user, $groupId) {
            $query = $this->buildScopedQuery($user, $groupId);

            $transaction = $query->with('category')
                ->lockForUpdate()
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if ($transaction) {
                $deleted = clone $transaction;
                $transaction->delete();
                return $deleted;
            }

            return null;
        });
    }

    public function getSummary(?User $user, string $period, ?int $groupId = null): array
    {
        $query = $this->buildScopedQuery($user, $groupId);

        $dateRange = $this->getDateRange($period);
        if ($dateRange) {
            $query->whereBetween('transactions.transaction_date', [$dateRange['start'], $dateRange['end']]);
        }

        $incomeType = TransactionType::INCOME->value;
        $expenseType = TransactionType::EXPENSE->value;

        $totals = $query->select(
            DB::raw("COALESCE(SUM(CASE WHEN transactions.type = '{$incomeType}' THEN transactions.amount ELSE 0 END), 0) as total_income"),
            DB::raw("COALESCE(SUM(CASE WHEN transactions.type = '{$expenseType}' THEN transactions.amount ELSE 0 END), 0) as total_expense")
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

    public function getStatsByCategory(?User $user, string $period = 'summary_month', ?int $groupId = null): array
    {
        $query = $this->buildScopedQuery($user, $groupId);

        $dateRange = $this->getDateRange($period);
        if ($dateRange) {
            $query->whereBetween('transactions.transaction_date', [$dateRange['start'], $dateRange['end']]);
        }

        // Single query for both income and expense
        $statsData = (clone $query)
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->select(
                'transactions.type',
                'categories.name',
                'categories.emoji',
                DB::raw('SUM(transactions.amount) as total')
            )
            ->groupBy('categories.id', 'transactions.type', 'categories.name', 'categories.emoji')
            ->get();

        $incomeByCategory = $statsData->filter(fn ($row) => $row->type === TransactionType::INCOME)
            ->sortByDesc('total')
            ->values()
            ->map(fn ($row) => [
                'name' => $row->name,
                'emoji' => $row->emoji,
                'amount' => (float) $row->total,
            ])
            ->toArray();

        $expenseByCategory = $statsData->filter(fn ($row) => $row->type === TransactionType::EXPENSE)
            ->sortByDesc('total')
            ->values()
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

        $incomeType = TransactionType::INCOME->value;
        $expenseType = TransactionType::EXPENSE->value;

        $totals = $query->select(
            DB::raw("COALESCE(SUM(CASE WHEN transactions.type = '{$incomeType}' THEN transactions.amount ELSE 0 END), 0) as total_income"),
            DB::raw("COALESCE(SUM(CASE WHEN transactions.type = '{$expenseType}' THEN transactions.amount ELSE 0 END), 0) as total_expense")
        )->first();

        return (float) $totals->total_income - (float) $totals->total_expense;
    }

    /**
     * Update a transaction.
     */
    public function update(Transaction $transaction, array $data): Transaction
    {
        $updateData = array_filter([
            'category_id'      => $data['category_id'] ?? null,
            'type'             => $data['type'] ?? null,
            'amount'           => $data['amount'] ?? null,
            'transaction_date' => $data['transaction_date'] ?? null,
        ], fn ($value) => $value !== null);

        // Handle note separately — it must be settable to null (to clear it)
        if (array_key_exists('note', $data)) {
            $updateData['note'] = $data['note'];
        }

        $transaction->update($updateData);
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
            $query->whereBetween('transactions.transaction_date', [$dateRange['start'], $dateRange['end']]);
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
            ->paginate(min((int) ($filters['per_page'] ?? 20), 100));
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
            return Transaction::where('transactions.group_id', $groupId);
        }

        if (!$user) {
            throw new \InvalidArgumentException('User is required when querying personal transactions.');
        }

        return Transaction::where('transactions.user_id', $user->id)
            ->whereNull('transactions.group_id');
    }
}
