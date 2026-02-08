<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'line_group_id',
        'name',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get all members of this group.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_members')
            ->withPivot('role', 'joined_at');
    }

    /**
     * Get all transactions in this group.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get admin members of this group.
     */
    public function admins(): BelongsToMany
    {
        return $this->members()->wherePivot('role', 'admin');
    }
}
