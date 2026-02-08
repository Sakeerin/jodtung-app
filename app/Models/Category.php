<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'emoji',
        'type',
        'is_default',
        'sort_order',
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
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the user that owns this category.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get shortcuts using this category.
     */
    public function shortcuts(): HasMany
    {
        return $this->hasMany(Shortcut::class);
    }

    /**
     * Get transactions in this category.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Scope to get default categories.
     */
    public function scopeDefaults($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope to get income categories.
     */
    public function scopeIncome($query)
    {
        return $query->where('type', TransactionType::INCOME);
    }

    /**
     * Scope to get expense categories.
     */
    public function scopeExpense($query)
    {
        return $query->where('type', TransactionType::EXPENSE);
    }

    /**
     * Get display name with emoji.
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->emoji} {$this->name}";
    }
}
