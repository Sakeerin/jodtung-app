<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shortcut extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'keyword',
        'emoji',
        'category_id',
        'type',
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
        ];
    }

    /**
     * Get the user that owns this shortcut.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the category for this shortcut.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get display keyword with emoji.
     */
    public function getDisplayKeywordAttribute(): string
    {
        return $this->emoji ? "{$this->emoji} {$this->keyword}" : $this->keyword;
    }

    /**
     * Scope for income shortcuts.
     */
    public function scopeIncome($query)
    {
        return $query->where('type', TransactionType::INCOME);
    }

    /**
     * Scope for expense shortcuts.
     */
    public function scopeExpense($query)
    {
        return $query->where('type', TransactionType::EXPENSE);
    }
}
