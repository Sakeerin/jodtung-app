<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'line_user_id',
        'avatar_url',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the LINE connection for this user.
     */
    public function lineConnection(): HasOne
    {
        return $this->hasOne(LineConnection::class);
    }

    /**
     * Get all categories for this user (including defaults).
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * Get all shortcuts for this user.
     */
    public function shortcuts(): HasMany
    {
        return $this->hasMany(Shortcut::class);
    }

    /**
     * Get all transactions for this user.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get all groups this user belongs to.
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_members')
            ->withPivot('role', 'joined_at');
    }

    /**
     * Check if user has connected LINE account.
     */
    public function hasLineConnection(): bool
    {
        return $this->lineConnection && $this->lineConnection->is_connected;
    }
}
