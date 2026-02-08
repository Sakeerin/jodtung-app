<?php

namespace App\Enums;

enum TransactionType: string
{
    case INCOME = 'income';
    case EXPENSE = 'expense';

    /**
     * Get the label for this type.
     */
    public function label(): string
    {
        return match ($this) {
            self::INCOME => 'รายรับ',
            self::EXPENSE => 'รายจ่าย',
        };
    }

    /**
     * Get the color for this type.
     */
    public function color(): string
    {
        return match ($this) {
            self::INCOME => '#1DB446',  // Green
            self::EXPENSE => '#DD4B39', // Red
        };
    }

    /**
     * Get the sign for this type.
     */
    public function sign(): string
    {
        return match ($this) {
            self::INCOME => '+',
            self::EXPENSE => '-',
        };
    }
}
