<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'group_id',
        'category_id',
        'type',
        'amount',
        'note',
        'source',
        'line_message_id',
        'transaction_date',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    /**
     * Get the user that owns this transaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the group this transaction belongs to.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Get the category for this transaction.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Scope for income transactions.
     */
    public function scopeIncome($query)
    {
        return $query->where('type', TransactionType::INCOME);
    }

    /**
     * Scope for expense transactions.
     */
    public function scopeExpense($query)
    {
        return $query->where('type', TransactionType::EXPENSE);
    }

    /**
     * Scope for transactions from LINE.
     */
    public function scopeFromLine($query)
    {
        return $query->where('source', 'line');
    }

    /**
     * Scope for transactions from web.
     */
    public function scopeFromWeb($query)
    {
        return $query->where('source', 'web');
    }

    /**
     * Scope for today's transactions.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('transaction_date', today());
    }

    /**
     * Scope for this week's transactions.
     */
    public function scopeThisWeek($query)
    {
        return $query->whereBetween('transaction_date', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ]);
    }

    /**
     * Scope for this month's transactions.
     */
    public function scopeThisMonth($query)
    {
        return $query->whereMonth('transaction_date', now()->month)
            ->whereYear('transaction_date', now()->year);
    }

    /**
     * Check if transaction is income.
     */
    public function isIncome(): bool
    {
        return $this->type === TransactionType::INCOME;
    }

    /**
     * Check if transaction is expense.
     */
    public function isExpense(): bool
    {
        return $this->type === TransactionType::EXPENSE;
    }

    /**
     * Get formatted amount with sign.
     */
    public function getFormattedAmountAttribute(): string
    {
        $sign = $this->isIncome() ? '+' : '-';
        return $sign . '฿' . number_format($this->amount, 2);
    }
}
