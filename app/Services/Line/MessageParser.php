<?php

namespace App\Services\Line;

use App\Models\Category;
use App\Models\Shortcut;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class MessageParser
{
    /**
     * Parse result types.
     */
    public const TYPE_COMMAND         = 'command';
    public const TYPE_CONNECTION_CODE = 'connection_code';
    public const TYPE_TRANSACTION     = 'transaction';
    public const TYPE_UNKNOWN         = 'unknown';

    /**
     * How long to cache a user's shortcuts (seconds).
     */
    private const SHORTCUT_CACHE_TTL = 60;

    /**
     * Parse a message from the user.
     *
     * @param string   $message The raw message text
     * @param User|null $user   The user who sent the message (null for unconnected users)
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
        $parts    = preg_split('/\s+/', $message, 2);
        $command  = strtolower($parts[0]);
        $argument = $parts[1] ?? null;

        // Map Thai commands to standard commands
        $commandMap = [
            '/help'           => 'help',
            '/ช่วยเหลือ'      => 'help',
            '/ยอดวันนี้'      => 'summary_today',
            '/ยอดสัปดาห์'     => 'summary_week',
            '/ยอดเดือนนี้'    => 'summary_month',
            '/ยอดรวม'         => 'summary_all',
            '/สถิติ'          => 'stats',
            '/ยกเลิก'         => 'cancel',
            '/คำสั่ง'         => 'shortcuts',
            '/หมวดหมู่'       => 'categories',
            '/สถานะ'          => 'status',
            '/รายการล่าสุด'   => 'recent',
            '/เคลียร์ยอด'     => 'clear',
            '/ยืนยัน'         => 'confirm',
            '/ลบกลุ่ม'        => 'delete_group',
            '/ชื่อกลุ่ม'      => 'rename_group',
            '/บันทึก'         => 'record',
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
     *   "อาหาร 150 ข้าวมันไก่"
     */
    private function parseTransaction(string $message, User $user): ?ParsedMessage
    {
        // Pattern: keyword amount [note]
        // Amount can be integer or decimal, with optional commas
        if (!preg_match('/^(.+?)\s+([\d,]+(?:\.\d{1,2})?)\s*(.*)$/u', $message, $matches)) {
            return null;
        }

        $keyword   = trim($matches[1]);
        $amountStr = str_replace(',', '', $matches[2]);
        $amount    = (float) $amountStr;
        $note      = trim($matches[3]) ?: null;

        // Validate amount
        if ($amount <= 0) {
            return null;
        }

        // Load all shortcuts once from cache — avoids N+1 per message
        $shortcuts = $this->getCachedShortcuts($user);

        // Try to match keyword with user's shortcuts
        $shortcut = $this->matchShortcut($keyword, $shortcuts);

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
     * Load all shortcuts for a user from cache (1 DB query per user per TTL window).
     */
    private function getCachedShortcuts(User $user): Collection
    {
        return Cache::remember(
            "shortcuts:user:{$user->id}",
            self::SHORTCUT_CACHE_TTL,
            fn () => Shortcut::where('user_id', $user->id)->with('category')->get()
        );
    }

    /**
     * Find a matching shortcut from an already-loaded collection.
     * All filtering done in PHP — no extra DB queries.
     */
    private function matchShortcut(string $keyword, Collection $shortcuts): ?Shortcut
    {
        $normalizedKeyword = mb_strtolower(trim($keyword));

        // 1. Exact keyword match (case-insensitive) or exact emoji match
        $match = $shortcuts->first(function (Shortcut $s) use ($keyword, $normalizedKeyword) {
            return mb_strtolower($s->keyword) === $normalizedKeyword
                || $s->emoji === $keyword;
        });

        if ($match) {
            return $match;
        }

        // 2. Prefix match — keyword or emoji is a prefix of the input
        return $shortcuts->first(function (Shortcut $s) use ($keyword) {
            return str_starts_with($keyword, $s->emoji ?? '')
                || str_starts_with($keyword, $s->keyword ?? '');
        });
    }

    /**
     * Find a matching default category (single query, result is naturally small).
     */
    private function findDefaultCategory(string $keyword): ?Category
    {
        $normalizedKeyword = mb_strtolower(trim($keyword));

        return Category::where('is_default', true)
            ->where(function ($query) use ($keyword, $normalizedKeyword) {
                $query->where('name', $normalizedKeyword)
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
        return (bool) preg_match('/\d+(?:\.\d{1,2})?/', $message);
    }

    /**
     * Extract potential amount from a message.
     */
    public function extractAmount(string $message): ?float
    {
        if (preg_match('/([\d,]+(?:\.\d{1,2})?)/', $message, $matches)) {
            $amount = (float) str_replace(',', '', $matches[1]);
            return $amount > 0 ? $amount : null;
        }

        return null;
    }

    /**
     * Invalidate the shortcuts cache for a user.
     * Call this whenever shortcuts are created, updated, or deleted.
     */
    public function invalidateShortcutsCache(int $userId): void
    {
        Cache::forget("shortcuts:user:{$userId}");
    }
}
