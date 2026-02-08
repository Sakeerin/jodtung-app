<?php

namespace App\Enums;

enum PeriodType: string
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';

    /**
     * Get the label for this period.
     */
    public function label(): string
    {
        return match ($this) {
            self::DAILY => 'วันนี้',
            self::WEEKLY => 'สัปดาห์นี้',
            self::MONTHLY => 'เดือนนี้',
        };
    }

    /**
     * Get the date range for this period.
     *
     * @return array{start: \Carbon\Carbon, end: \Carbon\Carbon}
     */
    public function dateRange(): array
    {
        return match ($this) {
            self::DAILY => [
                'start' => now()->startOfDay(),
                'end' => now()->endOfDay(),
            ],
            self::WEEKLY => [
                'start' => now()->startOfWeek(),
                'end' => now()->endOfWeek(),
            ],
            self::MONTHLY => [
                'start' => now()->startOfMonth(),
                'end' => now()->endOfMonth(),
            ],
        };
    }
}
