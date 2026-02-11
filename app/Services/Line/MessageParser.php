<?php

namespace App\Services\Line;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Shortcut;
use App\Models\User;

class MessageParser
{
    /**
     * Parse result types.
     */
    public const TYPE_COMMAND = 'command';
    public const TYPE_CONNECTION_CODE = 'connection_code';
    public const TYPE_TRANSACTION = 'transaction';
    public const TYPE_UNKNOWN = 'unknown';

    /**
     * Parse a message from the user.
     *
     * @param string $message The raw message text
     * @param User|null $user The user who sent the message (null for unconnected users)
     * @return ParsedMessage
     */
    public function parse(string $message, ?User $user = null): ParsedMessage
    {
        $message = trim($message);

        // 1. Check if it's a command (starts with /)
        if (str_starts_with($message, '/')) {
            return $this->parseCommand($message);
        }

        // 2. Check if it's a connection code (CONNECT-XXXXXX)
        if (preg_match('/^CONNECT-[A-Z0-9]{6}$/i', $message)) {
            return new ParsedMessage(
                type: self::TYPE_CONNECTION_CODE,
                connectionCode: strtoupper($message)
            );
        }

        // 3. Try to parse as transaction
        if ($user) {
            $transactionResult = $this->parseTransaction($message, $user);
            if ($transactionResult) {
                return $transactionResult;
            }
        }

        // 4. Unknown message type
        return new ParsedMessage(
            type: self::TYPE_UNKNOWN,
            rawMessage: $message
        );
    }

    /**
     * Parse a command message.
     */
    private function parseCommand(string $message): ParsedMessage
    {
        // Extract command and arguments
        $parts = preg_split('/\s+/', $message, 2);
        $command = strtolower($parts[0]);
        $argument = $parts[1] ?? null;

        // Map Thai commands to standard commands
        $commandMap = [
            '/help' => 'help',
            '/ช่วยเหลือ' => 'help',
            '/ยอดวันนี้' => 'summary_today',
            '/ยอดสัปดาห์' => 'summary_week',
            '/ยอดเดือนนี้' => 'summary_month',
            '/ยอดรวม' => 'summary_all',
            '/สถิติ' => 'stats',
            '/ยกเลิก' => 'cancel',
            '/คำสั่ง' => 'shortcuts',
            '/หมวดหมู่' => 'categories',
            '/สถานะ' => 'status',
            '/รายการล่าสุด' => 'recent',
            '/เคลียร์ยอด' => 'clear',
            '/ลบกลุ่ม' => 'delete_group',
            '/ชื่อกลุ่ม' => 'rename_group',
            '/บันทึก' => 'record',
        ];

        $normalizedCommand = $commandMap[$command] ?? 'unknown';

        return new ParsedMessage(
            type: self::TYPE_COMMAND,
            command: $normalizedCommand,
            commandArgument: $argument,
            rawMessage: $message
        );
    }

    /**
     * Parse a transaction message.
     *
     * Format: [keyword/emoji] [amount] [note]
     * Examples:
     *   "เงินเดือน 5000"
     *   "เงินเดือน 5000 เดือนมกราคม"
     *   "Kie 2000 ทดสอบ"
     *   "รับ 1000"
     */
    private function parseTransaction(string $message, User $user): ?ParsedMessage
    {
        // Try to extract amount from the message
        // Pattern: keyword amount [note]
        // Amount can be integer or decimal, with optional commas
        if (!preg_match('/^(.+?)\s+([\d,]+(?:\.\d{1,2})?)\s*(.*)$/u', $message, $matches)) {
            return null;
        }

        $keyword = trim($matches[1]);
        $amountStr = str_replace(',', '', $matches[2]);
        $amount = (float) $amountStr;
        $note = trim($matches[3]) ?: null;

        // Validate amount
        if ($amount <= 0) {
            return null;
        }

        // Try to match keyword with user's shortcuts
        $shortcut = $this->findShortcut($keyword, $user);

        if ($shortcut) {
            return new ParsedMessage(
                type: self::TYPE_TRANSACTION,
                keyword: $keyword,
                amount: $amount,
                note: $note,
                transactionType: $shortcut->type,
                category: $shortcut->category,
                shortcut: $shortcut,
                rawMessage: $message
            );
        }

        // Try to match with default categories
        $category = $this->findDefaultCategory($keyword);

        if ($category) {
            return new ParsedMessage(
                type: self::TYPE_TRANSACTION,
                keyword: $keyword,
                amount: $amount,
                note: $note,
                transactionType: $category->type,
                category: $category,
                rawMessage: $message
            );
        }

        // No matching shortcut or category found
        return new ParsedMessage(
            type: self::TYPE_UNKNOWN,
            keyword: $keyword,
            amount: $amount,
            note: $note,
            rawMessage: $message
        );
    }

    /**
     * Find a matching shortcut for the keyword.
     */
    private function findShortcut(string $keyword, User $user): ?Shortcut
    {
        // Normalize keyword for comparison
        $normalizedKeyword = mb_strtolower(trim($keyword));

        // First, try exact match with keyword
        $shortcut = Shortcut::where('user_id', $user->id)
            ->where(function ($query) use ($keyword, $normalizedKeyword) {
                $query->whereRaw('LOWER(keyword) = ?', [$normalizedKeyword])
                    ->orWhere('emoji', $keyword);
            })
            ->with('category')
            ->first();

        if ($shortcut) {
            return $shortcut;
        }

        // Try to match emoji at the start of keyword
        // e.g., "รับ" matches emoji "รับ" in shortcut
        return Shortcut::where('user_id', $user->id)
            ->where(function ($query) use ($keyword) {
                // Check if the keyword starts with the emoji
                $query->whereRaw('? LIKE CONCAT(emoji, "%")', [$keyword])
                    ->orWhereRaw('? LIKE CONCAT(keyword, "%")', [$keyword]);
            })
            ->with('category')
            ->first();
    }

    /**
     * Find a matching default category.
     */
    private function findDefaultCategory(string $keyword): ?Category
    {
        $normalizedKeyword = mb_strtolower(trim($keyword));

        // Try to match with default category names or emojis
        return Category::where('is_default', true)
            ->where(function ($query) use ($keyword, $normalizedKeyword) {
                $query->whereRaw('LOWER(name) = ?', [$normalizedKeyword])
                    ->orWhere('emoji', $keyword);
            })
            ->first();
    }

    /**
     * Check if a message looks like a transaction attempt.
     * Useful for providing helpful error messages.
     */
    public function looksLikeTransaction(string $message): bool
    {
        // Check if the message contains a number that could be an amount
        return (bool) preg_match('/\d+(?:\.\d{1,2})?/', $message);
    }

    /**
     * Extract potential amount from a message.
     */
    public function extractAmount(string $message): ?float
    {
        if (preg_match('/([\d,]+(?:\.\d{1,2})?)/', $message, $matches)) {
            $amountStr = str_replace(',', '', $matches[1]);
            $amount = (float) $amountStr;
            return $amount > 0 ? $amount : null;
        }

        return null;
    }
}

/**
 * Data class for parsed message results.
 */
class ParsedMessage
{
    public function __construct(
        public readonly string $type,
        public readonly ?string $command = null,
        public readonly ?string $commandArgument = null,
        public readonly ?string $connectionCode = null,
        public readonly ?string $keyword = null,
        public readonly ?float $amount = null,
        public readonly ?string $note = null,
        public readonly ?TransactionType $transactionType = null,
        public readonly ?Category $category = null,
        public readonly ?Shortcut $shortcut = null,
        public readonly ?string $rawMessage = null,
    ) {}

    /**
     * Check if this is a command message.
     */
    public function isCommand(): bool
    {
        return $this->type === MessageParser::TYPE_COMMAND;
    }

    /**
     * Check if this is a connection code.
     */
    public function isConnectionCode(): bool
    {
        return $this->type === MessageParser::TYPE_CONNECTION_CODE;
    }

    /**
     * Check if this is a valid transaction.
     */
    public function isTransaction(): bool
    {
        return $this->type === MessageParser::TYPE_TRANSACTION;
    }

    /**
     * Check if this is an unknown message type.
     */
    public function isUnknown(): bool
    {
        return $this->type === MessageParser::TYPE_UNKNOWN;
    }

    /**
     * Check if this is an income transaction.
     */
    public function isIncome(): bool
    {
        return $this->isTransaction() && $this->transactionType === TransactionType::INCOME;
    }

    /**
     * Check if this is an expense transaction.
     */
    public function isExpense(): bool
    {
        return $this->isTransaction() && $this->transactionType === TransactionType::EXPENSE;
    }
}
