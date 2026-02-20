<?php

namespace App\Services\Line;

use App\Enums\TransactionType;
use App\Models\Transaction;

class FlexMessageBuilder
{
    // Color constants
    private const COLOR_GREEN = '#1DB446';
    private const COLOR_RED = '#DD4B39';
    private const COLOR_ORANGE = '#FF8C00';
    private const COLOR_WHITE = '#FFFFFF';
    private const COLOR_GRAY = '#888888';
    private const COLOR_LIGHT_GRAY = '#AAAAAA';
    private const COLOR_DARK = '#333333';

    /**
     * Build welcome card for new users.
     */
    public function welcomeCard(?string $userName = null): array
    {
        $greeting = $userName ? "สวัสดี {$userName}!" : 'ยินดีต้อนรับ!';

        return [
            'type' => 'bubble',
            'size' => 'kilo',
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'backgroundColor' => self::COLOR_GREEN,
                'paddingAll' => '20px',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => $greeting,
                        'size' => 'xl',
                        'weight' => 'bold',
                        'color' => self::COLOR_WHITE,
                    ],
                    [
                        'type' => 'text',
                        'text' => 'จดตังค์ - บอทบันทึกรายรับ-รายจ่าย',
                        'size' => 'sm',
                        'color' => self::COLOR_WHITE,
                        'margin' => 'md',
                    ],
                    [
                        'type' => 'separator',
                        'margin' => 'lg',
                        'color' => '#FFFFFF40',
                    ],
                    [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'margin' => 'lg',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => 'เริ่มต้นใช้งาน:',
                                'size' => 'sm',
                                'weight' => 'bold',
                                'color' => self::COLOR_WHITE,
                            ],
                            [
                                'type' => 'text',
                                'text' => '1. สมัครสมาชิกที่เว็บไซต์',
                                'size' => 'sm',
                                'color' => self::COLOR_WHITE,
                                'margin' => 'sm',
                            ],
                            [
                                'type' => 'text',
                                'text' => '2. คัดลอกรหัส CONNECT-XXXXXX',
                                'size' => 'sm',
                                'color' => self::COLOR_WHITE,
                                'margin' => 'sm',
                            ],
                            [
                                'type' => 'text',
                                'text' => '3. พิมพ์รหัสในแชทนี้เพื่อเชื่อมต่อ',
                                'size' => 'sm',
                                'color' => self::COLOR_WHITE,
                                'margin' => 'sm',
                            ],
                        ],
                    ],
                ],
            ],
            'footer' => [
                'type' => 'box',
                'layout' => 'vertical',
                'paddingAll' => '15px',
                'contents' => [
                    [
                        'type' => 'button',
                        'action' => [
                            'type' => 'uri',
                            'label' => 'สมัครสมาชิกฟรี',
                            'uri' => config('app.url') . '/register',
                        ],
                        'style' => 'primary',
                        'color' => self::COLOR_GREEN,
                    ],
                    [
                        'type' => 'button',
                        'action' => [
                            'type' => 'message',
                            'label' => 'ดูคู่มือใช้งาน',
                            'text' => '/help',
                        ],
                        'style' => 'secondary',
                        'margin' => 'sm',
                    ],
                ],
            ],
        ];
    }

    /**
     * Build welcome back card for returning users.
     */
    public function welcomeBackCard(string $userName): array
    {
        return [
            'type' => 'bubble',
            'size' => 'kilo',
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'backgroundColor' => self::COLOR_GREEN,
                'paddingAll' => '20px',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => "ยินดีต้อนรับกลับ {$userName}!",
                        'size' => 'lg',
                        'weight' => 'bold',
                        'color' => self::COLOR_WHITE,
                        'wrap' => true,
                    ],
                    [
                        'type' => 'text',
                        'text' => 'คุณสามารถเริ่มบันทึกรายรับ-รายจ่ายได้เลย',
                        'size' => 'sm',
                        'color' => self::COLOR_WHITE,
                        'margin' => 'md',
                        'wrap' => true,
                    ],
                ],
            ],
            'footer' => [
                'type' => 'box',
                'layout' => 'horizontal',
                'paddingAll' => '15px',
                'spacing' => 'sm',
                'contents' => [
                    [
                        'type' => 'button',
                        'action' => [
                            'type' => 'message',
                            'label' => 'ดูสรุป',
                            'text' => '/ยอดเดือนนี้',
                        ],
                        'style' => 'secondary',
                        'flex' => 1,
                    ],
                    [
                        'type' => 'button',
                        'action' => [
                            'type' => 'message',
                            'label' => 'คู่มือ',
                            'text' => '/help',
                        ],
                        'style' => 'secondary',
                        'flex' => 1,
                    ],
                ],
            ],
        ];
    }

    /**
     * Build income record card.
     */
    public function incomeRecordCard(
        Transaction $transaction,
        float $todayBalance,
        ?string $accountName = null
    ): array {
        return $this->transactionRecordCard(
            transaction: $transaction,
            todayBalance: $todayBalance,
            accountName: $accountName,
            headerColor: self::COLOR_GREEN,
            headerText: 'บันทึกรายรับสำเร็จ'
        );
    }

    /**
     * Build expense record card.
     */
    public function expenseRecordCard(
        Transaction $transaction,
        float $todayBalance,
        ?string $accountName = null
    ): array {
        return $this->transactionRecordCard(
            transaction: $transaction,
            todayBalance: $todayBalance,
            accountName: $accountName,
            headerColor: self::COLOR_RED,
            headerText: 'บันทึกรายจ่ายสำเร็จ'
        );
    }

    /**
     * Build transaction record card (shared by income/expense).
     */
    private function transactionRecordCard(
        Transaction $transaction,
        float $todayBalance,
        ?string $accountName,
        string $headerColor,
        string $headerText
    ): array {
        $category = $transaction->category;
        $categoryDisplay = $category ? "{$category->emoji} {$category->name}" : 'ไม่ระบุหมวดหมู่';
        $amountDisplay = '฿' . number_format($transaction->amount, 0);
        $dateDisplay = $transaction->transaction_date->locale('th')->isoFormat('D MMM');
        $accountDisplay = $accountName ?? 'บัญชีส่วนตัว';
        
        $balanceSign = $todayBalance >= 0 ? '+' : '';
        $balanceColor = $todayBalance >= 0 ? self::COLOR_GREEN : self::COLOR_RED;
        $todayBalanceDisplay = $balanceSign . '฿' . number_format($todayBalance, 0);

        $contents = [
            // Header
            [
                'type' => 'text',
                'text' => $headerText,
                'size' => 'xs',
                'color' => self::COLOR_WHITE,
            ],
            // Category
            [
                'type' => 'text',
                'text' => $categoryDisplay,
                'size' => 'lg',
                'weight' => 'bold',
                'color' => self::COLOR_WHITE,
                'margin' => 'md',
            ],
            // Amount
            [
                'type' => 'text',
                'text' => $amountDisplay,
                'size' => '3xl',
                'weight' => 'bold',
                'color' => self::COLOR_WHITE,
                'margin' => 'sm',
            ],
        ];

        // Add note if exists
        if ($transaction->note) {
            $contents[] = [
                'type' => 'text',
                'text' => "\"{$transaction->note}\"",
                'size' => 'sm',
                'color' => self::COLOR_WHITE,
                'margin' => 'sm',
                'wrap' => true,
            ];
        }

        // Add account and date
        $contents[] = [
            'type' => 'box',
            'layout' => 'horizontal',
            'margin' => 'lg',
            'contents' => [
                [
                    'type' => 'text',
                    'text' => $accountDisplay,
                    'size' => 'xs',
                    'color' => '#FFFFFF99',
                    'flex' => 1,
                ],
                [
                    'type' => 'text',
                    'text' => $dateDisplay,
                    'size' => 'xs',
                    'color' => '#FFFFFF99',
                    'align' => 'end',
                ],
            ],
        ];

        // Add separator and today's balance
        $contents[] = [
            'type' => 'separator',
            'margin' => 'lg',
            'color' => '#FFFFFF40',
        ];
        $contents[] = [
            'type' => 'box',
            'layout' => 'horizontal',
            'margin' => 'lg',
            'contents' => [
                [
                    'type' => 'text',
                    'text' => 'ยอดวันนี้',
                    'size' => 'sm',
                    'color' => '#FFFFFF99',
                    'flex' => 1,
                ],
                [
                    'type' => 'text',
                    'text' => $todayBalanceDisplay,
                    'size' => 'lg',
                    'weight' => 'bold',
                    'color' => self::COLOR_WHITE,
                    'align' => 'end',
                ],
            ],
        ];

        return [
            'type' => 'bubble',
            'size' => 'kilo',
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'backgroundColor' => $headerColor,
                'paddingAll' => '20px',
                'contents' => $contents,
            ],
            'footer' => [
                'type' => 'box',
                'layout' => 'horizontal',
                'paddingAll' => '15px',
                'spacing' => 'sm',
                'contents' => [
                    [
                        'type' => 'button',
                        'action' => [
                            'type' => 'message',
                            'label' => 'ยกเลิก',
                            'text' => '/ยกเลิก',
                        ],
                        'style' => 'secondary',
                        'height' => 'sm',
                        'flex' => 1,
                    ],
                    [
                        'type' => 'button',
                        'action' => [
                            'type' => 'message',
                            'label' => 'ดูสรุป',
                            'text' => '/ยอดวันนี้',
                        ],
                        'style' => 'secondary',
                        'height' => 'sm',
                        'flex' => 1,
                    ],
                    [
                        'type' => 'button',
                        'action' => [
                            'type' => 'message',
                            'label' => 'บันทึกอีก',
                            'text' => '/บันทึก',
                        ],
                        'style' => 'primary',
                        'color' => $headerColor,
                        'height' => 'sm',
                        'flex' => 1,
                    ],
                ],
            ],
        ];
    }

    /**
     * Build summary card.
     */
    public function summaryCard(
        float $totalIncome,
        float $totalExpense,
        string $periodLabel,
        ?string $periodDetail = null
    ): array {
        $balance = $totalIncome - $totalExpense;
        $balanceColor = $balance >= 0 ? self::COLOR_GREEN : self::COLOR_RED;
        $balanceSign = $balance >= 0 ? '+' : '';

        $incomePercent = ($totalIncome + $totalExpense) > 0 
            ? ($totalIncome / ($totalIncome + $totalExpense)) * 100 
            : 50;
        $expensePercent = 100 - $incomePercent;

        $contents = [
            // Header
            [
                'type' => 'text',
                'text' => "สรุปยอด{$periodLabel}",
                'size' => 'lg',
                'weight' => 'bold',
                'color' => self::COLOR_WHITE,
            ],
        ];

        if ($periodDetail) {
            $contents[] = [
                'type' => 'text',
                'text' => $periodDetail,
                'size' => 'xs',
                'color' => '#FFFFFF99',
                'margin' => 'sm',
            ];
        }

        // Income row
        $contents[] = [
            'type' => 'box',
            'layout' => 'vertical',
            'margin' => 'xl',
            'contents' => [
                [
                    'type' => 'box',
                    'layout' => 'horizontal',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => 'รายรับ',
                            'size' => 'sm',
                            'color' => self::COLOR_WHITE,
                            'flex' => 1,
                        ],
                        [
                            'type' => 'text',
                            'text' => '+฿' . number_format($totalIncome, 0),
                            'size' => 'sm',
                            'weight' => 'bold',
                            'color' => self::COLOR_WHITE,
                            'align' => 'end',
                        ],
                    ],
                ],
                [
                    'type' => 'box',
                    'layout' => 'horizontal',
                    'margin' => 'sm',
                    'contents' => [
                        [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'flex' => (int) max($incomePercent, 1),
                            'height' => '6px',
                            'backgroundColor' => self::COLOR_WHITE,
                            'cornerRadius' => '3px',
                            'contents' => [],
                        ],
                        [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'flex' => (int) max($expensePercent, 1),
                            'contents' => [],
                        ],
                    ],
                ],
            ],
        ];

        // Expense row
        $contents[] = [
            'type' => 'box',
            'layout' => 'vertical',
            'margin' => 'lg',
            'contents' => [
                [
                    'type' => 'box',
                    'layout' => 'horizontal',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => 'รายจ่าย',
                            'size' => 'sm',
                            'color' => self::COLOR_WHITE,
                            'flex' => 1,
                        ],
                        [
                            'type' => 'text',
                            'text' => '-฿' . number_format($totalExpense, 0),
                            'size' => 'sm',
                            'weight' => 'bold',
                            'color' => self::COLOR_WHITE,
                            'align' => 'end',
                        ],
                    ],
                ],
                [
                    'type' => 'box',
                    'layout' => 'horizontal',
                    'margin' => 'sm',
                    'contents' => [
                        [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'flex' => (int) max($expensePercent, 1),
                            'height' => '6px',
                            'backgroundColor' => self::COLOR_RED,
                            'cornerRadius' => '3px',
                            'contents' => [],
                        ],
                        [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'flex' => (int) max($incomePercent, 1),
                            'contents' => [],
                        ],
                    ],
                ],
            ],
        ];

        // Separator and Balance
        $contents[] = [
            'type' => 'separator',
            'margin' => 'xl',
            'color' => '#FFFFFF40',
        ];
        $contents[] = [
            'type' => 'box',
            'layout' => 'horizontal',
            'margin' => 'lg',
            'contents' => [
                [
                    'type' => 'text',
                    'text' => 'คงเหลือ',
                    'size' => 'md',
                    'weight' => 'bold',
                    'color' => self::COLOR_WHITE,
                    'flex' => 1,
                ],
                [
                    'type' => 'text',
                    'text' => $balanceSign . '฿' . number_format(abs($balance), 0),
                    'size' => 'xl',
                    'weight' => 'bold',
                    'color' => self::COLOR_WHITE,
                    'align' => 'end',
                ],
            ],
        ];

        return [
            'type' => 'bubble',
            'size' => 'kilo',
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'backgroundColor' => self::COLOR_GREEN,
                'paddingAll' => '20px',
                'contents' => $contents,
            ],
            'footer' => [
                'type' => 'box',
                'layout' => 'horizontal',
                'paddingAll' => '15px',
                'spacing' => 'sm',
                'contents' => [
                    [
                        'type' => 'button',
                        'action' => [
                            'type' => 'message',
                            'label' => 'วันนี้',
                            'text' => '/ยอดวันนี้',
                        ],
                        'style' => 'secondary',
                        'height' => 'sm',
                        'flex' => 1,
                    ],
                    [
                        'type' => 'button',
                        'action' => [
                            'type' => 'message',
                            'label' => 'สัปดาห์',
                            'text' => '/ยอดสัปดาห์',
                        ],
                        'style' => 'secondary',
                        'height' => 'sm',
                        'flex' => 1,
                    ],
                    [
                        'type' => 'button',
                        'action' => [
                            'type' => 'message',
                            'label' => 'เดือน',
                            'text' => '/ยอดเดือนนี้',
                        ],
                        'style' => 'secondary',
                        'height' => 'sm',
                        'flex' => 1,
                    ],
                ],
            ],
        ];
    }

    /**
     * Build statistics card with category breakdown.
     */
    public function statsCard(
        array $incomeByCategory,
        array $expenseByCategory,
        float $totalIncome,
        float $totalExpense,
        string $periodLabel
    ): array {
        $contents = [
            // Header
            [
                'type' => 'text',
                'text' => "สถิติ{$periodLabel}",
                'size' => 'lg',
                'weight' => 'bold',
                'color' => self::COLOR_WHITE,
            ],
        ];

        // Income section
        if (count($incomeByCategory) > 0) {
            $contents[] = [
                'type' => 'text',
                'text' => 'รายรับ',
                'size' => 'sm',
                'weight' => 'bold',
                'color' => self::COLOR_WHITE,
                'margin' => 'xl',
            ];

            foreach (array_slice($incomeByCategory, 0, 5) as $item) {
                $percent = $totalIncome > 0 ? ($item['amount'] / $totalIncome) * 100 : 0;
                $contents[] = $this->buildCategoryRow(
                    emoji: $item['emoji'],
                    name: $item['name'],
                    amount: $item['amount'],
                    percent: $percent,
                    barColor: self::COLOR_WHITE
                );
            }
        }

        // Expense section
        if (count($expenseByCategory) > 0) {
            $contents[] = [
                'type' => 'text',
                'text' => 'รายจ่าย',
                'size' => 'sm',
                'weight' => 'bold',
                'color' => self::COLOR_WHITE,
                'margin' => 'xl',
            ];

            foreach (array_slice($expenseByCategory, 0, 5) as $item) {
                $percent = $totalExpense > 0 ? ($item['amount'] / $totalExpense) * 100 : 0;
                $contents[] = $this->buildCategoryRow(
                    emoji: $item['emoji'],
                    name: $item['name'],
                    amount: $item['amount'],
                    percent: $percent,
                    barColor: self::COLOR_RED
                );
            }
        }

        // Summary
        $balance = $totalIncome - $totalExpense;
        $contents[] = [
            'type' => 'separator',
            'margin' => 'xl',
            'color' => '#FFFFFF40',
        ];
        $contents[] = [
            'type' => 'box',
            'layout' => 'horizontal',
            'margin' => 'lg',
            'contents' => [
                [
                    'type' => 'text',
                    'text' => 'รายรับรวม',
                    'size' => 'xs',
                    'color' => '#FFFFFF99',
                    'flex' => 1,
                ],
                [
                    'type' => 'text',
                    'text' => '+฿' . number_format($totalIncome, 0),
                    'size' => 'sm',
                    'color' => self::COLOR_WHITE,
                    'align' => 'end',
                ],
            ],
        ];
        $contents[] = [
            'type' => 'box',
            'layout' => 'horizontal',
            'margin' => 'sm',
            'contents' => [
                [
                    'type' => 'text',
                    'text' => 'รายจ่ายรวม',
                    'size' => 'xs',
                    'color' => '#FFFFFF99',
                    'flex' => 1,
                ],
                [
                    'type' => 'text',
                    'text' => '-฿' . number_format($totalExpense, 0),
                    'size' => 'sm',
                    'color' => self::COLOR_WHITE,
                    'align' => 'end',
                ],
            ],
        ];
        $contents[] = [
            'type' => 'box',
            'layout' => 'horizontal',
            'margin' => 'md',
            'contents' => [
                [
                    'type' => 'text',
                    'text' => 'คงเหลือ',
                    'size' => 'sm',
                    'weight' => 'bold',
                    'color' => self::COLOR_WHITE,
                    'flex' => 1,
                ],
                [
                    'type' => 'text',
                    'text' => ($balance >= 0 ? '+' : '') . '฿' . number_format($balance, 0),
                    'size' => 'md',
                    'weight' => 'bold',
                    'color' => self::COLOR_WHITE,
                    'align' => 'end',
                ],
            ],
        ];

        return [
            'type' => 'bubble',
            'size' => 'mega',
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'backgroundColor' => self::COLOR_ORANGE,
                'paddingAll' => '20px',
                'contents' => $contents,
            ],
            'footer' => [
                'type' => 'box',
                'layout' => 'horizontal',
                'paddingAll' => '15px',
                'spacing' => 'sm',
                'contents' => [
                    [
                        'type' => 'button',
                        'action' => [
                            'type' => 'message',
                            'label' => 'ดูสรุป',
                            'text' => '/ยอดเดือนนี้',
                        ],
                        'style' => 'secondary',
                        'height' => 'sm',
                    ],
                    [
                        'type' => 'button',
                        'action' => [
                            'type' => 'uri',
                            'label' => 'เปิดเว็บ',
                            'uri' => config('app.url') . '/dashboard',
                        ],
                        'style' => 'primary',
                        'color' => self::COLOR_ORANGE,
                        'height' => 'sm',
                    ],
                ],
            ],
        ];
    }

    /**
     * Build a category row for stats card.
     */
    private function buildCategoryRow(
        string $emoji,
        string $name,
        float $amount,
        float $percent,
        string $barColor
    ): array {
        return [
            'type' => 'box',
            'layout' => 'vertical',
            'margin' => 'md',
            'contents' => [
                [
                    'type' => 'box',
                    'layout' => 'horizontal',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => "{$emoji} {$name}",
                            'size' => 'xs',
                            'color' => self::COLOR_WHITE,
                            'flex' => 1,
                        ],
                        [
                            'type' => 'text',
                            'text' => '฿' . number_format($amount, 0),
                            'size' => 'xs',
                            'color' => self::COLOR_WHITE,
                            'align' => 'end',
                        ],
                    ],
                ],
                [
                    'type' => 'box',
                    'layout' => 'horizontal',
                    'margin' => 'sm',
                    'contents' => [
                        [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'flex' => (int) max($percent, 1),
                            'height' => '4px',
                            'backgroundColor' => $barColor,
                            'cornerRadius' => '2px',
                            'contents' => [],
                        ],
                        [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'flex' => (int) max(100 - $percent, 1),
                            'contents' => [],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Build help card.
     */
    public function helpCard(): array
    {
        return [
            'type' => 'bubble',
            'size' => 'mega',
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'backgroundColor' => self::COLOR_GREEN,
                'paddingAll' => '20px',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => 'คู่มือใช้งาน จดตังค์',
                        'size' => 'lg',
                        'weight' => 'bold',
                        'color' => self::COLOR_WHITE,
                    ],
                    [
                        'type' => 'separator',
                        'margin' => 'lg',
                        'color' => '#FFFFFF40',
                    ],
                    // Record section
                    [
                        'type' => 'text',
                        'text' => 'บันทึกรายการ',
                        'size' => 'sm',
                        'weight' => 'bold',
                        'color' => self::COLOR_WHITE,
                        'margin' => 'lg',
                    ],
                    [
                        'type' => 'text',
                        'text' => 'รายรับ: "เงินเดือน 5000"',
                        'size' => 'xs',
                        'color' => self::COLOR_WHITE,
                        'margin' => 'sm',
                    ],
                    [
                        'type' => 'text',
                        'text' => 'รายจ่าย: "อาหาร 150 ข้าวมันไก่"',
                        'size' => 'xs',
                        'color' => self::COLOR_WHITE,
                        'margin' => 'sm',
                    ],
                    // Commands section
                    [
                        'type' => 'text',
                        'text' => 'คำสั่ง',
                        'size' => 'sm',
                        'weight' => 'bold',
                        'color' => self::COLOR_WHITE,
                        'margin' => 'lg',
                    ],
                    [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'margin' => 'sm',
                        'spacing' => 'sm',
                        'contents' => [
                            $this->buildHelpCommandRow('/ยอดวันนี้', 'สรุปยอดวันนี้'),
                            $this->buildHelpCommandRow('/ยอดสัปดาห์', 'สรุปยอดสัปดาห์นี้'),
                            $this->buildHelpCommandRow('/ยอดเดือนนี้', 'สรุปยอดเดือนนี้'),
                            $this->buildHelpCommandRow('/สถิติ', 'ดูสถิติตามหมวดหมู่'),
                            $this->buildHelpCommandRow('/ยกเลิก', 'ลบรายการล่าสุด'),
                            $this->buildHelpCommandRow('/คำสั่ง', 'ดูคำสั่งลัดทั้งหมด'),
                            $this->buildHelpCommandRow('/หมวดหมู่', 'ดูหมวดหมู่ทั้งหมด'),
                            $this->buildHelpCommandRow('/ชื่อกลุ่ม [ชื่อ]', 'ตั้งชื่อกลุ่ม (ในกลุ่ม)'),
                            $this->buildHelpCommandRow('/ลบกลุ่ม', 'ยกเลิกกลุ่มและให้บอทออก'),
                        ],
                    ],
                    // Connect section
                    [
                        'type' => 'text',
                        'text' => 'เชื่อมต่อบัญชี',
                        'size' => 'sm',
                        'weight' => 'bold',
                        'color' => self::COLOR_WHITE,
                        'margin' => 'lg',
                    ],
                    [
                        'type' => 'text',
                        'text' => 'พิมพ์ CONNECT-XXXXXX จากเว็บ',
                        'size' => 'xs',
                        'color' => self::COLOR_WHITE,
                        'margin' => 'sm',
                    ],
                ],
            ],
            'footer' => [
                'type' => 'box',
                'layout' => 'vertical',
                'paddingAll' => '15px',
                'contents' => [
                    [
                        'type' => 'button',
                        'action' => [
                            'type' => 'uri',
                            'label' => 'เปิดเว็บแอป',
                            'uri' => config('app.url'),
                        ],
                        'style' => 'primary',
                        'color' => self::COLOR_GREEN,
                    ],
                ],
            ],
        ];
    }

    /**
     * Build a help command row.
     */
    private function buildHelpCommandRow(string $command, string $description): array
    {
        return [
            'type' => 'box',
            'layout' => 'horizontal',
            'contents' => [
                [
                    'type' => 'text',
                    'text' => $command,
                    'size' => 'xs',
                    'color' => self::COLOR_WHITE,
                    'flex' => 2,
                ],
                [
                    'type' => 'text',
                    'text' => $description,
                    'size' => 'xs',
                    'color' => '#FFFFFF99',
                    'flex' => 3,
                ],
            ],
        ];
    }

    /**
     * Build transaction list card.
     */
    public function transactionListCard(array $transactions, string $title): array
    {
        $contents = [
            [
                'type' => 'text',
                'text' => $title,
                'size' => 'md',
                'weight' => 'bold',
                'color' => self::COLOR_DARK,
            ],
            [
                'type' => 'separator',
                'margin' => 'md',
            ],
        ];

        if (empty($transactions)) {
            $contents[] = [
                'type' => 'text',
                'text' => 'ไม่มีรายการ',
                'size' => 'sm',
                'color' => self::COLOR_GRAY,
                'margin' => 'md',
            ];
        } else {
            foreach ($transactions as $transaction) {
                $contents[] = $this->buildTransactionRow($transaction);
            }
        }

        return [
            'type' => 'bubble',
            'size' => 'mega',
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'paddingAll' => '20px',
                'contents' => $contents,
            ],
        ];
    }

    /**
     * Build a transaction row for list.
     */
    private function buildTransactionRow(Transaction $transaction): array
    {
        $category = $transaction->category;
        $categoryEmoji = $category ? $category->emoji : '';
        $categoryName = $category ? $category->name : 'ไม่ระบุ';
        
        $amountColor = $transaction->isIncome() ? self::COLOR_GREEN : self::COLOR_RED;
        $amountSign = $transaction->isIncome() ? '+' : '-';
        $amountText = $amountSign . '฿' . number_format($transaction->amount, 0);
        
        $dateText = $transaction->transaction_date->locale('th')->isoFormat('D MMM');

        return [
            'type' => 'box',
            'layout' => 'horizontal',
            'margin' => 'md',
            'contents' => [
                [
                    'type' => 'text',
                    'text' => $categoryEmoji,
                    'size' => 'md',
                    'flex' => 0,
                ],
                [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'flex' => 1,
                    'margin' => 'sm',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => $categoryName,
                            'size' => 'sm',
                            'color' => self::COLOR_DARK,
                        ],
                        [
                            'type' => 'text',
                            'text' => $transaction->note ?? '',
                            'size' => 'xs',
                            'color' => self::COLOR_GRAY,
                        ],
                    ],
                ],
                [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'flex' => 0,
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => $amountText,
                            'size' => 'sm',
                            'weight' => 'bold',
                            'color' => $amountColor,
                            'align' => 'end',
                        ],
                        [
                            'type' => 'text',
                            'text' => $dateText,
                            'size' => 'xs',
                            'color' => self::COLOR_GRAY,
                            'align' => 'end',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Build error card.
     */
    public function errorCard(string $message, ?string $suggestion = null): array
    {
        $contents = [
            [
                'type' => 'text',
                'text' => 'เกิดข้อผิดพลาด',
                'size' => 'md',
                'weight' => 'bold',
                'color' => self::COLOR_WHITE,
            ],
            [
                'type' => 'text',
                'text' => $message,
                'size' => 'sm',
                'color' => self::COLOR_WHITE,
                'margin' => 'md',
                'wrap' => true,
            ],
        ];

        if ($suggestion) {
            $contents[] = [
                'type' => 'separator',
                'margin' => 'lg',
                'color' => '#FFFFFF40',
            ];
            $contents[] = [
                'type' => 'text',
                'text' => $suggestion,
                'size' => 'xs',
                'color' => '#FFFFFF99',
                'margin' => 'lg',
                'wrap' => true,
            ];
        }

        return [
            'type' => 'bubble',
            'size' => 'kilo',
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'backgroundColor' => self::COLOR_RED,
                'paddingAll' => '20px',
                'contents' => $contents,
            ],
            'footer' => [
                'type' => 'box',
                'layout' => 'horizontal',
                'paddingAll' => '15px',
                'contents' => [
                    [
                        'type' => 'button',
                        'action' => [
                            'type' => 'message',
                            'label' => 'ดูคู่มือ',
                            'text' => '/help',
                        ],
                        'style' => 'secondary',
                    ],
                ],
            ],
        ];
    }

    /**
     * Build connection success card.
     */
    public function connectionSuccessCard(string $userName): array
    {
        return [
            'type' => 'bubble',
            'size' => 'kilo',
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'backgroundColor' => self::COLOR_GREEN,
                'paddingAll' => '20px',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => 'เชื่อมต่อสำเร็จ!',
                        'size' => 'lg',
                        'weight' => 'bold',
                        'color' => self::COLOR_WHITE,
                    ],
                    [
                        'type' => 'text',
                        'text' => "สวัสดี {$userName}",
                        'size' => 'md',
                        'color' => self::COLOR_WHITE,
                        'margin' => 'md',
                    ],
                    [
                        'type' => 'text',
                        'text' => 'คุณสามารถเริ่มบันทึกรายรับ-รายจ่ายได้แล้ว',
                        'size' => 'sm',
                        'color' => '#FFFFFF99',
                        'margin' => 'sm',
                        'wrap' => true,
                    ],
                ],
            ],
            'footer' => [
                'type' => 'box',
                'layout' => 'horizontal',
                'paddingAll' => '15px',
                'spacing' => 'sm',
                'contents' => [
                    [
                        'type' => 'button',
                        'action' => [
                            'type' => 'message',
                            'label' => 'ดูคู่มือ',
                            'text' => '/help',
                        ],
                        'style' => 'secondary',
                        'flex' => 1,
                    ],
                    [
                        'type' => 'button',
                        'action' => [
                            'type' => 'message',
                            'label' => 'ดูสรุป',
                            'text' => '/ยอดเดือนนี้',
                        ],
                        'style' => 'primary',
                        'color' => self::COLOR_GREEN,
                        'flex' => 1,
                    ],
                ],
            ],
        ];
    }

    /**
     * Build shortcuts list card.
     */
    public function shortcutsCard(array $shortcuts): array
    {
        $contents = [
            [
                'type' => 'text',
                'text' => 'คำสั่งลัดของคุณ',
                'size' => 'md',
                'weight' => 'bold',
                'color' => self::COLOR_DARK,
            ],
            [
                'type' => 'separator',
                'margin' => 'md',
            ],
        ];

        if (empty($shortcuts)) {
            $contents[] = [
                'type' => 'text',
                'text' => 'ยังไม่มีคำสั่งลัด',
                'size' => 'sm',
                'color' => self::COLOR_GRAY,
                'margin' => 'md',
            ];
            $contents[] = [
                'type' => 'text',
                'text' => 'สร้างคำสั่งลัดได้ที่เว็บแอป',
                'size' => 'xs',
                'color' => self::COLOR_LIGHT_GRAY,
                'margin' => 'sm',
            ];
        } else {
            foreach ($shortcuts as $shortcut) {
                $typeLabel = $shortcut->type === TransactionType::INCOME ? 'รายรับ' : 'รายจ่าย';
                $typeColor = $shortcut->type === TransactionType::INCOME ? self::COLOR_GREEN : self::COLOR_RED;
                $categoryName = $shortcut->category ? $shortcut->category->name : 'ไม่ระบุ';

                $contents[] = [
                    'type' => 'box',
                    'layout' => 'horizontal',
                    'margin' => 'md',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => $shortcut->emoji ?? '',
                            'size' => 'md',
                            'flex' => 0,
                        ],
                        [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'flex' => 1,
                            'margin' => 'sm',
                            'contents' => [
                                [
                                    'type' => 'text',
                                    'text' => $shortcut->keyword,
                                    'size' => 'sm',
                                    'weight' => 'bold',
                                    'color' => self::COLOR_DARK,
                                ],
                                [
                                    'type' => 'text',
                                    'text' => "{$categoryName} ({$typeLabel})",
                                    'size' => 'xs',
                                    'color' => self::COLOR_GRAY,
                                ],
                            ],
                        ],
                        [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'flex' => 0,
                            'width' => '8px',
                            'height' => '30px',
                            'backgroundColor' => $typeColor,
                            'cornerRadius' => '4px',
                            'contents' => [],
                        ],
                    ],
                ];
            }
        }

        return [
            'type' => 'bubble',
            'size' => 'mega',
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'paddingAll' => '20px',
                'contents' => $contents,
            ],
            'footer' => [
                'type' => 'box',
                'layout' => 'vertical',
                'paddingAll' => '15px',
                'contents' => [
                    [
                        'type' => 'button',
                        'action' => [
                            'type' => 'uri',
                            'label' => 'จัดการคำสั่งลัด',
                            'uri' => config('app.url') . '/shortcuts',
                        ],
                        'style' => 'primary',
                        'color' => self::COLOR_GREEN,
                    ],
                ],
            ],
        ];
    }

    /**
     * Build categories list card.
     */
    public function categoriesCard(array $incomeCategories, array $expenseCategories): array
    {
        $contents = [
            [
                'type' => 'text',
                'text' => 'หมวดหมู่',
                'size' => 'md',
                'weight' => 'bold',
                'color' => self::COLOR_DARK,
            ],
            [
                'type' => 'separator',
                'margin' => 'md',
            ],
        ];

        // Income categories
        $contents[] = [
            'type' => 'text',
            'text' => 'รายรับ',
            'size' => 'sm',
            'weight' => 'bold',
            'color' => self::COLOR_GREEN,
            'margin' => 'lg',
        ];

        $incomeItems = [];
        foreach ($incomeCategories as $category) {
            $incomeItems[] = "{$category->emoji} {$category->name}";
        }
        $contents[] = [
            'type' => 'text',
            'text' => empty($incomeItems) ? 'ไม่มีหมวดหมู่' : implode('  ', $incomeItems),
            'size' => 'sm',
            'color' => self::COLOR_GRAY,
            'margin' => 'sm',
            'wrap' => true,
        ];

        // Expense categories
        $contents[] = [
            'type' => 'text',
            'text' => 'รายจ่าย',
            'size' => 'sm',
            'weight' => 'bold',
            'color' => self::COLOR_RED,
            'margin' => 'lg',
        ];

        $expenseItems = [];
        foreach ($expenseCategories as $category) {
            $expenseItems[] = "{$category->emoji} {$category->name}";
        }
        $contents[] = [
            'type' => 'text',
            'text' => empty($expenseItems) ? 'ไม่มีหมวดหมู่' : implode('  ', $expenseItems),
            'size' => 'sm',
            'color' => self::COLOR_GRAY,
            'margin' => 'sm',
            'wrap' => true,
        ];

        return [
            'type' => 'bubble',
            'size' => 'mega',
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'paddingAll' => '20px',
                'contents' => $contents,
            ],
        ];
    }

    /**
     * Build group welcome card.
     */
    public function groupWelcomeCard(?string $groupName = null): array
    {
        $title = $groupName ? "สวัสดี {$groupName}!" : 'สวัสดีครับ!';

        return [
            'type' => 'bubble',
            'size' => 'kilo',
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'backgroundColor' => self::COLOR_GREEN,
                'paddingAll' => '20px',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => $title,
                        'size' => 'lg',
                        'weight' => 'bold',
                        'color' => self::COLOR_WHITE,
                    ],
                    [
                        'type' => 'text',
                        'text' => 'ผม จดตังค์ บอทบันทึกรายรับ-รายจ่าย',
                        'size' => 'sm',
                        'color' => self::COLOR_WHITE,
                        'margin' => 'md',
                        'wrap' => true,
                    ],
                    [
                        'type' => 'separator',
                        'margin' => 'lg',
                        'color' => '#FFFFFF40',
                    ],
                    [
                        'type' => 'text',
                        'text' => 'วิธีใช้งานในกลุ่ม:',
                        'size' => 'sm',
                        'weight' => 'bold',
                        'color' => self::COLOR_WHITE,
                        'margin' => 'lg',
                    ],
                    [
                        'type' => 'text',
                        'text' => 'บันทึกรายการ: "อาหาร 150 ข้าวเย็น"',
                        'size' => 'xs',
                        'color' => self::COLOR_WHITE,
                        'margin' => 'sm',
                    ],
                    [
                        'type' => 'text',
                        'text' => 'ดูสรุป: /ยอดเดือนนี้',
                        'size' => 'xs',
                        'color' => self::COLOR_WHITE,
                        'margin' => 'sm',
                    ],
                    [
                        'type' => 'text',
                        'text' => 'ตั้งชื่อกลุ่ม: /ชื่อกลุ่ม [ชื่อ]',
                        'size' => 'xs',
                        'color' => self::COLOR_WHITE,
                        'margin' => 'sm',
                    ],
                ],
            ],
            'footer' => [
                'type' => 'box',
                'layout' => 'horizontal',
                'paddingAll' => '15px',
                'contents' => [
                    [
                        'type' => 'button',
                        'action' => [
                            'type' => 'message',
                            'label' => 'ดูคู่มือ',
                            'text' => '/help',
                        ],
                        'style' => 'secondary',
                    ],
                ],
            ],
        ];
    }

    /**
     * Build cancel success card.
     */
    public function cancelSuccessCard(Transaction $transaction): array
    {
        $category = $transaction->category;
        $categoryDisplay = $category ? "{$category->emoji} {$category->name}" : 'ไม่ระบุหมวดหมู่';
        $amountDisplay = '฿' . number_format($transaction->amount, 0);
        $typeLabel = $transaction->isIncome() ? 'รายรับ' : 'รายจ่าย';

        return [
            'type' => 'bubble',
            'size' => 'kilo',
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'backgroundColor' => self::COLOR_GRAY,
                'paddingAll' => '20px',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => 'ยกเลิกรายการแล้ว',
                        'size' => 'md',
                        'weight' => 'bold',
                        'color' => self::COLOR_WHITE,
                    ],
                    [
                        'type' => 'box',
                        'layout' => 'horizontal',
                        'margin' => 'lg',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => $categoryDisplay,
                                'size' => 'sm',
                                'color' => self::COLOR_WHITE,
                                'flex' => 1,
                                'decoration' => 'line-through',
                            ],
                            [
                                'type' => 'text',
                                'text' => $amountDisplay,
                                'size' => 'sm',
                                'color' => self::COLOR_WHITE,
                                'align' => 'end',
                                'decoration' => 'line-through',
                            ],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'text' => "({$typeLabel})",
                        'size' => 'xs',
                        'color' => '#FFFFFF99',
                        'margin' => 'sm',
                    ],
                ],
            ],
        ];
    }
}
