<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LineConnection extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'line_user_id',
        'connection_code',
        'is_connected',
        'connected_at',
        'code_expires_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_connected' => 'boolean',
            'connected_at' => 'datetime',
            'code_expires_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns this connection.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if connection code is expired.
     */
    public function isCodeExpired(): bool
    {
        return $this->code_expires_at && $this->code_expires_at->isPast();
    }

    /**
     * Generate a new connection code.
     */
    public static function generateCode(): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return 'CONNECT-' . $code;
    }
}
